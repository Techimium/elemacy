import { useCallback, useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useConditionTypes } from '@/services/conditions';
import { newConditionRuleId, type ConditionRule } from '@/schemas/condition';
import { ConditionRuleRow } from './condition-rule';

interface ConditionBuilderProps {
    value: ConditionRule[];
    onChange: (conditions: ConditionRule[]) => void;
    context?: string;
    templateType?: string;
    /** What having no rules means here; defaults to "displays everywhere". */
    emptyMessage?: string;
    /**
     * Receives the rules left after ones the current type does not offer are
     * dropped automatically, as distinct from an author's own edit through
     * onChange. Defaults to onChange.
     */
    onPrune?: (conditions: ConditionRule[]) => void;
}

export function ConditionBuilder({ value, onChange, context, templateType, emptyMessage, onPrune }: ConditionBuilderProps) {
    const { data: groups = [], isSuccess } = useConditionTypes({ context, templateType });

    const onPruneRef = useRef(onPrune ?? onChange);
    const valueRef = useRef(value);
    onPruneRef.current = onPrune ?? onChange;
    valueRef.current = value;

    // Drop rules whose `type` is no longer offered for the current context /
    // template type (e.g. user changed a Header → Single, archive rules don't
    // apply anymore). A rule the server reports as unavailable (unknown, or a
    // non-functional mock stand-in) is never auto-removed here even when it
    // isn't in this context's list: D5 requires preserving it for repair,
    // not converting it into an empty/match-all definition just because this
    // screen loaded or an unrelated field was edited.
    useEffect(() => {
        if (!isSuccess) return;
        const applicable = new Set(groups.flatMap((g) => g.conditions.map((c) => c.value)));
        const current = valueRef.current;
        const kept = current.filter((r) => {
            if (r.type === '') return true;
            if (r.availability && !r.availability.available) return true;
            return applicable.has(r.type);
        });
        const removed = current.length - kept.length;
        if (removed > 0) {
            onPruneRef.current(kept);
            toast.info(
                removed === 1
                    ? __('1 condition removed (not applicable to this type)', 'elemacy')
                    : `${removed} ${__('conditions removed (not applicable to this type)', 'elemacy')}`
            );
        }
    }, [groups, isSuccess]);

    const addRule = useCallback(() => {
        onChange([
            ...value,
            { id: newConditionRuleId(), type: '', operator: 'include', value: '' },
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
                    {emptyMessage ?? __('No conditions — displays everywhere.', 'elemacy')}
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
