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
    contract_version: string;
}

/** The admin extension bridge's declared capabilities — a
 * consumer feature-detects against this array instead of branching solely
 * on `version`, so a future Free release can add or drop an individual
 * capability without every consumer needing a major version bump. */
export type ElemacyBridgeCapability = 'registry' | 'routes' | 'components' | 'toast';

export interface ElemacyShared {
    /** The bridge contract version (see `Elemacy.contract_version`, its
     * single source of truth) — not `ELEMACY_VERSION`, the plugin release. */
    version: string;
    capabilities: readonly ElemacyBridgeCapability[];
    React: typeof React;
    ReactDOM: typeof ReactDOM;
    components: typeof ElemacyComponents;
    toast: typeof toast;
    registry: typeof import('../lib/registry').registry;
    routes: typeof import('../lib/route-registry').routeRegistry;
}

/** Fired on `window` exactly once, the moment `window.ElemacyShared` is set.
 * A consumer that starts after this already fired (Free finished first)
 * must not wait for it — check `window.ElemacyShared` for existence first,
 * exactly as the "Free is already ready" scenario in
 * specs/pro/admin-extension-contract requires — and only add this listener
 * when it is not yet present. */
export interface ElemacyAdminReadyEventDetail {
    version: string;
    capabilities: readonly ElemacyBridgeCapability[];
}

declare global {
    interface Window {
        elemacy: Elemacy;
        ElemacyShared?: ElemacyShared;
    }

    interface WindowEventMap {
        'elemacy:admin-ready': CustomEvent<ElemacyAdminReadyEventDetail>;
    }
}
