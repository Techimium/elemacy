import { useSyncExternalStore, useCallback, type ReactNode } from 'react';
import { registry } from '@/lib/registry';
import type { SlotName, SlotPropsMap } from '@/lib/slots';

interface SlotProps<N extends SlotName> {
    name: N;
    slotProps?: SlotPropsMap[N];
    fallback?: ReactNode;
}

export function Slot<N extends SlotName>({
    name,
    slotProps,
    fallback = null,
}: SlotProps<N>) {
    const subscribe = useCallback(
        (fn: () => void) => registry.subscribe(name, fn),
        [name]
    );

    const fills = useSyncExternalStore(subscribe, () => registry.get(name));

    if (!fills.length) return <>{fallback}</>;

    return (
        <>
            {fills.map((Fill, i) => <Fill key={i} {...(slotProps as SlotPropsMap[N])} />)}
        </>
    );
}
