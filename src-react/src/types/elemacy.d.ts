import type * as React from 'react';
import type * as ReactDOM from 'react-dom/client';
import type * as ElemacyComponents from '../components/ui';
import type { toast } from 'sonner';
import type { Module } from '@/schemas/module';

export interface Elemacy {
    api_base: string;
    nonce: string;
    adminUrl: string;
    modules: Module[];
}

export interface ElemacyShared {
    React: typeof React;
    ReactDOM: typeof ReactDOM;
    components: typeof ElemacyComponents;
    toast: typeof toast;
    registry: typeof import('../lib/registry').registry;
    routes: typeof import('../lib/route-registry').routeRegistry;
}

declare global {
    interface Window {
        elemacy: Elemacy;
        ElemacyShared: ElemacyShared;
    }
}
