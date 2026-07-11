import {
    BlockTemplateSchema,
    type BlockTemplate,
    type BlockTemplateType,
    type CreateBlockTemplate,
    type UpdateBlockTemplate,
} from "@/features/library/schemas/block-template";
import { apiClient, parseResponse } from "@/lib/api";
import { parsePage } from "@/lib/pagination";
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

const BLOCK_TEMPLATES_PER_PAGE = 20;

export type BlockTemplateFilters = { search?: string; type?: string };

const fetchBlockTemplates = async (filters: BlockTemplateFilters, page: number) => {
    const response = await apiClient.get('library/templates', {
        params: { search: filters.search, type: filters.type, page, per_page: BLOCK_TEMPLATES_PER_PAGE },
    });
    return parsePage(BlockTemplateSchema, response.data?.data);
};

export const useBlockTemplates = (filters: BlockTemplateFilters) => {
    return useInfiniteQuery({
        queryKey: ['block-templates', filters],
        queryFn: ({ pageParam }) => fetchBlockTemplates(filters, pageParam),
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

const fetchBlockTemplateTypes = async (): Promise<BlockTemplateType[]> => {
    const response = await apiClient.get('library/template-types');
    return response.data?.data;
};

export const useBlockTemplateTypes = () => {
    return useQuery({
        queryKey: ['block-template-types'],
        queryFn: fetchBlockTemplateTypes,
        staleTime: Infinity,
    });
};

const createBlockTemplate = async (template: CreateBlockTemplate): Promise<BlockTemplate> => {
    const response = await apiClient.post('library/templates', template);
    return parseResponse(BlockTemplateSchema, response.data?.data);
};

export const useCreateBlockTemplateMutation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: createBlockTemplate,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['block-templates'] });
        }
    });
};

const updateBlockTemplate = async ({ id, ...template }: UpdateBlockTemplate & { id: number }): Promise<BlockTemplate> => {
    const response = await apiClient.put(`library/templates/${id}`, template);
    return parseResponse(BlockTemplateSchema, response.data?.data);
};

export const useUpdateBlockTemplateMutation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: updateBlockTemplate,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['block-templates'] });
        }
    });
};

const duplicateBlockTemplate = async (id: number): Promise<BlockTemplate> => {
    const response = await apiClient.post(`library/templates/${id}/duplicate`);
    return parseResponse(BlockTemplateSchema, response.data?.data);
};

export const useDuplicateBlockTemplateMutation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: duplicateBlockTemplate,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['block-templates'] });
        }
    });
};

const deleteBlockTemplate = async (id: number): Promise<void> => {
    const response = await apiClient.delete(`library/templates/${id}`);
    return response.data?.data;
};

export const useDeleteBlockTemplateMutation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: deleteBlockTemplate,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['block-templates'] });
        }
    });
};
