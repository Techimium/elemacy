import type { Template } from "@/features/theme-builder/schemas/template";
import { apiClient } from "@/lib/api";

export const fetchTemplates = async (): Promise<Template[]> => {
    const response = await apiClient.get('/theme-builder/templates');
    return response.data;
};

export const createTemplate = async (template: Omit<Template, 'id' | 'author'>): Promise<Template> => {
    const response = await apiClient.post('/theme-builder/templates', template);
    return response.data;
};

export const updateTemplate = async (id: number, template: Partial<Template>): Promise<Template> => {
    const response = await apiClient.put(`/theme-builder/templates/${id}`, template);
    return response.data;
};

export const deleteTemplate = async (id: number): Promise<void> => {
    const response = await apiClient.delete(`/theme-builder/templates/${id}`);
    return response.data;
};
