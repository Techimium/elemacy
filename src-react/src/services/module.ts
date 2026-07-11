import { apiClient } from "@/lib/api";
import type { Module } from "@/schemas/module";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { __ } from "@wordpress/i18n";
import { toast } from "sonner";

const MODULES_QUERY_KEY = ['modules'] as const;

const RELOAD_TOAST_ID = "elemacy-module-reload";

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

        onSuccess: () => {
            toast.warning(__("Module settings updated.", "elemacy"), {
                id: RELOAD_TOAST_ID,
                description: __("Reload the page to apply the changes and update menus.", "elemacy"),
                duration: Infinity,
                action: {
                    label: __("Reload", "elemacy"),
                    onClick: () => window.location.reload(),
                },
                cancel: {
                    label: __("Dismiss", "elemacy"),
                    onClick: () => {},
                },
            });
        },

        onSettled: () => {
            queryClient.invalidateQueries({ queryKey: MODULES_QUERY_KEY });
        },
    });
};
