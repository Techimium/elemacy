#!/usr/bin/env python3
"""Regression cases for the production archive, source-revision and version-tag release gates.

Each case builds a small synthetic archive with its manifest (the real plugin root supplies the version,
readme and inventory checks, which pass) and asserts the gate's verdict. Run from the plugin root:
python3 tests/release/verify-package-test.py
"""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import zipfile

root = Path(__file__).resolve().parent.parent.parent
script = root / 'src-react/scripts/verify-package.py'
version = next(line.split(':', 1)[1].strip() for line in (root / 'elemacy.php').read_text().splitlines() if line.strip().startswith('* Version:'))
head = subprocess.run(['git', '-C', str(root), 'rev-parse', 'HEAD'], capture_output=True, text=True, check=True).stdout.strip()
parent = subprocess.run(['git', '-C', str(root), 'rev-parse', 'HEAD~1'], capture_output=True, text=True, check=True).stdout.strip()


def verify(files, revision, dirty, tag=None):
    with tempfile.TemporaryDirectory(prefix='elemacy-verify-package-') as directory:
        archive = Path(directory) / f'elemacy-{version}.zip'
        with zipfile.ZipFile(archive, 'w') as package:
            for name, content in files.items():
                package.writestr('elemacy/' + name, content)
        evidence = {
            'slug': 'elemacy', 'version': version, 'revision': revision, 'dirty': dirty,
            'zipSha256': hashlib.sha256(archive.read_bytes()).hexdigest(),
            'files': [{'path': name, 'sha256': hashlib.sha256(content.encode()).hexdigest()} for name, content in files.items()],
        }
        Path(str(archive) + '.manifest.json').write_text(json.dumps(evidence))
        command = [sys.executable, str(script), str(archive), '--release-revision', head]
        if tag:
            command += ['--tag', tag]
        return subprocess.run(command, capture_output=True, text=True)


rules = json.loads((root / 'src-react/scripts/package-manifest.json').read_text())
shipped = {name: '<?php\n' if name.endswith('.php') else '\n' for name in rules['requiredFiles'] + rules['requiredRuntime']}
shipped.update({'vendor/autoload.php': '<?php\n', 'composer.json': '{}\n'})
cases = [
    ('an artifact built cleanly from the release revision passes', shipped, head, False, None),
    ('an artifact built from an older revision is rejected as stale', shipped, parent, False, 'Archive is stale'),
    ('an artifact built from a dirty tree is rejected', shipped, head, True, 'uncommitted changes'),
    ('vendor/ without composer.json is rejected', {name: content for name, content in shipped.items() if name != 'composer.json'}, head, False, 'without the composer.json'),
    ('an incomplete production build is rejected', {name: content for name, content in shipped.items() if name != 'assets/admin/scripts/admin.js'}, head, False, 'Required production file missing'),
]
for unwanted in ['wp-assets/icon.svg', '.wordpress-org/icon.png', 'docs/RELEASE.md', '.env', 'src-react/package.json', 'tests/test.php', 'assets/admin/scripts/admin.js.map', 'src/../secret.php', '/tmp/secret.php']:
    cases.append((f'{unwanted} is rejected', {**shipped, unwanted: 'unneeded'}, head, False,
                  'Unsafe archive path' if '..' in unwanted or unwanted.startswith('.') else 'production allowlist'))

failures = 0
for label, files, revision, dirty, expected in cases:
    result = verify(files, revision, dirty)
    passed = result.returncode == 0 if expected is None else (result.returncode == 1 and expected in result.stderr)
    failures += not passed
    print(('PASS  ' if passed else 'FAIL  ') + label + ('' if passed else '\n' + result.stdout + result.stderr))

tag_cases = [('v' + version, None), ('v9.9.9', 'does not match'), ('v' + version + '-rc.1', 'does not match')]
for tag, expected in tag_cases:
    result = verify(shipped, head, False, tag)
    passed = result.returncode == 0 if expected is None else result.returncode == 1 and expected in result.stderr
    failures += not passed
    print(('PASS  ' if passed else 'FAIL  ') + f'release tag {tag}' + ('' if passed else '\n' + result.stdout + result.stderr))
print(f'{len(cases) + len(tag_cases) - failures}/{len(cases) + len(tag_cases)} passed')
sys.exit(1 if failures else 0)
