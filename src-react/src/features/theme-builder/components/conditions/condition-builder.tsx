import { useCallback, useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useConditionTypes } from '@/features/theme-builder/services/conditions';
import type { ConditionRule } from '@/features/theme-builder/schemas/condition';
import { ConditionRuleRow } from './condition-rule';

function generateId(): string {
    return `cond_${Date.now()}_${Math.random().toString(36).slice(2)}`;
}

interface ConditionBuilderProps {
    value: ConditionRule[];
    onChange: (conditions: ConditionRule[]) => void;
    templateType?: string;
}

export function ConditionBuilder({ value, onChange, templateType }: ConditionBuilderProps) {
    const { data: groups = [], isSuccess } = useConditionTypes(templateType);

    const onChangeRef = useRef(onChange);
    const valueRef = useRef(value);
    onChangeRef.current = onChange;
    valueRef.current = value;

    // Drop rules whose `type` is no longer offered for the current template type
    // (e.g. user changed a Header → Single, archive rules don't apply anymore).
    // Mock entries don't count as "real" rules — they were never selectable.
    useEffect(() => {
        if (!isSuccess) return;
        const selectable = new Set(
            groups.flatMap((g) => g.conditions.filter((c) => !c.is_mock).map((c) => c.value))
        );
        const current = valueRef.current;
        const kept = current.filter((r) => r.type === '' || selectable.has(r.type));
        const removed = current.length - kept.length;
        if (removed > 0) {
            onChangeRef.current(kept);
            toast.info(
                removed === 1
                    ? __('1 condition removed (not applicable to this template type)', 'elemacy')
                    : `${removed} ${__('conditions removed (not applicable to this template type)', 'elemacy')}`
            );
        }
    }, [groups, isSuccess]);

    const addRule = useCallback(() => {
        onChange([
            ...value,
            { id: generateId(), type: '', operator: 'include', value: '' },
        ]);
    }, [value, onChange]);

    const updateRule = useCallback((id: string, updated: ConditionRule) => {
        onChange(value.map((r) => (r.id === id ? updated : r)));
    }, [value, onChange]);

    const removeRule = useCallback((id: string) => {
        onChange(value.filter((r) => r.id !== id));
    }, [value, onChange]);

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <Label>{__('Display Conditions', 'elemacy')}</Label>
                <Button type="button" variant="link" size="sm" onClick={addRule} className="h-auto p-0">
                    + {__('Add Condition', 'elemacy')}
                </Button>
            </div>

            {value.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    {__('No conditions — template displays everywhere.', 'elemacy')}
                </p>
            ) : (
                <div className="space-y-2">
                    {value.map((rule) => (
                        <ConditionRuleRow
                            key={rule.id}
                            rule={rule}
                            conditionGroups={groups}
                            onChange={(updated) => updateRule(rule.id, updated)}
                            onRemove={() => removeRule(rule.id)}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
