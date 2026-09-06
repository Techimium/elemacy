import type * as WpI18n from '@wordpress/i18n';

/**
 * Bridges `@wordpress/i18n` imports to WordPress's own runtime copy exposed on
 * `window.wp.i18n`. The `wp-i18n` script is a declared dependency of the admin
 * bundle (in both dev and production), so translations registered through
 * `wp_set_script_translations` reach the same i18n instance the app calls —
 * and the library is not bundled a second time. Aliased to `@wordpress/i18n`
 * in `vite.config.ts`.
 */
const runtime = (window as unknown as { wp?: { i18n?: typeof WpI18n } }).wp?.i18n;

if (!runtime) {
    throw new Error(
        'window.wp.i18n is unavailable. Ensure the "wp-i18n" script is enqueued as a dependency of the admin bundle.'
    );
}

export const __ = runtime.__;
export const _x = runtime._x;
export const _n = runtime._n;
export const _nx = runtime._nx;
export const sprintf = runtime.sprintf;
export const isRTL = runtime.isRTL;
export const setLocaleData = runtime.setLocaleData;
export const getLocaleData = runtime.getLocaleData;
export const hasTranslation = runtime.hasTranslation;
export const subscribe = runtime.subscribe;
