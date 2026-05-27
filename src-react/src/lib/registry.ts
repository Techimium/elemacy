import type { ComponentType } from 'react';

type SlotFill<P = Record<string, unknown>> = ComponentType<P>;

const fills = new Map<string, SlotFill[]>();
const listeners = new Map<string, Set<() => void>>();
const EMPTY: readonly SlotFill[] = [];

function notify(slot: string): void {
    listeners.get(slot)?.forEach(fn => fn());
}

function register(slot: string, component: SlotFill): void {
    const existing = fills.get(slot) ?? [];
    if (existing.includes(component)) {
        console.warn(`[Registry] Component already registered in slot "${slot}"`);
        return;
    }

    fills.set(slot, [...existing, component]);
    notify(slot);
}

function unregister(slot: string, component: SlotFill): void {
    const existing = fills.get(slot);
    if (!existing) return;

    const filtered = existing.filter(c => c !== component);
    if (filtered.length === 0) {
        fills.delete(slot);
    } else {
        fills.set(slot, filtered);
    }

    notify(slot);
}

function get(slot: string): readonly SlotFill[] {
    return fills.get(slot) ?? EMPTY;
}

function subscribe(slot: string, fn: () => void): () => void {
    if (!listeners.has(slot)) listeners.set(slot, new Set());
    listeners.get(slot)!.add(fn);

    return () => {
        const slotListeners = listeners.get(slot);
        if (!slotListeners) return;

        slotListeners.delete(fn);
        if (slotListeners.size === 0) {
            listeners.delete(slot);
        }
    };
}

function has(slot: string): boolean {
    return fills.has(slot) && fills.get(slot)!.length > 0;
}

function clear(slot?: string): void {
    if (slot) {
        fills.delete(slot);
        notify(slot);
        return;
    }

    fills.clear();
    listeners.forEach((_, slotName) => notify(slotName));
}

export const registry = {
    register,
    unregister,
    get,
    subscribe,
    has,
    clear,
    notify,
};
