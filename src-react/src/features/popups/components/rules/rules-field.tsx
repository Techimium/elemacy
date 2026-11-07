import { useFieldArray, useFormContext, useWatch } from 'react-hook-form';
import { __ } from '@wordpress/i18n';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { CreatePopup, TriggerRule } from '@/features/popups/schemas/popup';
import { useRuleTypes } from '@/features/popups/services/rules';
import { generateEntryId } from '@/features/popups/lib/seed-params';
import { RuleRow } from './rule-row';

/** Whether a rule needs at least one chosen role to restrict anything. */
function requiresRole(rule: TriggerRule): boolean {
    const roles = rule.params?.roles;

    return rule.type === 'roles' && Array.isArray(roles) && roles.length > 0;
}

export function RulesField() {
    const { control } = useFormContext<CreatePopup>();
    const { fields, append, remove, update } = useFieldArray({
        control,
        name: 'rules',
    });
    const { data: types = [] } = useRuleTypes();
    const rules = (useWatch({ control, name: 'rules' }) ?? []) as TriggerRule[];

    // "Hide for logged-in users" shows the popup to visitors only; a role rule
    // that requires a role shows it to signed-in users only. Together nobody
    // can see it. Saving stays allowed: it is a warning, not a validation.
    const neverShows = rules.some((rule) => rule.type === 'logged_in') && rules.some(requiresRole);

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

            {neverShows && (
                <p
                    role="alert"
                    data-testid="popup-rules-never-shows"
                    className="rounded-md border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900"
                >
                    {__(
                        'This popup will never display: "Hide for logged-in users" and a "User roles" rule cannot both be satisfied.',
                        'elemacy'
                    )}
                </p>
            )}

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
