import axios from "axios";

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

export { apiClient };