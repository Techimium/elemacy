import { __ } from '@wordpress/i18n';
import { cn } from '@/lib/utils';
import { Label } from '@/components/ui/label';
import { UnavailableBadge } from '@/components/unavailable-badge';
import type { Availability } from '@/schemas/availability';
import { usePopupTypes } from '@/features/popups/services/popup';
import { POPUP_TYPE_FALLBACK_ICON, POPUP_TYPE_HINTS } from '@/features/popups/constants/popups';

interface TypeFieldProps {
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    /**
     * The currently saved popup's own type availability: a
     * stored type that is no longer registered (e.g. a deactivated
     * extension) stays visible/selected here for repair rather than
     * silently looking indistinguishable from a healthy selection.
     */
    availability?: Availability;
}

export function TypeField({ value, onChange, disabled, availability }: TypeFieldProps) {
    const { data: types = [], isLoading } = usePopupTypes();

    return (
        <div className="space-y-2">
            <div className="flex items-center gap-2">
                <Label>{__('Type', 'elemacy')}</Label>
                <UnavailableBadge availability={availability} />
            </div>
            {isLoading ? (
                <p className="text-sm text-muted-foreground">{__('Loading types…', 'elemacy')}</p>
            ) : (
                <div className="grid grid-cols-2 gap-3">
                    {types.map((type) => {
                        const hint = POPUP_TYPE_HINTS[type.value];
                        const Icon = hint?.icon ?? POPUP_TYPE_FALLBACK_ICON;
                        const selected = value === type.value;
                        const locked = !type.availability.available;

                        return (
                            <button
                                type="button"
                                key={type.value}
                                disabled={disabled || locked}
                                onClick={() => onChange(type.value)}
                                className={cn(
                                    'flex flex-col items-start gap-2 rounded-lg border p-4 text-left transition-colors',
                                    'hover:border-primary/50 disabled:cursor-not-allowed disabled:opacity-50',
                                    selected
                                        ? 'border-primary ring-2 ring-primary/30 bg-primary/5'
                                        : 'border-input'
                                )}
                                aria-pressed={selected}
                                title={locked ? type.availability.message : undefined}
                            >
                                <Icon className="size-5 text-muted-foreground" />
                                <span className="text-sm font-semibold text-gray-900">
                                    {type.label}
                                </span>
                                {hint?.description && (
                                    <span className="text-xs text-muted-foreground">
                                        {hint.description}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
