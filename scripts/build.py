import hashlib
import pathlib
import re
import zipfile

root = pathlib.Path(__file__).resolve().parents[1]
slug = "href-scanner"
runtime_files = ["href-scanner.php", "admin.js", "listings.css", "readme.txt", "LICENSE", "THIRD-PARTY-NOTICES.txt"]


def build():
    header = (root / "href-scanner.php").read_text(encoding="utf-8")
    readme = (root / "readme.txt").read_text(encoding="utf-8")
    version = re.search(r"^ \* Version: (\d+\.\d+\.\d+)$", header, re.MULTILINE).group(1)
    if re.search(r"^Stable tag: (.+)$", readme, re.MULTILINE).group(1) != version:
        raise RuntimeError("Plugin version and readme stable tag must agree.")
    files = runtime_files + [path.relative_to(root).as_posix() for path in (root / "languages").rglob("*") if path.is_file() and path.suffix in {".pot", ".po", ".mo", ".json"}]
    for name in files:
        path = root / name
        if not path.is_file() or path.is_symlink() or root not in path.resolve().parents:
            raise RuntimeError(f"Missing or unsafe distribution file: {name}")
    dist = root / "dist"
    dist.mkdir(exist_ok=True)
    archive_path = dist / f"{slug}-{version}.zip"
    with zipfile.ZipFile(archive_path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name in sorted(files):
            entry = zipfile.ZipInfo(f"{slug}/{name}", date_time=(2000, 1, 1, 0, 0, 0))
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            entry.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(entry, (root / name).read_bytes(), compresslevel=9)
    with zipfile.ZipFile(archive_path) as archive:
        if archive.testzip() is not None or set(archive.namelist()) != {f"{slug}/{name}" for name in files}:
            raise RuntimeError("Release archive integrity check failed.")
        for name in files:
            if archive.read(f"{slug}/{name}") != (root / name).read_bytes():
                raise RuntimeError(f"Release archive differs from source: {name}")
    checksum = hashlib.sha256(archive_path.read_bytes()).hexdigest()
    (dist / f"{archive_path.name}.sha256").write_text(f"{checksum}  {archive_path.name}\n", encoding="utf-8")
    print(f"{archive_path}\nSHA-256: {checksum}\n{len(files)} files; {archive_path.stat().st_size} bytes; archive integrity and source bytes verified.")
    return archive_path


if __name__ == "__main__":
    build()
