import { Lock } from 'lucide-react';
import { __ } from '@wordpress/i18n';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ParamFields } from '@/features/popups/components/param-fields';
import type { TriggerRule } from '@/features/popups/schemas/popup';
import type { RuleType } from '@/features/popups/schemas/trigger-rule-types';
import { seedParams } from '@/features/popups/lib/seed-params';

interface RuleRowProps {
    entry: TriggerRule;
    types: RuleType[];
    onChange: (entry: TriggerRule) => void;
    onRemove: () => void;
}

export function RuleRow({ entry, types, onChange, onRemove }: RuleRowProps) {
    const selected = types.find((t) => t.value === entry.type);

    const handleTypeChange = (value: string) => {
        const next = types.find((t) => t.value === value);
        onChange({ ...entry, type: value, params: next ? seedParams(next.params_schema) : {} });
    };

    return (
        <div className="space-y-3 rounded-md border p-3">
            <div className="flex items-center gap-2">
                <Select value={entry.type || undefined} onValueChange={handleTypeChange}>
                    <SelectTrigger className="flex-1 min-w-0">
                        <SelectValue placeholder={__('Select rule…', 'elemacy')} />
                    </SelectTrigger>
                    <SelectContent>
                        {types.map((type) => {
                            const locked = type.is_mock;
                            return (
                                <SelectItem
                                    key={type.value}
                                    value={type.value}
                                    disabled={locked}
                                    title={
                                        locked
                                            ? __('Available in Elemacy Pro', 'elemacy')
                                            : undefined
                                    }
                                >
                                    <span className="flex items-center gap-2">
                                        {type.label}
                                        {locked && (
                                            <Badge variant="secondary" className="gap-1">
                                                <Lock aria-hidden />
                                                {__('Pro', 'elemacy')}
                                            </Badge>
                                        )}
                                    </span>
                                </SelectItem>
                            );
                        })}
                    </SelectContent>
                </Select>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onRemove}
                    aria-label={__('Remove rule', 'elemacy')}
                    className="text-muted-foreground hover:text-destructive"
                >
                    ×
                </Button>
            </div>

            {selected && (
                <ParamFields
                    schema={selected.params_schema}
                    params={entry.params}
                    onChange={(params) => onChange({ ...entry, params })}
                />
            )}
        </div>
    );
}
