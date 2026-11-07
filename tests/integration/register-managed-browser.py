#!/usr/bin/env python3
"""Explicitly register a disposable wp-site-manager site with installed release ZIPs.

Does not create/delete sites or read database/admin passwords. Never targets elemacy.test.
"""
import argparse
import hashlib
import json
from pathlib import Path
import re
import secrets
import subprocess
import tempfile
import zipfile


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--domain', required=True)
    parser.add_argument('--free-zip', type=Path, required=True)
    parser.add_argument('--pro-zip', type=Path)
    parser.add_argument('--confirm-disposable-site', action='store_true')
    args = parser.parse_args()
    if not args.confirm_disposable_site or not re.fullmatch(r'elemacy-release(?:-[a-z0-9-]+)?\.test', args.domain):
        parser.error('Explicit disposable-site confirmation and an elemacy-release*.test domain are required.')
    root = Path('/var/www') / args.domain
    if root.resolve() != root or not (root / 'wp-config.php').is_file():
        parser.error('Expected a real wp-site-manager root with wp-config.php.')

    def wp_eval(code):
        return subprocess.check_output(['wp', 'eval', code, '--skip-plugins', '--skip-themes', '--path=' + str(root)], text=True).strip()

    identity = json.loads(wp_eval('echo wp_json_encode(["database"=>DB_NAME,"url"=>get_option("siteurl"),"token"=>get_option("elemacy_integration_environment")]);'))
    if identity['database'] != args.domain.replace('.', '_').replace('-', '_') or identity['url'] != 'http://' + args.domain or identity['token']:
        parser.error('Unexpected database/URL or already registered site; use its existing manifest.')
    packages, installed = {}, {}
    for slug, archive_path in [('elemacy', args.free_zip), ('elemacy-pro', args.pro_zip)]:
        if archive_path is None:
            continue
        archive_path = archive_path.resolve()
        with zipfile.ZipFile(archive_path) as archive:
            for entry in archive.infolist():
                if entry.is_dir():
                    continue
                member = Path(entry.filename)
                if member.parts[0] != slug or '..' in member.parts or member.is_absolute():
                    parser.error('Unsafe ZIP member.')
                target = root / 'wp-content/plugins' / member
                digest = hashlib.sha256(archive.read(entry)).hexdigest()
                if target.resolve() != target or not target.is_file() or hashlib.sha256(target.read_bytes()).hexdigest() != digest:
                    parser.error('Installed file does not match release ZIP: ' + entry.filename)
                installed[entry.filename] = digest
        packages[slug] = {'archive': str(archive_path), 'sha256': hashlib.sha256(archive_path.read_bytes()).hexdigest()}
    token = secrets.token_hex(8)
    directory = Path(tempfile.gettempdir()) / ('elemacy-browser-' + token)
    directory.mkdir(mode=0o700)
    environment = {'purpose': 'elemacy-managed-browser-v1', 'transport': 'external', 'token': token,
                   'root': str(root), 'url': identity['url'], 'database': identity['database'],
                   'wp_config_sha256': hashlib.sha256((root / 'wp-config.php').read_bytes()).hexdigest(),
                   'packages': packages, 'installed_files': installed}
    manifest = directory / 'environment.json'
    manifest.write_text(json.dumps(environment, indent=2) + '\n')
    manifest.chmod(0o600)
    wp_eval('update_option("elemacy_integration_environment", "' + token + '");')
    print(str(manifest))


if __name__ == '__main__':
    main()
