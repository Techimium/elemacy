#!/usr/bin/env python3
"""Verify a generated archive against its retained file manifest and runtime exclusions."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import zipfile

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('archive', type=Path)
parser.add_argument('--check-missing-tools', action='store_true')
args = parser.parse_args()
archive = args.archive.resolve()
evidence = json.loads(Path(str(archive) + '.manifest.json').read_text())
slug = evidence['slug']
with zipfile.ZipFile(archive) as package:
    names = package.namelist()
    assert len(names) == len(evidence['files']), 'Archive file count differs from manifest.'
    for entry in evidence['files']:
        assert hashlib.sha256(package.read(slug + '/' + entry['path'])).hexdigest() == entry['sha256'], entry['path']
    for name in names:
        assert not any(part in name for part in ['/Animations/', '/animations/', 'AnimationsPlaceholder', '/node_modules/', '/tests/', '/src-react/', '/.git/']), name
        assert not name.endswith('.map'), name
assert hashlib.sha256(archive.read_bytes()).hexdigest() == evidence['zipSha256'], 'Archive checksum mismatch.'
if args.check_missing_tools:
    script = Path(__file__).with_name('build-zip.mjs')
    node = shutil.which('node')
    assert node, 'Node is required for the missing-tool regression.'
    with tempfile.TemporaryDirectory(prefix='elemacy-missing-tools-') as empty:
        result = subprocess.run([node, str(script)], env={**os.environ, 'PATH': empty}, capture_output=True, text=True)
        assert result.returncode != 0 and 'Required command failed: composer' in result.stderr, result.stderr
print(f'{slug}: {len(names)} files verified; archive checksum and exclusions passed.')
