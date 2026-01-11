import type { Template } from "@/features/theme-builder/schemas/template";

const getHeaders = () => {
    return {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.elemacy.nonce,
    };
};

export const fetchTemplates = async (): Promise<Template[]> => {
    const response = await fetch(window.elemacy.api_base + 'theme-builder/templates', {
        headers: {
            'X-WP-Nonce': window.elemacy.nonce,
        }
    });
    if (!response.ok) {
        throw new Error('Failed to fetch templates');
    }
    const data = await response.json();
    return data.data; // Assuming the API returns the array directly or we might need data.data
};

export const createTemplate = async (template: Omit<Template, 'id' | 'author'>): Promise<Template> => {
    const response = await fetch(window.elemacy.api_base + 'theme-builder/templates', {
        method: 'POST',
        headers: getHeaders(),
        body: JSON.stringify(template),
    });
    if (!response.ok) {
        throw new Error('Failed to create template');
    }
    return response.json();
};

export const updateTemplate = async (id: number, template: Partial<Template>): Promise<Template> => {
    const response = await fetch(window.elemacy.api_base + `theme-builder/templates/${id}`, {
        method: 'PUT',
        headers: getHeaders(),
        body: JSON.stringify(template),
    });
    if (!response.ok) {
        throw new Error('Failed to update template');
    }
    return response.json();
};

export const deleteTemplate = async (id: number): Promise<void> => {
    const response = await fetch(window.elemacy.api_base + `theme-builder/templates/${id}`, {
        method: 'DELETE',
        headers: {
            'X-WP-Nonce': window.elemacy.nonce,
        },
    });
    if (!response.ok) {
        throw new Error('Failed to delete template');
    }
};
