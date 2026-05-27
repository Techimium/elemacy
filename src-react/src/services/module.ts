import { apiClient } from "@/lib/api";
import type { Module } from "@/schemas/module";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

const MODULES_QUERY_KEY = ['modules'] as const;

const fetchModules = async (): Promise<Module[]> => {
    const response = await apiClient.get(`modules`);
    return response.data?.data ?? [];
};

export const useModulesQuery = () => {
    return useQuery({
        queryKey: MODULES_QUERY_KEY,
        queryFn: fetchModules,
        initialData: () => window.elemacy.modules,
        staleTime: Infinity,
    });
};

export const useModuleQuery = ({ name }: { name: string }) => {
    return useQuery({
        queryKey: MODULES_QUERY_KEY,
        queryFn: fetchModules,
        initialData: () => window.elemacy.modules,
        staleTime: Infinity,
        select: (modules) => modules.find((module) => module.name === name),
    });
};

const toggleModule = async ({ isEnabled, name }: { isEnabled: boolean; name: string }): Promise<void> => {
    const action = isEnabled ? 'enable' : 'disable';
    const response = await apiClient.put(`modules/${name}`, { action });
    return response.data?.data;
};

export const useToggleModuleMutation = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: toggleModule,
        onMutate: async ({ isEnabled, name }) => {
            await queryClient.cancelQueries({ queryKey: MODULES_QUERY_KEY });
            const previousModules = queryClient.getQueryData<Module[]>(MODULES_QUERY_KEY);

            queryClient.setQueryData<Module[]>(MODULES_QUERY_KEY, (modules) =>
                modules?.map((module) =>
                    module.name === name ? { ...module, is_active: isEnabled } : module
                )
            );

            return { previousModules };
        },

        onError: (_err, _variables, context) => {
            if (context?.previousModules) {
                queryClient.setQueryData(MODULES_QUERY_KEY, context.previousModules);
            }
        },

        onSettled: () => {
            queryClient.invalidateQueries({ queryKey: MODULES_QUERY_KEY });
        },
    });
};
