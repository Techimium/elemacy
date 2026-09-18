import { AlertTriangle } from 'lucide-react';
import { __ } from '@wordpress/i18n';
import { cn } from '@/lib/utils';
import type { Availability } from '@/schemas/availability';

interface UnavailableBadgeProps {
    availability?: Availability;
    className?: string;
}

/**
 * Marks a saved condition/rule/trigger the server reports as unavailable
 * (D5) — unknown, or a non-functional mock stand-in — so an administrator
 * can tell it apart from a field that simply has nothing selected yet. The
 * message explains why; the underlying value/params are left untouched.
 */
export function UnavailableBadge({ availability, className }: UnavailableBadgeProps) {
    if (!availability || availability.available) {
        return null;
    }

    return (
        <span
            className={cn(
                'inline-flex w-fit shrink-0 items-center gap-1 whitespace-nowrap rounded-md',
                'bg-destructive/10 px-2 py-0.5',
                'text-xs font-medium text-destructive',
                '[&>svg]:size-3 [&>svg]:pointer-events-none',
                className
            )}
            title={availability.message || __('This saved value is currently unavailable.', 'elemacy')}
        >
            <AlertTriangle aria-hidden />
            {__('Unavailable', 'elemacy')}
        </span>
    );
}
