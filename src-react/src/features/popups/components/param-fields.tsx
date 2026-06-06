import { __ } from '@wordpress/i18n';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ParamDescriptor } from '@/features/popups/schemas/trigger-rule-types';

type Params = Record<string, unknown>;

const clamp = (value: number, min?: number, max?: number) => {
    if (typeof min === 'number' && value < min) return min;
    if (typeof max === 'number' && value > max) return max;
    return value;
};

interface ParamFieldsProps {
    schema: ParamDescriptor[];
    params: Params;
    onChange: (params: Params) => void;
}

/**
 * Renders a type's `params_schema` as form controls and writes each value into
 * `params[key]`. Shared by trigger-row and rule-row so the two stay identical.
 * Fields stack full-width to match the rest of the popup form.
 */
export function ParamFields({ schema, params, onChange }: ParamFieldsProps) {
    if (schema.length === 0) return null;

    const setParam = (key: string, value: unknown) => onChange({ ...params, [key]: value });

    return (
        <div className="space-y-4">
            {schema.map((descriptor) => (
                <ParamField
                    key={descriptor.key}
                    descriptor={descriptor}
                    value={params[descriptor.key]}
                    onChange={(value) => setParam(descriptor.key, value)}
                />
            ))}
        </div>
    );
}

function ParamField({
    descriptor,
    value,
    onChange,
}: {
    descriptor: ParamDescriptor;
    value: unknown;
    onChange: (value: unknown) => void;
}) {
    const label = descriptor.label ?? descriptor.key;

    // Inputs deliberately carry no `type` attribute: WordPress admin's forms.css
    // styles input[type=text|number|…] (unlayered, so it outranks our utility
    // classes) and would bleed in. A bare input escapes those selectors and keeps
    // the component styling — the same reason the form's Title field looks right.
    switch (descriptor.type) {
        case 'number':
            return (
                <div className="space-y-2">
                    <Label>
                        {label}
                        {descriptor.unit ? ` (${descriptor.unit})` : ''}
                    </Label>
                    <Input
                        inputMode="numeric"
                        value={typeof value === 'number' ? value : ''}
                        onChange={(event) => {
                            const raw = event.target.value;
                            if (raw === '') return onChange(undefined);
                            const parsed = Number(raw);
                            if (!Number.isNaN(parsed)) {
                                onChange(clamp(parsed, descriptor.min, descriptor.max));
                            }
                        }}
                    />
                </div>
            );

        case 'text':
            return (
                <div className="space-y-2">
                    <Label>{label}</Label>
                    <Input
                        value={typeof value === 'string' ? value : ''}
                        onChange={(event) => onChange(event.target.value)}
                    />
                </div>
            );

        case 'select':
            return (
                <div className="space-y-2">
                    <Label>{label}</Label>
                    <Select
                        value={typeof value === 'string' && value !== '' ? value : undefined}
                        onValueChange={onChange}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder={__('Select…', 'elemacy')} />
                        </SelectTrigger>
                        <SelectContent>
                            {(descriptor.options ?? []).map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            );

        case 'boolean':
            return (
                <div className="flex items-center justify-between">
                    <Label>{label}</Label>
                    <Switch checked={value === true} onCheckedChange={onChange} />
                </div>
            );

        case 'checkbox-group':
            return (
                <CheckboxGroupField
                    label={label}
                    options={descriptor.options ?? []}
                    value={Array.isArray(value) ? (value as string[]) : []}
                    onChange={onChange}
                />
            );

        default:
            return null;
    }
}

function CheckboxGroupField({
    label,
    options,
    value,
    onChange,
}: {
    label: string;
    options: { value: string; label: string }[];
    value: string[];
    onChange: (value: string[]) => void;
}) {
    const toggle = (optionValue: string, checked: boolean) => {
        const next = checked
            ? [...value, optionValue]
            : value.filter((item) => item !== optionValue);
        onChange(next);
    };

    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            <div className="flex flex-wrap gap-x-4 gap-y-2">
                {options.map((option) => (
                    <label
                        key={option.value}
                        className="flex items-center gap-2 text-sm font-normal"
                    >
                        <input
                            type="checkbox"
                            className="size-4 rounded border-input accent-primary"
                            checked={value.includes(option.value)}
                            onChange={(event) => toggle(option.value, event.target.checked)}
                        />
                        {option.label}
                    </label>
                ))}
            </div>
        </div>
    );
}
