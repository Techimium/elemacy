import { TemplateSchema, type CreateTemplate, type Template, type TemplateType, type UpdateTemplate } from "@/features/theme-builder/schemas/template";
import { apiClient, parseResponse } from "@/lib/api";
import { parsePage } from "@/lib/pagination";
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

const TEMPLATES_PER_PAGE = 20;

export type TemplateFilters = { search?: string; type?: string };

const fetchTemplates = async (filters: TemplateFilters, page: number) => {
    const response = await apiClient.get('theme-builder/templates', {
        params: { search: filters.search, type: filters.type, page, per_page: TEMPLATES_PER_PAGE },
    });
    return parsePage(TemplateSchema, response.data?.data);
};

export const useTemplates = (filters: TemplateFilters) => {
    return useInfiniteQuery({
        queryKey: ['templates', filters],
        queryFn: ({ pageParam }) => fetchTemplates(filters, pageParam),
        initialPageParam: 1,
        getNextPageParam: (last) =>
            last.pagination.page < last.pagination.total_pages ? last.pagination.page + 1 : undefined,
        // Keep only the previous filter's first page as a placeholder (not every
        // page scrolled to) — avoids a full-height spinner on every keystroke
        // without flashing dozens of stale, filter-mismatched cards.
        placeholderData: (previousData) =>
            previousData
                ? { pages: previousData.pages.slice(0, 1), pageParams: previousData.pageParams.slice(0, 1) }
                : previousData,
    });
};

const fetchTemplateTypes = async (): Promise<TemplateType[]> => {
    const response = await apiClient.get('theme-builder/template-types');
    return response.data?.data;
};

export const useTemplateTypes = () => {
    return useQuery({
        queryKey: ['template-types'],
        queryFn: fetchTemplateTypes,
        staleTime: Infinity,
    });
};

const createTemplate = async (template: CreateTemplate): Promise<Template> => {
    const response = await apiClient.post('theme-builder/templates', template);
    return parseResponse(TemplateSchema, response.data?.data);
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
    return parseResponse(TemplateSchema, response.data?.data);
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
    return parseResponse(TemplateSchema, response.data?.data);
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
