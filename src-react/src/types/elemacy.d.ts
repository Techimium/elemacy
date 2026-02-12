export interface Elemacy {
    api_base: string;
    nonce: string;
    adminUrl: string;
    templateTypes: { value: string; label: string }[];
}

declare global {
    interface Window {
        elemacy: Elemacy;
    }
}