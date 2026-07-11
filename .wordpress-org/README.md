# WordPress.org listing assets

This directory is deployed to the plugin's SVN `/assets` folder (see
`.github/workflows/deploy-wordpress-plugin.yml`, `ASSETS_DIR`). It is excluded
from the plugin zip/trunk via `.distignore`.

Required files (PNG or JPG; PNG recommended):

| File | Size | Purpose |
|---|---|---|
| `icon-128x128.png` | 128×128 | Plugin directory icon |
| `icon-256x256.png` | 256×256 | Retina icon |
| `banner-772x250.png` | 772×250 | Listing banner |
| `banner-1544x500.png` | 1544×500 | Retina banner |
| `screenshot-1.png` … `screenshot-N.png` | any | Must match the numbered entries in readme.txt `== Screenshots ==` |

The `== Screenshots ==` section in `readme.txt` currently lists entries with no
matching images here — add one `screenshot-N.png` per entry (or trim the
section) before the first deploy, or the listing will show broken slots.
