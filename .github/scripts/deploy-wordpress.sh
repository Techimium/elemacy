#!/usr/bin/env bash
# Publish only the verified distribution and the separate WordPress.org listing assets.
set -euo pipefail

: "${PLUGIN_VERSION:?Missing release version}"
: "${SVN_URL:?Missing SVN repository URL}"
: "${DISTRIBUTION_DIR:?Missing verified distribution directory}"
: "${ASSETS_DIR:?Missing listing assets directory}"
DRY_RUN=${DRY_RUN:-true}
[[ "$DRY_RUN" == true || "$DRY_RUN" == false ]] || { echo 'Invalid DRY_RUN value' >&2; exit 1; }
[[ "$PLUGIN_VERSION" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]] || { echo 'Invalid release version' >&2; exit 1; }
[[ -f "$DISTRIBUTION_DIR/elemacy.php" && -d "$ASSETS_DIR" ]] || { echo 'Release inputs missing' >&2; exit 1; }

auth=(--non-interactive --no-auth-cache)
if [[ "$DRY_RUN" == false ]]; then
  : "${WPORG_USERNAME:?Missing WordPress.org SVN username}"
  : "${WPORG_PASSWORD:?Missing WordPress.org SVN password}"
  auth+=(--username "$WPORG_USERNAME" --password "$WPORG_PASSWORD")
fi

svn_work=$(mktemp -d)
trap 'rm -rf "$svn_work"' EXIT
# Fetch trunk/assets fully, but only the names of historical releases.
svn checkout "$SVN_URL" "$svn_work" --depth immediates "${auth[@]}"
svn update "$svn_work/trunk" "$svn_work/assets" --set-depth infinity "${auth[@]}"
svn update "$svn_work/tags" --set-depth immediates "${auth[@]}"

# Refuse to rewrite a release, or roll trunk back behind a published stable version.
python3 - "$svn_work/tags" "$PLUGIN_VERSION" <<'PY'
from pathlib import Path
import re
import sys

tags, version = Path(sys.argv[1]), sys.argv[2]
if (tags / version).exists():
    sys.exit(f'Release {version} already exists; publish a new version instead.')
current = tuple(map(int, version.split('.')))
for tag in tags.iterdir():
    if re.fullmatch(r'[0-9]+\.[0-9]+\.[0-9]+', tag.name) and tuple(map(int, tag.name.split('.'))) >= current:
        sys.exit(f'Release {version} would downgrade existing release {tag.name}.')
PY

# Protect SVN metadata while removing files absent from the production artifact.
rsync -a --delete --exclude='.svn/' "$DISTRIBUTION_DIR/" "$svn_work/trunk/"
rsync -a --delete --exclude='.svn/' "$ASSETS_DIR/" "$svn_work/assets/"

# Parse SVN status as XML so spaces in paths work and staging failures stop the release.
status_file=$(mktemp)
trap 'rm -rf "$svn_work"; rm -f "$status_file"' EXIT
svn status --xml "$svn_work" > "$status_file"
python3 - "$status_file" <<'PY'
import subprocess
import sys
import xml.etree.ElementTree as ET

for entry in ET.parse(sys.argv[1]).findall('.//entry'):
    state = entry.find('wc-status').get('item')
    if state == 'unversioned':
        subprocess.run(['svn', 'add', '--force', '--', entry.get('path') + '@'], check=True)
    elif state == 'missing':
        subprocess.run(['svn', 'delete', '--', entry.get('path') + '@'], check=True)
    elif state in {'conflicted', 'obstructed', 'incomplete'}:
        sys.exit(f'Unsafe SVN state: {state}: {entry.get("path")}')
PY
svn copy "$svn_work/trunk" "$svn_work/tags/$PLUGIN_VERSION"
svn status "$svn_work"

if [[ "$DRY_RUN" == true ]]; then
  echo 'Dry run passed; no SVN changes committed.'
else
  svn commit "$svn_work" -m "Deploy version $PLUGIN_VERSION" "${auth[@]}"
fi
