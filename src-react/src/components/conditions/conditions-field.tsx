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
}

export function ConditionsField({ value, onChange, context, templateType }: ConditionsFieldProps) {
    const { data: groups = [], isSuccess } = useConditionTypes({ context, templateType });

    // Hide only once we positively know no condition types apply to this
    // context/template type. Today some have none registered; an extension (pro)
    // can add some and they surface here automatically — no hardcoded list.
    if (isSuccess && groups.length === 0) {
        return null;
    }

    return (
        <ConditionBuilder
            value={value ?? []}
            onChange={onChange}
            context={context}
            templateType={templateType}
        />
    );
}
