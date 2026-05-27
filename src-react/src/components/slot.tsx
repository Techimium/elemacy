import { useSyncExternalStore, useCallback, type ReactNode } from 'react';
import { registry } from '@/lib/registry';

interface SlotProps<T extends Record<string, unknown>> {
    name: string;
    slotProps?: T;
    fallback?: ReactNode;
}

export function Slot<T extends Record<string, unknown>>({
    name,
    slotProps = {} as T,
    fallback = null,
}: SlotProps<T>) {
    const subscribe = useCallback(
        (fn: () => void) => registry.subscribe(name, fn),
        [name]
    );

    const fills = useSyncExternalStore(subscribe, () => registry.get(name));

    if (!fills.length) return <>{fallback}</>;

    return (
        <>
            {fills.map((Fill, i) => <Fill key={i} {...slotProps} />)}
        </>
    );
}
