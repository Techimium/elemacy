import {
    PopupListItemSchema,
    PopupSchema,
    type CreatePopup,
    type Popup,
    type UpdatePopup,
} from '@/features/popups/schemas/popup';
import { apiClient, parseResponse } from '@/lib/api';
import { parsePage } from '@/lib/pagination';
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

const POPUPS_QUERY_KEY = ['popups'] as const;
const POPUPS_PER_PAGE = 20;

export type PopupFilters = { search?: string; type?: string };

const fetchPopups = async (filters: PopupFilters, page: number) => {
    const response = await apiClient.get('popups', {
        params: { search: filters.search, type: filters.type, page, per_page: POPUPS_PER_PAGE },
    });
    return parsePage(PopupListItemSchema, response.data?.data);
};

export const usePopups = (filters: PopupFilters) => {
    return useInfiniteQuery({
        queryKey: [...POPUPS_QUERY_KEY, filters],
        queryFn: ({ pageParam }) => fetchPopups(filters, pageParam),
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
