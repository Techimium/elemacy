import { __, _n, sprintf } from "@wordpress/i18n";
import { Badge } from "@/components/ui/badge";
import type { TemplateDisplay as Display } from "@/features/theme-builder/schemas/template";

/**
 * Where a template displays, as the site resolves it: its inclusion conditions,
 * or why it displays nowhere (no inclusion condition, an unavailable
 * condition, or a newer template with the same condition taking over).
 */
export function TemplateDisplay({ display }: { display: Display }) {
    // A draft with no inclusion rule will not display once published either.
    if (display.state === 'not_displayed' || (display.state === 'unpublished' && display.summary.length === 0)) {
        return (
            <p className="text-xs text-amber-700" data-display-state={display.state}>
                {__('Not displayed — add a condition to choose where it appears.', 'elemacy')}
            </p>
        );
    }

    if (display.state === 'suspended') {
        return (
            <p className="text-xs text-red-700" data-display-state="suspended">
                {sprintf(
                    /* translators: %s: comma-separated names of unavailable conditions. */
                    __('Suspended — unavailable condition: %s', 'elemacy'),
                    display.unavailable.join(', ')
                )}
            </p>
        );
    }

    return (
        <div className="space-y-1" data-display-state={display.state}>
            <div className="flex flex-wrap gap-1">
                {display.summary.map((label) => (
                    <Badge key={label} variant="secondary">{label}</Badge>
                ))}
                {display.exclusions > 0 && (
                    <Badge variant="outline">
                        {sprintf(
                            /* translators: %d: number of exclusion conditions. */
                            _n('%d exclusion', '%d exclusions', display.exclusions, 'elemacy'),
                            display.exclusions
                        )}
                    </Badge>
                )}
            </div>
            {display.overridden_by && (
                <p className="text-xs text-amber-700" data-display-overridden-by={display.overridden_by.id}>
                    {sprintf(
                        /* translators: %s: title of the newer template that takes precedence. */
                        __('Overridden by “%s” where they share a condition.', 'elemacy'),
                        display.overridden_by.title
                    )}
                </p>
            )}
        </div>
    );
}
