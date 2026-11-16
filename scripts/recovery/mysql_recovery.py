#!/usr/bin/env python3
"""Planner MySQL logical backup, checksum verification and disposable restore rehearsal.

Database-only: excludes Tetranyble Storage objects, private media and credential KMS keys.
Output is a LOCAL plaintext gzip with mode 0600, not a production off-site archive.
Encryption / object snapshot / retention are separate mandatory operations.
"""
from __future__ import annotations
import argparse
import datetime as dt
import gzip
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys
import tempfile

DATABASE = re.compile(r"^[A-Za-z][A-Za-z0-9_]{0,63}$")
MANIFEST_VERSION = "planner-mysql-backup-v1"
CHUNK = 1024 * 1024


class UnsafeRecovery(RuntimeError):
    """Release precondition was not satisfied; messages never include credentials."""


def safe_name(value: str) -> str:
    if not DATABASE.fullmatch(value):
        raise UnsafeRecovery("Database name must be an unquoted MySQL identifier (letters/digits/underscore).")
    return value


def secrets_file(path: str) -> Path:
    config = Path(path).absolute()
    if config.is_symlink() or not config.is_file():
        raise UnsafeRecovery("MySQL client option file must be a regular, non-symlink file.")
    permissions = stat.S_IMODE(config.stat().st_mode)
    if permissions & (stat.S_IRWXG | stat.S_IRWXO):
        raise UnsafeRecovery("MySQL client option file permissions must be owner-only (chmod 600).")
    return config


def checksum(path: Path) -> str:
    hasher = hashlib.sha256()
    with path.open("rb") as f:
        for block in iter(lambda: f.read(CHUNK), b""):
            hasher.update(block)
    return hasher.hexdigest()


def read_manifest(source: Path) -> dict:
    if source.is_symlink() or not source.is_file() or source.suffixes[-2:] != [".sql", ".gz"]:
        raise UnsafeRecovery("Backup must be an existing .sql.gz regular file, not a symlink.")
    meta = Path(str(source) + ".manifest.json")
    if meta.is_symlink() or not meta.is_file():
        raise UnsafeRecovery("Checksum manifest missing or is a symlink.")
    try:
        document = json.loads(meta.read_text())
    except (ValueError, UnicodeError) as e:
        raise UnsafeRecovery("Backup manifest cannot be read.") from e
    if not isinstance(document, dict) or document.get("format") != MANIFEST_VERSION:
        raise UnsafeRecovery("Unknown backup manifest format.")
    if document.get("compression") != "gzip" or document.get("encrypted") is not False:
        raise UnsafeRecovery("Unexpected backup compression or encryption metadata.")
    if document.get("database") is None or safe_name(document["database"]) != document["database"]:
        raise UnsafeRecovery("Backup source database missing from manifest.")
    if document.get("sha256") != checksum(source) or document.get("bytes") != source.stat().st_size:
        raise UnsafeRecovery("Backup SHA-256 or length does not match manifest; restore prohibited.")
    return document


def executable(name: str) -> str:
    binary = shutil.which(name)
    if not binary:
        raise UnsafeRecovery(f"Required MySQL client binary is unavailable: {name}.")
    return binary


def save_backup(args: argparse.Namespace) -> None:
    db = safe_name(args.database)
    config = secrets_file(args.option_file)
    destination = Path(args.output).absolute()
    if destination.suffixes[-2:] != [".sql", ".gz"]:
        raise UnsafeRecovery("Backup output must end with .sql.gz.")
    if destination.exists() or destination.is_symlink() or Path(str(destination) + ".manifest.json").exists():
        raise UnsafeRecovery("Backup output and manifest must not already exist.")
    if not destination.parent.is_dir():
        raise UnsafeRecovery("Backup output directory must already exist.")
    dump = executable("mysqldump")
    command = [dump, f"--defaults-extra-file={config}", "--single-transaction", "--quick",
        "--skip-lock-tables", "--routines", "--triggers", "--events", "--hex-blob",
        "--no-tablespaces", "--set-gtid-purged=OFF", db]
    fd, tempname = tempfile.mkstemp(prefix=".planner-backup-", suffix=".tmp", dir=destination.parent)
    os.fchmod(fd, 0o600)
    try:
        with os.fdopen(fd, "wb") as output:
            process = subprocess.Popen(command, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL)
            assert process.stdout is not None
            with gzip.GzipFile(filename="", mode="wb", fileobj=output, mtime=0) as compressed:
                for part in iter(lambda: process.stdout.read(CHUNK), b""):
                    compressed.write(part)
            process.stdout.close()
            if process.wait() != 0:
                raise UnsafeRecovery("mysqldump failed; incomplete backup has been discarded.")
            output.flush()
            os.fsync(output.fileno())
        temporary = Path(tempname)
        fingerprint = checksum(temporary)
        details = {"format": MANIFEST_VERSION, "database": db, "sha256": fingerprint,
            "bytes": temporary.stat().st_size, "compression": "gzip", "encrypted": False,
            "contains_storage_objects": False,
            "created_at_utc": dt.datetime.now(dt.timezone.utc).isoformat()}
        # Manifest is written before final backup rename; incomplete work can be safely removed.
        manifest = Path(str(destination) + ".manifest.json")
        mf, tmpmanifest = tempfile.mkstemp(prefix=".planner-manifest-", dir=destination.parent)
        os.fchmod(mf, 0o600)
        try:
            with os.fdopen(mf, "w") as stream:
                json.dump(details, stream, sort_keys=True)
                stream.flush()
                os.fsync(stream.fileno())
            os.replace(temporary, destination)
            os.replace(tmpmanifest, manifest)
        finally:
            Path(tmpmanifest).unlink(missing_ok=True)
        print(json.dumps({"status": "BACKUP_CREATED_UNENCRYPTED_LOCAL_ONLY", "path": str(destination),
            "sha256": fingerprint, "bytes": details["bytes"], "encrypted": False,
            "storage_objects_included": False}))
    finally:
        Path(tempname).unlink(missing_ok=True)


