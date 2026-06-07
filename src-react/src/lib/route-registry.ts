import type { ComponentType } from 'react';

export interface RegisteredRoute {
    path: string;
    component: ComponentType;
}

const routes = new Map<string, ComponentType>();
const listeners = new Set<() => void>();
let snapshot: RegisteredRoute[] = [];

function rebuildSnapshot(): void {
    snapshot = Array.from(routes, ([path, component]) => ({ path, component }));
}

function notify(): void {
    listeners.forEach(fn => fn());
}

function register(path: string, component: ComponentType): void {
    if (routes.has(path)) {
        console.warn(`[RouteRegistry] A route is already registered for path "${path}"`);
        return;
    }

    routes.set(path, component);
    rebuildSnapshot();
    notify();
}

function unregister(path: string): void {
    if (!routes.delete(path)) return;

    rebuildSnapshot();
    notify();
}

function getAll(): RegisteredRoute[] {
    return snapshot;
}

function subscribe(fn: () => void): () => void {
    listeners.add(fn);
    return () => {
        listeners.delete(fn);
    };
}

export const routeRegistry = {
    register,
    unregister,
    getAll,
    subscribe,
};
