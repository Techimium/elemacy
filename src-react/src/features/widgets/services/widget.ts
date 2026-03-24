import { apiClient } from "@/lib/api";
import type { Widget } from "@/schemas/widget";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

const fetchWidgets = async (): Promise<Widget[]> => {
    const response = await apiClient.get('widgets');
    return response.data?.data ?? [];
};

export const useWidgetsQuery = () => {
    return useQuery({
        queryKey: ['widgets'],
        queryFn: fetchWidgets,
    });
};

const toggleWidget = async ({ isEnabled, name }: { isEnabled: boolean; name: string }): Promise<void> => {
    const action = isEnabled ? 'enable' : 'disable';
    const response = await apiClient.put(`widgets/${name}/toggle`, { action });
    return response.data?.data;
};

export const useToggleWidgetMutation = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: toggleWidget,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['widgets'] });
        },
    });
};
