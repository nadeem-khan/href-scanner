import pathlib
import re
import subprocess
import sys

root = pathlib.Path(__file__).resolve().parents[1]
patterns = {
    "private key": r"-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----",
    "AWS access key": r"\b(?:AKIA|ASIA)[A-Z0-9]{16}\b",
    "GitHub token": r"\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,})\b",
    "API token": r"\b(?:sk-(?:proj-)?[A-Za-z0-9_-]{32,}|xox[baprs]-[A-Za-z0-9-]{20,})\b",
    "credential assignment": r"(?i)(?:password|passwd|client_secret|api_key|access_token|DB_PASSWORD)[\s'\"]*[:=,]\s*['\"][^'\"\r\n]{8,}['\"]",
    "credential URL": r"https?://[^\s/<>\"']+:[^\s/@<>\"']+@[^\s/<>\"']+",
}


def scan_text(text):
    findings = []
    for line_number, line in enumerate(text.splitlines(), 1):
        for secret_type, pattern in patterns.items():
            for match in re.finditer(pattern, line):
                if secret_type == "credential URL" and re.fullmatch(r"https://" + r"user:pass@example\.test", match.group()):
                    continue
                findings.append((line_number, secret_type))
    return findings


def git(*args):
    return subprocess.check_output(["git", "-C", str(root), *args])


def main():
    findings = []
    working_files = 0
    for path in sorted(root.rglob("*")):
        relative_path = path.relative_to(root)
        if any(part in {".git", ".audit", "dist", "release", "node_modules", "vendor", "__pycache__"} for part in relative_path.parts) or not path.is_file():
            continue
        working_files += 1
        if path.is_symlink():
            findings.append((str(relative_path), 0, "symlink needs review"))
            continue
        if path.suffix.lower() in {".sql", ".pem", ".key", ".p12", ".pfx", ".sqlite", ".sqlite3"} or path.name in {".env", "wp-config.php", "auth.json"} or path.name.startswith(".env."):
            findings.append((str(relative_path), 0, "sensitive file needs review"))
        for line_number, secret_type in scan_text(path.read_bytes().decode("utf-8", errors="replace")):
            findings.append((str(relative_path), line_number, secret_type))
    objects = git("rev-list", "--objects", "--all", "--reflog").decode().splitlines()
    history_blobs = 0
    for entry in objects:
        object_id, _, path = entry.partition(" ")
        if git("cat-file", "-t", object_id).strip() != b"blob":
            continue
        history_blobs += 1
        for line_number, secret_type in scan_text(git("cat-file", "blob", object_id).decode("utf-8", errors="replace")):
            findings.append((f"history {object_id[:12]} {path}", line_number, secret_type))
    for line_number, secret_type in scan_text(git("log", "--all", "--reflog", "--format=%B").decode("utf-8", errors="replace")):
        findings.append(("commit messages", line_number, secret_type))
    config_path = git("rev-parse", "--git-path", "config").decode().strip()
    for line_number, secret_type in scan_text((root / config_path).read_text(encoding="utf-8", errors="replace")):
        findings.append(("Git config", line_number, secret_type))
    print(f"Scanned {working_files} working files and {history_blobs} available Git blobs, including reflogs and commit messages.")
    for path, line_number, secret_type in findings:
        print(f"REVIEW REQUIRED: {path}:{line_number}: {secret_type}")
    if findings:
        return 1
    print("No candidate credentials or private data files found. Pattern scanning requires human review; it is not proof of absence.")
    return 0


if __name__ == "__main__":
    if "--self-test" in sys.argv:
        assert scan_text("-----BEGIN " + "PRIVATE KEY-----")
        assert scan_text("ghp_" + "a" * 36)
        assert scan_text("password = '" + "test-secret-value" + "'")
        assert not scan_text("https://user:pass@example.test")
        assert not scan_text("License: GPL-2.0-or-later")
        print("Secret scanner self-check passed.")
    else:
        sys.exit(main())
