import { useFieldArray, useFormContext } from 'react-hook-form';
import { __ } from '@wordpress/i18n';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { CreatePopup, TriggerRule } from '@/features/popups/schemas/popup';
import { useTriggerTypes } from '@/features/popups/services/triggers';
import { generateEntryId } from '@/features/popups/lib/seed-params';
import { TriggerRow } from './trigger-row';

export function TriggersField() {
    const { control } = useFormContext<CreatePopup>();
    const { fields, append, remove, update } = useFieldArray({
        control,
        name: 'triggers',
    });
    const { data: types = [] } = useTriggerTypes();

    const addTrigger = () => {
        append({ id: generateEntryId('trig'), type: '', params: {} });
    };

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <Label>{__('Triggers', 'elemacy')}</Label>
                <Button
                    type="button"
                    variant="link"
                    size="sm"
                    onClick={addTrigger}
                    className="h-auto p-0"
                >
                    + {__('Add Trigger', 'elemacy')}
                </Button>
            </div>

            {fields.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    {__('No triggers — the popup opens immediately on page load.', 'elemacy')}
                </p>
            ) : (
                <div className="space-y-3">
                    {fields.map((field, index) => (
                        <TriggerRow
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
