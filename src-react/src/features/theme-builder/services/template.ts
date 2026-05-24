import type { CreateTemplate, Template, UpdateTemplate } from "@/features/theme-builder/schemas/template";
import { apiClient } from "@/lib/api";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

const fetchTemplates = async (): Promise<Template[]> => {
    const response = await apiClient.get('theme-builder/templates');
    return response.data?.data;
};

export const useTemplates = () => {
    return useQuery({
        queryKey: ['templates'],
        queryFn: fetchTemplates,
    });
};

const createTemplate = async (template: CreateTemplate): Promise<Template> => {
    const response = await apiClient.post('theme-builder/templates', template);
    return response.data?.data;
};

export const useCreateTemplateMutation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: createTemplate,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['templates'] });
        }
    });
};

const updateTemplate = async ({ id, ...template }: UpdateTemplate & { id: number }): Promise<Template> => {
    const response = await apiClient.put(`theme-builder/templates/${id}`, template);
    return response.data?.data;
};

export const useUpdateTemplateMutation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: updateTemplate,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['templates'] });
        }
    });
};

const duplicateTemplate = async (id: number): Promise<Template> => {
    const response = await apiClient.post(`theme-builder/templates/${id}/duplicate`);
    return response.data?.data;
};

export const useDuplicateTemplateMutation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: duplicateTemplate,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['templates'] });
        }
    });
};

const deleteTemplate = async (id: number): Promise<void> => {
    const response = await apiClient.delete(`theme-builder/templates/${id}`);
    return response.data?.data;
};

export const useDeleteTemplateMutation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: deleteTemplate,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['templates'] });
        }
    });
};