def verify_backup(args: argparse.Namespace) -> None:
    path = Path(args.input).absolute()
    manifest = read_manifest(path)
    # Test compressed-stream integrity too, independently of the outer SHA-256.
    try:
        with gzip.open(path, "rb") as backup:
            while backup.read(CHUNK):
                pass
    except (OSError, EOFError, gzip.BadGzipFile) as e:
        raise UnsafeRecovery("Compressed SQL stream is invalid; restore prohibited.") from e
    print(json.dumps({"status": "CHECKSUM_AND_GZIP_VERIFIED", "source_database": manifest["database"],
        "sha256": manifest["sha256"], "encrypted": False,
        "restore_tested": False, "storage_objects_included": False}))


def mysql_tables(mysql: str, config: Path, target: str) -> list[str]:
    check = subprocess.run([mysql, f"--defaults-extra-file={config}", f"--database={target}",
        "--batch", "--skip-column-names", "--execute=SHOW TABLES"], capture_output=True, check=False)
    if check.returncode != 0:
        raise UnsafeRecovery("Disposable restore target is inaccessible or does not exist.")
    return check.stdout.decode("utf-8", errors="replace").splitlines()


def rehearse_restore(args: argparse.Namespace) -> None:
    source = Path(args.input).absolute()
    document = read_manifest(source)
    # Do not use a production database name even if the caller explicitly opts in.
    target = safe_name(args.target_database)
    if not target.startswith("planner_restore_") or target == document["database"]:
        raise UnsafeRecovery("Restore target must be a separately created planner_restore_* disposable database.")
    if not args.acknowledge_disposable_target:
        raise UnsafeRecovery("--acknowledge-disposable-target is required; no implicit destructive restore.")
    config = secrets_file(args.option_file)
    mysql = executable("mysql")
    if mysql_tables(mysql, config, target):
        raise UnsafeRecovery("Disposable restore database contains tables; refusing to overwrite any records.")
    # The backup contains schema + data SQL only; target DB is specified explicitly.
    process = subprocess.Popen([mysql, f"--defaults-extra-file={config}", f"--database={target}",
        "--binary-mode"], stdin=subprocess.PIPE, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    assert process.stdin is not None
    try:
        with gzip.open(source, "rb") as backup:
            for block in iter(lambda: backup.read(CHUNK), b""):
                process.stdin.write(block)
        process.stdin.close()
        if process.wait() != 0:
            raise UnsafeRecovery("Disposable restore failed; inspect the isolated target and do not promote the backup.")
    except (BrokenPipeError, OSError, EOFError) as error:
        process.kill()
        process.wait()
        raise UnsafeRecovery("Disposable SQL stream restore failed.") from error
    table_count = len(mysql_tables(mysql, config, target))
    if table_count == 0:
        raise UnsafeRecovery("Restore completed but created no tables; test failed.")
    print(json.dumps({"status": "DISPOSABLE_RESTORE_REHEARSED", "source_database": document["database"],
        "target_database": target, "restored_tables": table_count,
        "sha256": document["sha256"], "storage_objects_included": False,
        "application_queries_verified": False, "external_media_verified": False}))


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest="action", required=True)
    backup = sub.add_parser("backup", help="Make local 0600 unencrypted gzip MySQL 8 logical backup")
    backup.add_argument("--option-file", required=True)
    backup.add_argument("--database", required=True)
    backup.add_argument("--output", required=True)
    check = sub.add_parser("verify", help="Verify checksum and gzip stream; never touches MySQL")
    check.add_argument("--input", required=True)
    restore = sub.add_parser("rehearse", help="Restore only to empty disposable planner_restore_* database")
    restore.add_argument("--option-file", required=True)
    restore.add_argument("--input", required=True)
    restore.add_argument("--target-database", required=True)
    restore.add_argument("--acknowledge-disposable-target", action="store_true")
    args = parser.parse_args()
    try:
        {"backup": save_backup, "verify": verify_backup, "rehearse": rehearse_restore}[args.action](args)
        return 0
    except UnsafeRecovery as error:
        print(json.dumps({"status": "BLOCKED", "reason": str(error)}), file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
