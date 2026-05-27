import type * as React from 'react';
import type * as ReactDOM from 'react-dom/client';
import type * as ElemacyComponents from '../components/ui';
import type { toast } from 'sonner';
import type { registry } from '@/lib/registry';
import type { Module } from '@/schemas/module';

export interface Elemacy {
    api_base: string;
    nonce: string;
    adminUrl: string;
    templateTypes: { value: string; label: string }[];
    modules: Module[];
}

export interface ElemacyShared {
    React: typeof React;
    ReactDOM: typeof ReactDOM;
    components: typeof ElemacyComponents;
    toast: typeof toast;
    registry: typeof registry;
}

declare global {
    interface Window {
        elemacy: Elemacy;
        ElemacyShared: ElemacyShared;
    }
}
