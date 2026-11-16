"""Dependency-free safety tests for the MySQL backup/restore rehearsal CLI."""
import gzip
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

SCRIPT = Path(__file__).resolve().parents[2] / "scripts/recovery/mysql_recovery.py"

class RecoverySafetyTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.dbopt = self.root / "mysql.cnf"
        self.dbopt.write_text("[client]\nuser=synthetic\n")
        self.dbopt.chmod(0o600)
        self.bin = self.root / "bin"
        self.bin.mkdir()
        self.backup = self.root / "backup.sql.gz"
        self.manifest = Path(str(self.backup)+".manifest.json")
        self.env = {**os.environ,"PATH":str(self.bin)+os.pathsep+os.environ.get("PATH","")}

    def invoke(self, *args):
        return subprocess.run([sys.executable, str(SCRIPT),*args], cwd=self.root,
                              env=self.env, capture_output=True, text=True)

    def fixture(self):
        with gzip.open(self.backup,'wb') as output: output.write(b'CREATE TABLE proof (id int);\n')
        self.manifest.write_text(json.dumps({
            'format':'planner-mysql-backup-v1','database':'planner_src',
            'compression':'gzip','encrypted':False,'bytes':self.backup.stat().st_size,
            'sha256':hashlib.sha256(self.backup.read_bytes()).hexdigest(),
        }))

    def test_invalid_database_name_refused_before_dump(self):
        response = self.invoke('backup','--option-file',str(self.dbopt),
            '--database','planner;DROP DATABASE prod','--output',str(self.backup))
        self.assertEqual(2,response.returncode)
        self.assertFalse(self.backup.exists())

    def test_insecure_client_file_refused(self):
        self.dbopt.chmod(0o644)
        response = self.invoke('backup','--option-file',str(self.dbopt),
            '--database','planner_src','--output',str(self.backup))
        self.assertEqual(2,response.returncode)
        self.assertIn('permissions',response.stderr)

    def test_checksum_mismatch_refuses_restore(self):
        self.fixture()
        self.backup.write_bytes(self.backup.read_bytes()+b'corrupted')
        response = self.invoke('rehearse','--option-file',str(self.dbopt),
            '--input',str(self.backup),'--target-database','planner_restore_test',
            '--acknowledge-disposable-target')
        self.assertEqual(2,response.returncode)
        self.assertIn('SHA-256',response.stderr)

    def test_refuses_restore_to_any_non_scratch_database(self):
        self.fixture()
        for target in ['production','planner_src','planner_restore_../oops']:
            response = self.invoke('rehearse','--option-file',str(self.dbopt),
                '--input',str(self.backup),'--target-database',target,
                '--acknowledge-disposable-target')
            self.assertEqual(2,response.returncode,target)

    def test_valid_checksum_does_not_claim_restore(self):
        self.fixture()
        response = self.invoke('verify','--input',str(self.backup))
        self.assertEqual(0,response.returncode,response.stderr)
        self.assertFalse(json.loads(response.stdout)['restore_tested'])

    def test_empty_scratch_rehearsal_with_fake_mysql_client(self):
        self.fixture()
        marker=self.root/'replayed.sql'
        binary=self.bin/'mysql'
        # Fake mysql: first SHOW TABLES is empty; after write, it lists proof.
        binary.write_text('#!/usr/bin/env python3\nimport pathlib,sys\n'
            f'p=pathlib.Path({str(marker)!r})\n'
            'if any("--execute=SHOW TABLES" in a for a in sys.argv):\n'
            '    print("proof" if p.exists() else "",end="")\n'
            'else: p.write_bytes(sys.stdin.buffer.read())\n')
        binary.chmod(0o700)
        response=self.invoke('rehearse','--option-file',str(self.dbopt),
            '--input',str(self.backup),'--target-database','planner_restore_qa',
            '--acknowledge-disposable-target')
        self.assertEqual(0,response.returncode,response.stderr)
        self.assertIn(b'CREATE TABLE proof',marker.read_bytes())
        self.assertEqual(json.loads(response.stdout)['status'],'DISPOSABLE_RESTORE_REHEARSED')
        another=self.invoke('rehearse','--option-file',str(self.dbopt),
            '--input',str(self.backup),'--target-database','planner_restore_qa',
            '--acknowledge-disposable-target')
        self.assertEqual(2,another.returncode)
        self.assertIn('contains tables',another.stderr)

if __name__=='__main__': unittest.main()
