import { ConditionBuilder } from './condition-builder';
import { useConditionTypes } from '@/features/theme-builder/services/conditions';
import type { ConditionRule } from '@/features/theme-builder/schemas/condition';

interface ConditionsFieldProps {
    value?: ConditionRule[];
    onChange: (value: ConditionRule[]) => void;
    templateType?: string;
}

export function ConditionsField({ value, onChange, templateType }: ConditionsFieldProps) {
    const { data: groups = [], isSuccess } = useConditionTypes(templateType);

    // Hide only once we positively know no condition types apply to this template
    // type. Today 404/search/loop have none registered; an extension (pro) can add
    // some and they surface here automatically — no hardcoded list to maintain.
    if (isSuccess && groups.length === 0) {
        return null;
    }

    return <ConditionBuilder value={value ?? []} onChange={onChange} templateType={templateType} />;
}
