import { __ } from '@wordpress/i18n';
import { ProBadge } from '@/components/pro-badge';

/**
 * Free-version teaser for popup analytics. Shows blurred placeholder stats with
 * a "Pro" lock so users know impressions/conversions unlock with Elemacy Pro.
 * Rendered as the popup-card-footer slot fallback; the pro plugin replaces it
 * with the real {@link PopupAnalytics} fill when active.
 */
function Stat({ value, label }: { value: string; label: string }) {
    return (
        <div className="text-center">
            <div className="text-lg font-bold text-gray-900">{value}</div>
            <div className="text-xs uppercase tracking-wide text-gray-400">{label}</div>
        </div>
    );
}

export function LockedAnalytics() {
    return (
        <div
            className="relative mt-4 border-t border-gray-100 pt-3"
            title={__('Available in Elemacy Pro', 'elemacy')}
        >
            <div className="grid grid-cols-2 gap-2 select-none blur-[3px]" aria-hidden>
                <Stat value="1,284" label={__('Impressions', 'elemacy')} />
                <Stat value="312" label={__('Conversions', 'elemacy')} />
            </div>
            <div className="absolute inset-0 flex items-center justify-center">
                <ProBadge />
            </div>
        </div>
    );
}
