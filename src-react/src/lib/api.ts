import axios from "axios";
import { toast } from "sonner";
import type { z } from "zod";

const apiClient = axios.create({
    baseURL: window.elemacy.api_base,
});

/**
 * Validate an API payload against its schema at the boundary. A shape mismatch
 * is surfaced as a clean error (and logged for debugging) instead of crashing
 * deeper in the UI on an unexpected `undefined`.
 */
function parseResponse<T>(schema: z.ZodType<T>, data: unknown): T {
    const result = schema.safeParse(data);

    if (!result.success) {
        console.error("[Elemacy] Unexpected API response shape:", result.error.issues, data);
        throw new Error("Unexpected response from the server.");
    }

    return result.data;
}

apiClient.interceptors.request.use(
    (config) => {
        config.headers["X-WP-Nonce"] = window.elemacy.nonce;
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

apiClient.interceptors.response.use(
    (response) => {
        if (response.data && response.data.message) {
            toast.success(response.data.message);
        }
        return response;
    },
    (error) => {
        const message = error.response?.data?.message || error.message || "An unknown error occurred";
        toast.error(message);
        return Promise.reject(error);
    }
);

export { apiClient, parseResponse };