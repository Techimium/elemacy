import { apiClient } from "@/lib/api";
import type { Module } from "@/schemas/module";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

const fetchModule = async ({ name }: { name: string }): Promise<Module> => {
    const response = await apiClient.get(`modules/${name}`);
    return response.data?.data ?? {};
};

export const useModuleQuery = ({ name }: { name: string }) => {
    return useQuery({
        queryKey: ['modules', name],
        queryFn: () => fetchModule({ name }),
    });
};

const toggleModule = async ({ isEnabled, name }: { isEnabled: boolean; name: string }): Promise<void> => {
    const action = isEnabled ? 'disable' : 'enable';
    const response = await apiClient.put(`modules/${name}`, { action });
    return response.data?.data;
};

export const useToggleModuleMutation = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: toggleModule,
        onMutate: async ({ isEnabled, name }) => {
            await queryClient.cancelQueries({ queryKey: ['modules', name] });
            const previousValue = queryClient.getQueryData<Module>(['modules', name]);
            queryClient.setQueryData(['modules', name], { ...previousValue, is_active: !isEnabled });
            return { previousValue, name };
        },

        onError: (_err, variables, context) => {
            if (context) {
                queryClient.setQueryData(['modules', context.name], { ...context.previousValue, is_active: variables.isEnabled });
            }
        },

        onSettled: (_data, _error, variables) => {
            queryClient.invalidateQueries({ queryKey: ['modules', variables.name] });
        },
    });
};