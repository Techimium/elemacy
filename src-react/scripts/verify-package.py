#!/usr/bin/env python3
"""Verify a generated archive against its retained manifest, its runtime exclusions and the release gate.

Beyond the archive itself this checks that the plugin header version, the runtime version constant and
(for Free) the readme stable tag agree, that the readme's tested-up-to value matches the WordPress version
CI pins, that the readme changelog has a section for this version, that the WordPress.org listing assets
the deploy workflow publishes exist, and that every shipped file carrying a third-party license header is
covered by docs/THIRD-PARTY.md. `--root` points the source-level checks at another plugin root (used to
exercise deliberately broken inputs); the archive is always checked as given.
"""
import argparse
import hashlib
import json
import os
import re
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import zipfile

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('archive', type=Path)
parser.add_argument('--check-missing-tools', action='store_true')
parser.add_argument('--root', type=Path, default=Path(__file__).resolve().parent.parent.parent, help='Plugin root for the source-level release checks.')
args = parser.parse_args()
archive = args.archive.resolve()
root = args.root.resolve()
problems = []

def fail(message):
    problems.append(message)

evidence = json.loads(Path(str(archive) + '.manifest.json').read_text())
slug = evidence['slug']
free = slug == 'elemacy'
LICENSE_HEADER = re.compile(rb'@license|Licensed under|MIT License|Copyright \(c\)')

with zipfile.ZipFile(archive) as package:
    names = package.namelist()
    if len(names) != len(evidence['files']):
        fail('Archive file count differs from manifest.')
    for entry in evidence['files']:
        if hashlib.sha256(package.read(slug + '/' + entry['path'])).hexdigest() != entry['sha256']:
            fail('Checksum mismatch: ' + entry['path'])
    for name in names:
        if any(part in name for part in ['/Animations/', '/animations/', 'AnimationsPlaceholder', '/node_modules/', '/tests/', '/src-react/', '/.git/']):
            fail('Excluded path shipped: ' + name)
        if name.endswith('.map'):
            fail('Source map shipped: ' + name)
    licensed = [n[len(slug) + 1:] for n in names if n.endswith(('.js', '.css', '.php')) and LICENSE_HEADER.search(package.read(n))]
if hashlib.sha256(archive.read_bytes()).hexdigest() != evidence['zipSha256']:
    fail('Archive checksum mismatch.')

# --- version consistency -------------------------------------------------------
main_file = root / f'{slug}.php'
main = main_file.read_text() if main_file.exists() else ''
header_version = (re.search(r'^\s*\*\s*Version:\s*(\S+)\s*$', main, re.M) or [None, None])[1]
constant = 'ELEMACY_VERSION' if free else 'ELEMACY_PRO_VERSION'
constant_version = (re.search(r"define\(\s*'" + constant + r"'\s*,\s*'([^']+)'", main) or [None, None])[1]
versions = {'plugin header Version': header_version, constant: constant_version, 'archive manifest version': evidence.get('version')}
readme = (root / 'readme.txt').read_text() if (root / 'readme.txt').exists() else ''
if free:
    versions['readme Stable tag'] = (re.search(r'^Stable tag:\s*(\S+)', readme, re.M) or [None, None])[1]
if len({v for v in versions.values()}) != 1 or None in versions.values():
    fail('Versions disagree: ' + ', '.join(f'{k}={v}' for k, v in versions.items()))

if free:
    # --- readme: tested up to, changelog ----------------------------------------
    ci = (root / '.github/workflows/ci.yml').read_text() if (root / '.github/workflows/ci.yml').exists() else ''
    pinned = sorted(set(re.findall(r'wp core download[^\n]*--version=([0-9.]+)', ci)))
    tested = (re.search(r'^Tested up to:\s*(\S+)', readme, re.M) or [None, None])[1]
    if not pinned:
        fail('CI pins no WordPress version (wp core download --version=...).')
    elif tested not in pinned:
        fail(f'readme "Tested up to: {tested}" does not match the WordPress version CI pins ({", ".join(pinned)}).')
    short = readme.split('\n\n')[1].strip() if readme.count('\n\n') else ''
    if len(short) > 150:
        fail(f'readme short description is {len(short)} characters; WordPress.org allows 150.')
    tags = (re.search(r'^Tags:\s*(.+)$', readme, re.M) or [None, ''])[1]
    if len([t for t in tags.split(',') if t.strip()]) > 5:
        fail('readme lists more than five tags.')
    version = header_version or ''
    if not re.search(r'^= ' + re.escape(version) + r' =\s*$', readme, re.M):
        fail(f'readme changelog has no "= {version} =" section.')
    # --- listing assets the deploy workflow publishes ---------------------------
    deploy = (root / '.github/workflows/deploy-wordpress-plugin.yml').read_text() if (root / '.github/workflows/deploy-wordpress-plugin.yml').exists() else ''
    assets_dir = (re.search(r'ASSETS_DIR:\s*(\S+)', deploy) or [None, None])[1]
    if assets_dir:
        for asset in ['banner-772x250.png', 'banner-1544x500.png', 'icon-128x128.png', 'icon-256x256.png']:
            if not (root / assets_dir / asset).is_file():
                fail(f'Listing asset missing: {assets_dir}/{asset}')

# --- third-party inventory -------------------------------------------------------
inventory_file = root / 'docs/THIRD-PARTY.md' if free else root.parent / 'elemacy' / 'docs/THIRD-PARTY.md'
inventory = inventory_file.read_text() if inventory_file.exists() else ''
if not inventory:
    fail(f'Third-party inventory not found at {inventory_file}.')
for path in licensed:
    if f'`{path}`' not in inventory and f'`{slug}/{path}`' not in inventory:
        fail(f'Shipped file carries a third-party license header but is not listed in docs/THIRD-PARTY.md: {path}')

if args.check_missing_tools:
    script = Path(__file__).with_name('build-zip.mjs')
    node = shutil.which('node')
    if not node:
        fail('Node is required for the missing-tool regression.')
    else:
        with tempfile.TemporaryDirectory(prefix='elemacy-missing-tools-') as empty:
            result = subprocess.run([node, str(script)], env={**os.environ, 'PATH': empty}, capture_output=True, text=True)
            if result.returncode == 0 or 'Required command failed: composer' not in result.stderr:
                fail('build-zip.mjs did not fail explicitly without Composer on PATH.')

if problems:
    print(f'{slug}: release gate FAILED', file=sys.stderr)
    for problem in problems:
        print(' - ' + problem, file=sys.stderr)
    sys.exit(1)
print(f'{slug}: {len(names)} files verified; checksum, exclusions, versions, readme, listing assets and third-party inventory all pass.')
