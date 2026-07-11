import { z } from "zod";
import {
    BlockTemplateSchema,
    type BlockTemplate,
    type BlockTemplateType,
    type CreateBlockTemplate,
    type UpdateBlockTemplate,
} from "@/features/library/schemas/block-template";
import { apiClient, parseResponse } from "@/lib/api";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

const fetchBlockTemplates = async (): Promise<BlockTemplate[]> => {
    const response = await apiClient.get('library/templates');
    return parseResponse(z.array(BlockTemplateSchema), response.data?.data ?? []);
};

export const useBlockTemplates = () => {
    return useQuery({
        queryKey: ['block-templates'],
        queryFn: fetchBlockTemplates,
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
