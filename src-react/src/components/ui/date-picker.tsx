import * as React from 'react';
import { __ } from '@wordpress/i18n';
import { CalendarIcon, ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

const sameDay = (a: Date, b: Date) =>
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate();

/** The 42 days (6 weeks) that fill the visible month grid, including spillover. */
const monthGrid = (month: Date): Date[] => {
    const firstOfMonth = new Date(month.getFullYear(), month.getMonth(), 1);
    const gridStart = new Date(firstOfMonth);
    gridStart.setDate(1 - firstOfMonth.getDay());

    return Array.from({ length: 42 }, (_, offset) => {
        const day = new Date(gridStart);
        day.setDate(gridStart.getDate() + offset);
        return day;
    });
};

const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });
const monthFormatter = new Intl.DateTimeFormat(undefined, {
    month: 'long',
    year: 'numeric',
});

interface DatePickerProps {
    value?: Date;
    onChange: (date: Date) => void;
    placeholder?: string;
    className?: string;
}

export function DatePicker({ value, onChange, placeholder, className }: DatePickerProps) {
    const [open, setOpen] = React.useState(false);
    const [viewMonth, setViewMonth] = React.useState(() => value ?? new Date());
    const today = new Date();

    React.useEffect(() => {
        if (value) setViewMonth(value);
    }, [value]);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className={cn(
                        'justify-start font-normal',
                        !value && 'text-muted-foreground',
                        className
                    )}
                >
                    <CalendarIcon className="size-4 opacity-60" />
                    {value
                        ? dateFormatter.format(value)
                        : placeholder ?? __('Pick a date', 'elemacy')}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto">
                <div className="flex items-center justify-between pb-3">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={() =>
                            setViewMonth(
                                (current) =>
                                    new Date(current.getFullYear(), current.getMonth() - 1, 1)
                            )
                        }
                    >
                        <ChevronLeftIcon className="size-4" />
                    </Button>
                    <div className="text-sm font-medium">{monthFormatter.format(viewMonth)}</div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={() =>
                            setViewMonth(
                                (current) =>
                                    new Date(current.getFullYear(), current.getMonth() + 1, 1)
                            )
                        }
                    >
                        <ChevronRightIcon className="size-4" />
                    </Button>
                </div>

                <div className="grid grid-cols-7 gap-1">
                    {WEEKDAYS.map((weekday) => (
                        <div
                            key={weekday}
                            className="text-muted-foreground flex h-8 items-center justify-center text-xs font-normal"
                        >
                            {weekday}
                        </div>
                    ))}
                    {monthGrid(viewMonth).map((day) => {
                        const isCurrentMonth = day.getMonth() === viewMonth.getMonth();
                        const isSelected = value ? sameDay(day, value) : false;
                        const isToday = sameDay(day, today);

                        return (
                            <button
                                key={day.toISOString()}
                                type="button"
                                onClick={() => {
                                    onChange(day);
                                    setOpen(false);
                                }}
                                className={cn(
                                    'flex size-8 items-center justify-center rounded-md text-sm transition-colors',
                                    'hover:bg-accent hover:text-accent-foreground',
                                    !isCurrentMonth && 'text-muted-foreground/40',
                                    isToday && !isSelected && 'border border-input',
                                    isSelected &&
                                        'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground'
                                )}
                            >
                                {day.getDate()}
                            </button>
                        );
                    })}
                </div>
            </PopoverContent>
        </Popover>
    );
}
