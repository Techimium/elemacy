import { useEffect, useRef } from "react";
import { useInView } from "@/hooks/use-in-view";
import Spinner from "@/components/spinner";

interface InfiniteScrollSentinelProps {
    onLoadMore: () => void;
    hasMore: boolean;
    isLoading: boolean;
}

/**
 * An invisible sentinel that requests the next page as it scrolls into view and
 * shows a spinner while the fetch is in flight. Renders nothing once the list is
 * fully loaded.
 */
export function InfiniteScrollSentinel({ onLoadMore, hasMore, isLoading }: InfiniteScrollSentinelProps) {
    const { ref, inView } = useInView<HTMLDivElement>();

    // `onLoadMore` (react-query's fetchNextPage) is not reference-stable across
    // renders, so it's read via a ref rather than listed as a dependency —
    // otherwise an unrelated re-render (e.g. typing in the search box) while the
    // sentinel happens to be in view would re-fire this effect and load an extra
    // page.
    const onLoadMoreRef = useRef(onLoadMore);
    onLoadMoreRef.current = onLoadMore;

    useEffect(() => {
        if (inView && hasMore && !isLoading) {
            onLoadMoreRef.current();
        }
    }, [inView, hasMore, isLoading]);

    if (!hasMore && !isLoading) {
        return null;
    }

    return (
        <div ref={ref} className="flex justify-center py-6">
            {isLoading && <Spinner />}
        </div>
    );
}
