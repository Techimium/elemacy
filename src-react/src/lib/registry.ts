import type { ComponentType } from 'react';
import type { SlotName, SlotPropsMap } from '@/lib/slots';

type AnyFill = ComponentType<Record<string, unknown>>;

const fills = new Map<SlotName, AnyFill[]>();
const listeners = new Map<SlotName, Set<() => void>>();
const EMPTY: readonly AnyFill[] = [];

function notify(slot: SlotName): void {
    listeners.get(slot)?.forEach(fn => fn());
}

function register<N extends SlotName>(slot: N, component: ComponentType<SlotPropsMap[N]>): void {
    const fill = component as AnyFill;
    const existing = fills.get(slot) ?? [];
    if (existing.includes(fill)) {
        console.warn(`[Registry] Component already registered in slot "${slot}"`);
        return;
    }

    fills.set(slot, [...existing, fill]);
    notify(slot);
}

function unregister<N extends SlotName>(slot: N, component: ComponentType<SlotPropsMap[N]>): void {
    const existing = fills.get(slot);
    if (!existing) return;

    const filtered = existing.filter(c => c !== (component as AnyFill));
    if (filtered.length === 0) {
        fills.delete(slot);
    } else {
        fills.set(slot, filtered);
    }

    notify(slot);
}

function get<N extends SlotName>(slot: N): readonly ComponentType<SlotPropsMap[N]>[] {
    return (fills.get(slot) ?? EMPTY) as readonly ComponentType<SlotPropsMap[N]>[];
}

function subscribe(slot: SlotName, fn: () => void): () => void {
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

function has(slot: SlotName): boolean {
    return fills.has(slot) && fills.get(slot)!.length > 0;
}

function clear(slot?: SlotName): void {
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
