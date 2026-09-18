# Third-party code shipped with Elemacy

Everything Elemacy ships that it did not write, with version, license and source. `verify-package.py` fails the release if a shipped file with a recognizable third-party license header is not covered here.

## Free admin bundle

`assets/admin/scripts/admin.js` and `assets/admin/styles/admin.css` are built by Vite from `src-react/` and contain these packages (versions from `src-react/package-lock.json`):

| Package | Version | License | Source |
| --- | --- | --- | --- |
| @hookform/resolvers | 5.2.2 | MIT | https://www.npmjs.com/package/@hookform/resolvers |
| @radix-ui/react-dialog | 1.1.15 | MIT | https://www.npmjs.com/package/@radix-ui/react-dialog |
| @radix-ui/react-dropdown-menu | 2.1.16 | MIT | https://www.npmjs.com/package/@radix-ui/react-dropdown-menu |
| @radix-ui/react-label | 2.1.8 | MIT | https://www.npmjs.com/package/@radix-ui/react-label |
| @radix-ui/react-select | 2.2.6 | MIT | https://www.npmjs.com/package/@radix-ui/react-select |
| @radix-ui/react-slot | 1.2.4 | MIT | https://www.npmjs.com/package/@radix-ui/react-slot |
| @radix-ui/react-switch | 1.2.6 | MIT | https://www.npmjs.com/package/@radix-ui/react-switch |
| @tanstack/react-query | 5.90.13 | MIT | https://www.npmjs.com/package/@tanstack/react-query |
| @wordpress/i18n | 6.15.0 | GPL-2.0-or-later | https://www.npmjs.com/package/@wordpress/i18n |
| axios | 1.13.2 | MIT | https://www.npmjs.com/package/axios |
| class-variance-authority | 0.7.1 | Apache-2.0 | https://www.npmjs.com/package/class-variance-authority |
| clsx | 2.1.1 | MIT | https://www.npmjs.com/package/clsx |
| lucide / lucide-react | 0.545.0 / 0.544.0 | ISC | https://lucide.dev |
| next-themes | 0.4.6 | MIT | https://www.npmjs.com/package/next-themes |
| radix-ui | 1.4.3 | MIT | https://www.npmjs.com/package/radix-ui |
| react, react-dom | 19.2.0 | MIT | https://react.dev |
| react-hook-form | 7.69.0 | MIT | https://www.npmjs.com/package/react-hook-form |
| react-router | 7.9.4 | MIT | https://www.npmjs.com/package/react-router |
| sonner | 2.0.7 | MIT | https://www.npmjs.com/package/sonner |
| tailwind-merge | 3.3.1 | MIT | https://www.npmjs.com/package/tailwind-merge |
| tailwindcss (@tailwindcss/vite) | 4.1.14 | MIT | https://tailwindcss.com |
| zod | 4.2.1 | MIT | https://www.npmjs.com/package/zod |

## Pro admin bundle

`elemacy-pro/assets/admin/scripts/elemacy-pro.js` and `styles/elemacy-pro.css` are built from Pro's own sources only. Pro consumes React, ReactDOM and the shared components through Free's runtime bridge and its production build rejects any bundled React; it has no runtime dependencies of its own.

## Hand-written assets

Every file under `assets/editor`, `assets/frontend`, `src/Modules/*/assets` and Pro's `assets/popups` is Elemacy's own code under the plugin license.

## Provided at runtime, not shipped

- jQuery: WordPress core.
- Swiper (Loop Carousel) and select2 (query control): loaded from Elementor's own bundled copies.
- `tests/e2e/jquery.js` is a test fixture and is excluded from every archive.
