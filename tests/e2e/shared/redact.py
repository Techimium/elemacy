#!/usr/bin/env python3
"""Remove known native session credentials/nonces from retained text and traces."""
import json
import base64
import io
import re
from pathlib import Path
import sys
import urllib.parse
import zipfile

root = Path(sys.argv[1]).resolve()
secrets_file = root / '.capture-secrets.jsonl'
values = {json.loads(line) for line in secrets_file.read_text().splitlines() if line} if secrets_file.exists() else set()
values |= {urllib.parse.quote(value, safe='') for value in values}
needles = sorted((value.encode() for value in values if value), key=len, reverse=True)


def redact(content):
    for value in needles:
        content = content.replace(value, b'[REDACTED]')
    # WordPress action links and hidden form fields also contain short-lived
    # nonces that may never be submitted, so request-header collection misses them.
    content = re.sub(rb'((?:_wpnonce|_ajax_nonce|_nonce|nonce)(?:=|%3D|[\\"\s]*:[\\"\s]*)["\x27]?)([a-f0-9]{10})(?![a-f0-9])',
                     rb'\1[REDACTED]', content, flags=re.I)
    return content


def redact_zip(content):
    result = io.BytesIO()
    with zipfile.ZipFile(io.BytesIO(content)) as original, zipfile.ZipFile(result, 'w') as output:
        for entry in original.infolist():
            data = original.read(entry.filename)
            if not entry.filename.endswith(('.png', '.jpg', '.jpeg', '.webp')):
                data = redact(data)
            output.writestr(entry, data)
    return result.getvalue()


def redact_html(content):
    # Playwright embeds a ZIP of report JSON as base64. Raw text replacement
    # cannot redact the encoded request URLs inside that report.
    def embedded(match):
        return match[1] + base64.b64encode(redact_zip(base64.b64decode(match[2])))
    return re.sub(rb'(data:application/zip;base64,)([A-Za-z0-9+/=]+)', embedded, redact(content))


for path in root.rglob('*'):
    if not path.is_file() or path == secrets_file:
        continue
    if path.suffix == '.zip':
        temporary = path.with_suffix('.redacted.zip')
        temporary.write_bytes(redact_zip(path.read_bytes()))
        temporary.replace(path)
    elif path.suffix == '.html':
        path.write_bytes(redact_html(path.read_bytes()))
    elif path.suffix in ['.json', '.jsonl', '.xml', '.md', '.txt', '.log', '.trace', '.network']:
        path.write_bytes(redact(path.read_bytes()))
secrets_file.unlink(missing_ok=True)
