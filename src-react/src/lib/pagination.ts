import { z } from "zod";

/** Pagination totals returned alongside a page of results, under the response `data`. */
export const PaginationSchema = z.object({
    total: z.number(),
    total_pages: z.number(),
    page: z.number(),
    per_page: z.number(),
});

export type Pagination = z.infer<typeof PaginationSchema>;

export interface Page<T> {
    results: T[];
    pagination: Pagination;
}

/**
 * Validate a paginated `{ results, pagination }` payload (the envelope's `data`
 * object) against the item schema, surfacing a clean error on a shape mismatch —
 * the paginated counterpart of `parseResponse` in `lib/api.ts`. Not implemented
 * by composing `parseResponse` directly: under the installed Zod v4, passing a
 * schema built inside a generic function through `parseResponse`'s own generic
 * loses the type parameter and collapses to `unknown` at every call site.
 */
export function parsePage<T>(itemSchema: z.ZodType<T>, payload: unknown): Page<T> {
    const schema = z.object({
        results: z.array(itemSchema),
        pagination: PaginationSchema,
    });

    const result = schema.safeParse(payload);

    if (!result.success) {
        console.error("[Elemacy] Unexpected paginated response shape:", result.error.issues, payload);
        throw new Error("Unexpected response from the server.");
    }

    return result.data;
}
