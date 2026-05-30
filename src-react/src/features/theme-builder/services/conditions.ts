import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api';
import type { ConditionGroup, ConditionSubValue } from '@/features/theme-builder/schemas/condition';

const fetchConditionTypes = async (templateType?: string): Promise<ConditionGroup[]> => {
    const response = await apiClient.get('conditions/types', {
        params: templateType ? { template_type: templateType } : undefined,
    });
    return response.data?.data;
};

export const useConditionTypes = (templateType?: string) => {
    return useQuery({
        queryKey: ['condition-types', templateType ?? ''],
        queryFn: () => fetchConditionTypes(templateType),
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
 * display it when an existing template is reopened.
 */
export const useConditionValueLabel = (condition: string, value: string) => {
    return useQuery({
        queryKey: ['condition-value-label', condition, value],
        queryFn: () => fetchConditionSearch({ condition, include: value }),
        enabled: condition !== '' && value !== '',
    });
};
