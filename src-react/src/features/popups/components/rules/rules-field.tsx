import { useFieldArray, useFormContext } from 'react-hook-form';
import { __ } from '@wordpress/i18n';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { CreatePopup, TriggerRule } from '@/features/popups/schemas/popup';
import { useRuleTypes } from '@/features/popups/services/rules';
import { generateEntryId } from '@/features/popups/lib/seed-params';
import { RuleRow } from './rule-row';

export function RulesField() {
    const { control } = useFormContext<CreatePopup>();
    const { fields, append, remove, update } = useFieldArray({
        control,
        name: 'rules',
    });
    const { data: types = [] } = useRuleTypes();

    const addRule = () => {
        append({ id: generateEntryId('rule'), type: '', params: {} });
    };

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <Label>{__('Advanced Rules', 'elemacy')}</Label>
                <Button
                    type="button"
                    variant="link"
                    size="sm"
                    onClick={addRule}
                    className="h-auto p-0"
                >
                    + {__('Add Rule', 'elemacy')}
                </Button>
            </div>

            {fields.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    {__('No rules — the popup has no extra display restrictions.', 'elemacy')}
                </p>
            ) : (
                <div className="space-y-3">
                    {fields.map((field, index) => (
                        <RuleRow
                            key={field.id}
                            entry={field as unknown as TriggerRule}
                            types={types}
                            onChange={(entry) => update(index, entry)}
                            onRemove={() => remove(index)}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
