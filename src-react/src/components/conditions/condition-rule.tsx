import { Lock } from 'lucide-react';
import { __ } from '@wordpress/i18n';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { AsyncCombobox } from '@/components/ui/async-combobox';
import type { ConditionGroup, ConditionRule, ConditionType } from '@/schemas/condition';

// Radix Select disallows empty string values; this sentinel represents
// "no sub-value" (the condition's empty-value branch — "any" / "all").
const ANY_VALUE = '__any__';

interface ConditionRuleRowProps {
    rule: ConditionRule;
    conditionGroups: ConditionGroup[];
    onChange: (rule: ConditionRule) => void;
    onRemove: () => void;
}

export function ConditionRuleRow({ rule, conditionGroups, onChange, onRemove }: ConditionRuleRowProps) {
    return (
        <div className="flex items-center gap-2">
            <OperatorSelect rule={rule} onChange={onChange} />
            <TypeSelect groups={conditionGroups} rule={rule} onChange={onChange} />
            <RemoveButton onClick={onRemove} />
        </div>
    );
}

function OperatorSelect({ rule, onChange }: { rule: ConditionRule; onChange: (rule: ConditionRule) => void }) {
    return (
        <Select
            value={rule.operator}
            onValueChange={(v) => onChange({ ...rule, operator: v as 'include' | 'exclude' })}
        >
            <SelectTrigger className="w-32">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="include">{__('Show on', 'elemacy')}</SelectItem>
                <SelectItem value="exclude">{__('Hide on', 'elemacy')}</SelectItem>
            </SelectContent>
        </Select>
    );
}

function TypeSelect({
    groups,
    rule,
    onChange,
}: {
    groups: ConditionGroup[];
    rule: ConditionRule;
    onChange: (rule: ConditionRule) => void;
}) {
    const allConditions = groups.flatMap((g) => g.conditions);
    const selected = allConditions.find((c) => c.value === rule.type);

    return (
        <>
            <Select
                value={rule.type || undefined}
                onValueChange={(v) => onChange({ ...rule, type: v, value: '' })}
            >
                <SelectTrigger className="flex-1 min-w-0">
                    <SelectValue placeholder={__('Select condition…', 'elemacy')} />
                </SelectTrigger>
                <SelectContent>
                    {groups.map((group) => (
                        <SelectGroup key={group.type}>
                            <SelectLabel>{group.label}</SelectLabel>
                            {group.conditions.map((c) => (
                                <SelectItem
                                    key={c.value}
                                    value={c.value}
                                    disabled={c.is_mock}
                                    title={
                                        c.is_mock
                                            ? __('Provided by an extension that is not active', 'elemacy')
                                            : undefined
                                    }
                                >
                                    <span className="flex items-center gap-2">
                                        {c.label}
                                        {c.is_mock && (
                                            <Lock className="size-3 text-muted-foreground" aria-hidden />
                                        )}
                                    </span>
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    ))}
                </SelectContent>
            </Select>

            {selected?.value_type === 'select' && (
                <SubValueSelect condition={selected} rule={rule} onChange={onChange} />
            )}

            {selected?.value_type === 'search' && (
                <AsyncCombobox
                    condition={selected.value}
                    value={rule.value}
                    onChange={(value) => onChange({ ...rule, value })}
                />
            )}
        </>
    );
}

function SubValueSelect({
    condition,
    rule,
    onChange,
}: {
    condition: ConditionType;
    rule: ConditionRule;
    onChange: (rule: ConditionRule) => void;
}) {
    return (
        <Select
            value={rule.value === '' ? ANY_VALUE : rule.value}
            onValueChange={(v) => onChange({ ...rule, value: v === ANY_VALUE ? '' : v })}
        >
            <SelectTrigger className="flex-1 min-w-0">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ANY_VALUE}>{__('Any', 'elemacy')}</SelectItem>
                {condition.sub_values.map((sv) => (
                    <SelectItem key={sv.value} value={sv.value}>
                        {sv.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function RemoveButton({ onClick }: { onClick: () => void }) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onClick}
            aria-label={__('Remove condition', 'elemacy')}
            className="text-muted-foreground hover:text-destructive"
        >
            ×
        </Button>
    );
}
