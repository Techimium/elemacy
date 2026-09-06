import { useEffect, useRef, useState } from "react";

/**
 * Reports whether the returned ref's element is intersecting the viewport. Drives
 * infinite-scroll "load more" with a native IntersectionObserver — no dependency.
 * `rootMargin` pre-triggers before the element is fully visible.
 */
export function useInView<T extends Element = HTMLDivElement>(rootMargin = "200px") {
    const ref = useRef<T | null>(null);
    const [inView, setInView] = useState(false);

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => setInView(entry.isIntersecting),
            { rootMargin }
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [rootMargin]);

    return { ref, inView };
}
