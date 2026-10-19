import { useEffect, useRef } from 'react';
import { ConditionBuilder } from './condition-builder';
import { useConditionTypes } from '@/services/conditions';
import type { ConditionRule } from '@/schemas/condition';

interface ConditionsFieldProps {
    value?: ConditionRule[];
    onChange: (value: ConditionRule[]) => void;
    /** e.g. `popup` — sent as `context` to the condition-types query. */
    context?: string;
    /** e.g. `header` — sent as `template_type` to the condition-types query. */
    templateType?: string;
    /** What having no rules means here; see ConditionBuilder. */
    emptyMessage?: string;
    /** Rules dropped automatically, not by the author; see ConditionBuilder. */
    onPrune?: (value: ConditionRule[]) => void;
}

export function ConditionsField({ value, onChange, context, templateType, emptyMessage, onPrune }: ConditionsFieldProps) {
    const { data: groups = [], isSuccess } = useConditionTypes({ context, templateType });
    const noneApply = isSuccess && groups.length === 0;

    const valueRef = useRef(value);
    const onPruneRef = useRef(onPrune ?? onChange);
    valueRef.current = value;
    onPruneRef.current = onPrune ?? onChange;

    // The builder drops rules a type does not offer, but it is not mounted
    // when no condition applies at all, so a rule added under one template
    // type would otherwise be saved under another. A rule the server reports
    // as unavailable is kept for repair, as the builder does.
    useEffect(() => {
        if (!noneApply) return;
        const current = valueRef.current ?? [];
        const kept = current.filter((r) => r.availability && !r.availability.available);
        if (kept.length !== current.length) {
            onPruneRef.current(kept);
        }
    }, [noneApply, templateType, context]);

    // Hide only once we positively know no condition types apply to this
    // context/template type. An extension (pro) can add some and they surface
    // here automatically — no hardcoded list.
    if (noneApply) {
        return null;
    }

    return (
        <ConditionBuilder
            value={value ?? []}
            onChange={onChange}
            context={context}
            templateType={templateType}
            emptyMessage={emptyMessage}
            onPrune={onPrune}
        />
    );
}
