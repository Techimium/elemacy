import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api';
import type { TriggerType } from '@/features/popups/schemas/trigger-rule-types';

const fetchTriggerTypes = async (): Promise<TriggerType[]> => {
    const response = await apiClient.get('popups/triggers');
    return response.data?.data ?? [];
};

/** Available trigger types (with their param schemas) for the Triggers panel. */
export const useTriggerTypes = () => {
    return useQuery({
        queryKey: ['popup-trigger-types'],
        queryFn: fetchTriggerTypes,
        staleTime: Infinity,
    });
};
