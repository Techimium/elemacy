import { __ } from '@wordpress/i18n';

import { cn } from '@/lib/utils';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const pad = (value: number) => String(value).padStart(2, '0');

/** Whether the active locale formats hours with an AM/PM marker (e.g. en-US). */
const usesMeridiem = (() => {
    try {
        return new Intl.DateTimeFormat(undefined, { hour: 'numeric' })
            .formatToParts(new Date(2020, 0, 1, 13))
            .some((part) => part.type === 'dayPeriod');
    } catch {
        return false;
    }
})();

const HOURS_24 = Array.from({ length: 24 }, (_, hour) => hour);
const HOURS_12 = [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
const MINUTES = Array.from({ length: 60 }, (_, minute) => minute);

const to12 = (hours24: number) => ({
    hour: hours24 % 12 === 0 ? 12 : hours24 % 12,
    period: hours24 < 12 ? 'AM' : 'PM',
});

const from12 = (hour12: number, period: string) => {
    if (period === 'AM') return hour12 === 12 ? 0 : hour12;
    return hour12 === 12 ? 12 : hour12 + 12;
};

interface TimePickerProps {
    hours?: number;
    minutes?: number;
    onChange: (hours: number, minutes: number) => void;
    className?: string;
}

export function TimePicker({ hours, minutes, onChange, className }: TimePickerProps) {
    const meridiem = typeof hours === 'number' ? to12(hours) : null;

    const minuteSelect = (
        <Select
            value={typeof minutes === 'number' ? String(minutes) : undefined}
            onValueChange={(next) => onChange(hours ?? 0, Number(next))}
        >
            <SelectTrigger className="w-[4.5rem]" aria-label={__('Minutes', 'elemacy')}>
                <SelectValue placeholder={__('MM', 'elemacy')} />
            </SelectTrigger>
            <SelectContent position="popper" className="max-h-60">
                {MINUTES.map((minute) => (
                    <SelectItem key={minute} value={String(minute)}>
                        {pad(minute)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    if (usesMeridiem) {
        return (
            <div className={cn('flex items-center gap-1', className)}>
                <Select
                    value={meridiem ? String(meridiem.hour) : undefined}
                    onValueChange={(next) =>
                        onChange(from12(Number(next), meridiem?.period ?? 'AM'), minutes ?? 0)
                    }
                >
                    <SelectTrigger className="w-[4.5rem]" aria-label={__('Hours', 'elemacy')}>
                        <SelectValue placeholder={__('HH', 'elemacy')} />
                    </SelectTrigger>
                    <SelectContent position="popper" className="max-h-60">
                        {HOURS_12.map((hour) => (
                            <SelectItem key={hour} value={String(hour)}>
                                {hour}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <span className="text-muted-foreground">:</span>
                {minuteSelect}
                <Select
                    value={meridiem?.period}
                    onValueChange={(period) =>
                        onChange(from12(meridiem?.hour ?? 12, period), minutes ?? 0)
                    }
                >
                    <SelectTrigger className="w-[4.5rem]" aria-label={__('AM/PM', 'elemacy')}>
                        <SelectValue placeholder={__('--', 'elemacy')} />
                    </SelectTrigger>
                    <SelectContent position="popper">
                        <SelectItem value="AM">{__('AM', 'elemacy')}</SelectItem>
                        <SelectItem value="PM">{__('PM', 'elemacy')}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        );
    }

    return (
        <div className={cn('flex items-center gap-1', className)}>
            <Select
                value={typeof hours === 'number' ? String(hours) : undefined}
                onValueChange={(next) => onChange(Number(next), minutes ?? 0)}
            >
                <SelectTrigger className="w-[4.5rem]" aria-label={__('Hours', 'elemacy')}>
                    <SelectValue placeholder={__('HH', 'elemacy')} />
                </SelectTrigger>
                <SelectContent position="popper" className="max-h-60">
                    {HOURS_24.map((hour) => (
                        <SelectItem key={hour} value={String(hour)}>
                            {pad(hour)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <span className="text-muted-foreground">:</span>
            {minuteSelect}
        </div>
    );
}
