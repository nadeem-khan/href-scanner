import hashlib
import importlib.util
import pathlib
import re
import tempfile
import zipfile

root = pathlib.Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("release_build", root / "scripts" / "build.py")
release_build = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release_build)
header = (root / "href-scanner.php").read_text(encoding="utf-8")
readme = (root / "readme.txt").read_text(encoding="utf-8")
for field in ["Requires at least", "Requires PHP", "License", "License URI"]:
    assert re.search(rf"^ \* {field}: (.+)$", header, re.MULTILINE).group(1) == re.search(rf"^{field}: (.+)$", readme, re.MULTILINE).group(1), field
assert re.search(r"^Contributors: (.+)$", readme, re.MULTILINE).group(1) == "chillopedia"
assert re.search(r"^ \* Text Domain: (.+)$", header, re.MULTILINE).group(1) == release_build.slug
assert len(re.search(r"^Tags: (.+)$", readme, re.MULTILINE).group(1).split(",")) <= 5
assert len(readme.split("\n\n", 1)[1].splitlines()[0]) <= 150
for section in ["Description", "Installation", "Frequently Asked Questions", "Changelog", "Upgrade Notice"]:
    assert f"== {section} ==" in readme, section
for name in ["README.md", "CONTRIBUTING.md", "SECURITY.md", "CHANGELOG.md", "THIRD-PARTY-NOTICES.txt", "LICENSE", ".gitignore", ".distignore", "docs/WORDPRESS_ORG_RELEASE_CHECKLIST.md", "docs/RELEASING.md", "docs/FIRST_RELEASE.md", ".phpcs.xml.dist"]:
    assert (root / name).is_file(), name
assert "Plugin URI: https://github.com/nadeem-khan/href-scanner" in header
assert "https://github.com/nadeem-khan/href-scanner" in readme
archive_path = release_build.build()
first_hash = hashlib.sha256(archive_path.read_bytes()).hexdigest()
assert hashlib.sha256(release_build.build().read_bytes()).hexdigest() == first_hash, "Build must be repeatable."
with zipfile.ZipFile(archive_path) as archive, tempfile.TemporaryDirectory(dir=root / "dist") as temporary_dir:
    assert (root / "dist").resolve() in pathlib.Path(temporary_dir).resolve().parents
    archive.extractall(temporary_dir)
    assert archive.testzip() is None
    assert (pathlib.Path(temporary_dir) / release_build.slug / "href-scanner.php").read_bytes() == (root / "href-scanner.php").read_bytes()
    assert all(name.startswith(release_build.slug + "/") and ".." not in pathlib.PurePosixPath(name).parts for name in archive.namelist())
    assert not any(name.split("/", 1)[1].startswith((".git", ".audit", "tests/", "scripts/", "docs/", "vendor/", "node_modules/")) for name in archive.namelist())
print("Release metadata, readme structure, reproducibility, extraction and archive exclusion checks passed.")
