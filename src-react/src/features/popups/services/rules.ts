import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api';
import type { RuleType } from '@/features/popups/schemas/trigger-rule-types';

const fetchRuleTypes = async (): Promise<RuleType[]> => {
    const response = await apiClient.get('popups/rules');
    return response.data?.data ?? [];
};

/** Available advanced-rule types (with their param schemas) for the Rules panel. */
export const useRuleTypes = () => {
    return useQuery({
        queryKey: ['popup-rule-types'],
        queryFn: fetchRuleTypes,
        staleTime: Infinity,
    });
};
