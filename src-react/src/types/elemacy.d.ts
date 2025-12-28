export interface Elemacy {
    api_base: string;
    nonce: string;
    adminUrl: string;
}

declare global {
    interface Window {
        elemacy: Elemacy;
    }
}