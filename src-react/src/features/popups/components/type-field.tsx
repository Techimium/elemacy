import { __ } from '@wordpress/i18n';
import { AlignStartVertical, GalleryThumbnails, PanelTop, Square } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Label } from '@/components/ui/label';
import { POPUP_TYPES, type PopupType } from '@/features/popups/constants/popups';

const TYPE_ICONS: Record<PopupType, React.ComponentType<{ className?: string }>> = {
    popup: Square,
    topbar: PanelTop,
    floating: AlignStartVertical,
    banner: GalleryThumbnails,
};

interface TypeFieldProps {
    value: PopupType;
    onChange: (value: PopupType) => void;
    disabled?: boolean;
}

export function TypeField({ value, onChange, disabled }: TypeFieldProps) {
    return (
        <div className="space-y-2">
            <Label>{__('Type', 'elemacy')}</Label>
            <div className="grid grid-cols-2 gap-3">
                {POPUP_TYPES.map((type) => {
                    const Icon = TYPE_ICONS[type.value];
                    const selected = value === type.value;
                    return (
                        <button
                            type="button"
                            key={type.value}
                            disabled={disabled}
                            onClick={() => onChange(type.value)}
                            className={cn(
                                'flex flex-col items-start gap-2 rounded-lg border p-4 text-left transition-colors',
                                'hover:border-primary/50 disabled:cursor-not-allowed disabled:opacity-50',
                                selected
                                    ? 'border-primary ring-2 ring-primary/30 bg-primary/5'
                                    : 'border-input'
                            )}
                            aria-pressed={selected}
                        >
                            <Icon className="size-5 text-muted-foreground" />
                            <span className="text-sm font-semibold text-gray-900">{type.label}</span>
                            <span className="text-xs text-muted-foreground">{type.description}</span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
