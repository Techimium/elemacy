import axios from "axios";
import { toast } from "sonner";

const apiClient = axios.create({
    baseURL: window.elemacy.api_base,
});

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

export { apiClient };