import { Lock } from 'lucide-react';
import { __ } from '@wordpress/i18n';
import { cn } from '@/lib/utils';

interface ProBadgeProps {
    className?: string;
    label?: string;
}

/**
 * Eye-catching "Pro" pill used to mark locked premium features. A flat solid
 * amber so it stands out from the neutral admin UI.
 */
export function ProBadge({ className, label }: ProBadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex w-fit shrink-0 items-center gap-1 whitespace-nowrap rounded-md',
                'bg-amber-100 px-2 py-0.5',
                'text-xs font-bold uppercase tracking-wide text-amber-800',
                'shadow-sm [&>svg]:size-3 [&>svg]:pointer-events-none',
                className
            )}
        >
            <Lock aria-hidden className="text-amber-800" />
            {label ?? __('Pro', 'elemacy')}
        </span>
    );
}
