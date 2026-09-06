import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api';
import type { ConditionGroup, ConditionSubValue } from '@/schemas/condition';

/**
 * Scopes the condition-types request. `context` (e.g. `popup`) and
 * `template_type` (e.g. `header`) are both understood by the PHP controller;
 * callers pass whichever applies to their feature.
 */
export interface ConditionTypesArgs {
    context?: string;
    templateType?: string;
}

/** Accepts either the options object or a bare template-type string. */
type ConditionTypesInput = ConditionTypesArgs | string | undefined;

const normalizeArgs = (input: ConditionTypesInput): ConditionTypesArgs => {
    if (typeof input === 'string') {
        return { templateType: input };
    }
    return input ?? {};
};

const fetchConditionTypes = async (args: ConditionTypesArgs): Promise<ConditionGroup[]> => {
    const params: Record<string, string> = {};
    if (args.context) params.context = args.context;
    if (args.templateType) params.template_type = args.templateType;

    const response = await apiClient.get('conditions/types', {
        params: Object.keys(params).length ? params : undefined,
    });
    return response.data?.data;
};

export const useConditionTypes = (input?: ConditionTypesInput) => {
    const args = normalizeArgs(input);
    return useQuery({
        queryKey: ['condition-types', args.context ?? '', args.templateType ?? ''],
        queryFn: () => fetchConditionTypes(args),
    });
};

interface ConditionSearchParams {
    condition: string;
    q?: string;
    include?: string;
}

const fetchConditionSearch = async (params: ConditionSearchParams): Promise<ConditionSubValue[]> => {
    const response = await apiClient.get('conditions/search', { params });
    return response.data?.data ?? [];
};

/** Options matching the typed query for a `search`-type condition's value picker. */
export const useConditionSearch = (condition: string, query: string, enabled = true) => {
    return useQuery({
        queryKey: ['condition-search', condition, query],
        queryFn: () => fetchConditionSearch({ condition, q: query }),
        enabled: enabled && condition !== '',
    });
};

/**
 * Resolves the label for an already-saved value (by id) so the combobox can
 * display it when an existing record is reopened.
 */
export const useConditionValueLabel = (condition: string, value: string) => {
    return useQuery({
        queryKey: ['condition-value-label', condition, value],
        queryFn: () => fetchConditionSearch({ condition, include: value }),
        enabled: condition !== '' && value !== '',
    });
};
