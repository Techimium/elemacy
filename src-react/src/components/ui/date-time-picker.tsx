import { DatePicker } from '@/components/ui/date-picker';
import { TimePicker } from '@/components/ui/time-picker';

const pad = (value: number) => String(value).padStart(2, '0');

/** Serialize a Date to the `YYYY-MM-DDTHH:mm` string the rule params store. */
const toValue = (date: Date) =>
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(
        date.getHours()
    )}:${pad(date.getMinutes())}`;

const parseValue = (value?: string): Date | null => {
    if (!value) return null;
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

interface DateTimePickerProps {
    value?: string;
    onChange: (value: string | undefined) => void;
    placeholder?: string;
}

export function DateTimePicker({ value, onChange, placeholder }: DateTimePickerProps) {
    const selected = parseValue(value);

    const commitDate = (date: Date) => {
        const next = new Date(date);
        next.setHours(selected?.getHours() ?? 0, selected?.getMinutes() ?? 0, 0, 0);
        onChange(toValue(next));
    };

    const commitTime = (hours: number, minutes: number) => {
        const base = selected ? new Date(selected) : new Date();
        base.setHours(hours, minutes, 0, 0);
        onChange(toValue(base));
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <DatePicker
                value={selected ?? undefined}
                onChange={commitDate}
                placeholder={placeholder}
                className="flex-1"
            />
            <TimePicker
                hours={selected?.getHours()}
                minutes={selected?.getMinutes()}
                onChange={commitTime}
            />
        </div>
    );
}
