import {
    PopupSchema,
    type CreatePopup,
    type Popup,
    type PopupListItem,
    type UpdatePopup,
} from '@/features/popups/schemas/popup';
import { apiClient, parseResponse } from '@/lib/api';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

const POPUPS_QUERY_KEY = ['popups'] as const;

const fetchPopups = async (): Promise<PopupListItem[]> => {
    const response = await apiClient.get('popups');
    return response.data?.data ?? [];
};

export const usePopups = () => {
    return useQuery({
        queryKey: POPUPS_QUERY_KEY,
        queryFn: fetchPopups,
    });
};

const fetchPopup = async (id: number): Promise<Popup> => {
    const response = await apiClient.get(`popups/${id}`);
    return parseResponse(PopupSchema, response.data?.data);
};

export const usePopup = (id: number | null | undefined) => {
    return useQuery({
        queryKey: ['popup', id],
        queryFn: () => fetchPopup(id as number),
        enabled: typeof id === 'number',
    });
};

const createPopup = async (popup: CreatePopup): Promise<Popup> => {
    const response = await apiClient.post('popups', popup);
    return parseResponse(PopupSchema, response.data?.data);
};

export const useCreatePopupMutation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: createPopup,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: POPUPS_QUERY_KEY });
        },
    });
};

const updatePopup = async ({ id, ...popup }: UpdatePopup & { id: number }): Promise<Popup> => {
    const response = await apiClient.put(`popups/${id}`, popup);
    return parseResponse(PopupSchema, response.data?.data);
};

export const useUpdatePopupMutation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: updatePopup,
        onSuccess: (_data, variables) => {
            queryClient.invalidateQueries({ queryKey: POPUPS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: ['popup', variables.id] });
        },
    });
};

const duplicatePopup = async (id: number): Promise<Popup> => {
    const response = await apiClient.post(`popups/${id}/duplicate`);
    return parseResponse(PopupSchema, response.data?.data);
};

export const useDuplicatePopupMutation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: duplicatePopup,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: POPUPS_QUERY_KEY });
        },
    });
};

const deletePopup = async (id: number): Promise<void> => {
    const response = await apiClient.delete(`popups/${id}`);
    return response.data?.data;
};

export const useDeletePopupMutation = () => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: deletePopup,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: POPUPS_QUERY_KEY });
        },
    });
};
