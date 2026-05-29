import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api';
import type { ConditionGroup } from '@/features/theme-builder/schemas/condition';

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
