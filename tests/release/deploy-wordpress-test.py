#!/usr/bin/env python3
"""Exercise the deployment script against an isolated file:// SVN repository; never contact WordPress.org."""
import hashlib
import os
from pathlib import Path
import shlex
import shutil
import subprocess
import tempfile

root = Path(__file__).resolve().parents[2]
script = root / '.github/scripts/deploy-wordpress.sh'
for tool in ('svn', 'svnadmin', 'rsync'):
    if not shutil.which(tool):
        raise SystemExit(f'Required regression tool missing: {tool}')


def run(*command, **kwargs):
    return subprocess.run(command, check=True, capture_output=True, text=True, **kwargs)


def files(directory):
    return {str(p.relative_to(directory)): hashlib.sha256(p.read_bytes()).hexdigest()
            for p in directory.rglob('*') if p.is_file()}


with tempfile.TemporaryDirectory(prefix='elemacy-svn-release-') as temporary:
    base = Path(temporary)
    repository = base / 'svn-repo'
    run('svnadmin', 'create', str(repository))
    url = repository.as_uri()
    seed = base / 'seed'
    for name in ('trunk/tests', 'trunk/stale folder', 'assets', 'tags/0.9.0'):
        (seed / name).mkdir(parents=True)
    for name in ('trunk/tests/unused.php', 'trunk/stale folder/deleted@file.php', 'assets/old-banner.png', 'tags/0.9.0/keep.php'):
        (seed / name).write_text('old content')
    run('svn', 'import', str(seed), url, '-m', 'Seed release fixture', '--non-interactive')

    distribution = base / 'verified production'
    for name in ('elemacy.php', 'readme.txt', 'vendor/autoload.php', 'assets/new folder/name@2.js'):
        destination = distribution / name
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_text('production ' + name)
    listing = base / 'wp-assets'
    listing.mkdir()
    (listing / 'icon.svg').write_text('<svg/>')
    (listing / 'screenshot-1.png').write_bytes(b'listing screenshot')
    (base / 'development-secret.env').write_text('must never ship')

    environment = {**os.environ, 'SVN_URL': url, 'PLUGIN_VERSION': '1.0.0',
                   'DISTRIBUTION_DIR': str(distribution), 'ASSETS_DIR': str(listing),
                   'DRY_RUN': 'true', 'WPORG_USERNAME': 'local-test', 'WPORG_PASSWORD': 'local-test'}

    def revision():
        return run('svnlook', 'youngest', str(repository)).stdout.strip()

    def deploy(**overrides):
        return subprocess.run(['bash', str(script)], cwd=base, env={**environment, **overrides}, capture_output=True, text=True)

    before = revision()
    result = deploy()
    assert result.returncode == 0, result.stdout + result.stderr
    assert 'Dry run passed' in result.stdout and revision() == before
    print('PASS  dry run stages changes without committing')

    result = deploy(DRY_RUN='false')
    assert result.returncode == 0, result.stdout + result.stderr
    assert int(revision()) == int(before) + 1, 'Release must use one atomic commit'
    exported = base / 'exported'
    run('svn', 'export', url, str(exported))
    assert files(exported / 'trunk') == files(distribution)
    assert files(exported / 'tags/1.0.0') == files(distribution)
    assert files(exported / 'assets') == files(listing)
    assert files(exported / 'tags/0.9.0') == files(seed / 'tags/0.9.0')
    print('PASS  one commit publishes exact production trunk/tag and separate listing assets')
    print('PASS  stale files removed, historical tag preserved, spaces and @ paths handled')

    cases = [
        ({}, 'already exists'),
        ({'PLUGIN_VERSION': '0.8.0'}, 'would downgrade'),
        ({'PLUGIN_VERSION': '1.0.1-rc.1'}, 'Invalid release version'),
        ({'PLUGIN_VERSION': '1.0.1', 'ASSETS_DIR': str(base / 'absent')}, 'Release inputs missing'),
        ({'PLUGIN_VERSION': '1.0.1', 'DRY_RUN': 'false', 'WPORG_PASSWORD': ''}, 'Missing WordPress.org SVN password'),
        ({'PLUGIN_VERSION': '1.0.1', 'DRY_RUN': 'invalid'}, 'Invalid DRY_RUN'),
    ]
    for overrides, message in cases:
        before = revision()
        result = deploy(**overrides)
        assert result.returncode != 0 and message in result.stderr, result.stdout + result.stderr
        assert revision() == before
        print('PASS  rejected safely: ' + message)

    # A real SVN command failure must not be swallowed by status parsing or a pipeline.
    wrapper = base / 'bin'
    wrapper.mkdir()
    real_svn = shutil.which('svn')
    (wrapper / 'svn').write_text('#!/bin/bash\nif [[ "$1" == add ]]; then echo "injected staging failure" >&2; exit 23; fi\nexec ' + shlex.quote(real_svn) + ' "$@"\n')
    (wrapper / 'svn').chmod(0o755)
    (distribution / 'new-file.php').write_text('<?php // new file')
    before = revision()
    result = deploy(PLUGIN_VERSION='1.0.1', DRY_RUN='false', PATH=str(wrapper) + os.pathsep + os.environ['PATH'])
    assert result.returncode != 0 and 'injected staging failure' in result.stderr, result.stdout + result.stderr
    assert revision() == before
    print('PASS  SVN staging failure aborts before any commit')
