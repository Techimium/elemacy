<?php

namespace Elemacy\Core\Multisite;

defined('ABSPATH') || exit;

/**
 * Iterates every site in a WordPress Multisite network in bounded pages,
 * switching into each site only for the duration of a supplied callback and
 * always restoring the prior blog context via `finally` — even if the callback
 * throws.
 */
class SiteBatchIterator
{
    /**
     * Calls $callback(int $site_id) for up to $limit sites starting at
     * $offset (site-ID query order — stable across calls as long as no site
     * is created/deleted between them), wrapping each call in
     * switch_to_blog()/restore_current_blog().
     *
     * @param callable(int): void $callback
     * @return array{processed: int, has_more: bool} How many sites this call
     *         actually processed, and whether more remain beyond $offset +
     *         processed (so the caller knows whether to schedule another
     *         batch rather than needing a separate count query).
     */
    public function process_batch(callable $callback, int $offset, int $limit): array
    {
        if ($limit < 1) {
            return [
                'processed' => 0,
                'has_more'  => false,
            ];
        }

        // One extra beyond $limit reveals whether more sites remain without
        // a second, separate COUNT query.
        $site_ids = get_sites([
            'fields' => 'ids',
            'number' => $limit + 1,
            'offset' => $offset,
        ]);

        $has_more = count($site_ids) > $limit;
        $site_ids = array_slice($site_ids, 0, $limit);

        foreach ($site_ids as $site_id) {
            switch_to_blog((int) $site_id);

            try {
                $callback((int) $site_id);
            } finally {
                restore_current_blog();
            }
        }

        return [
            'processed' => count($site_ids),
            'has_more'  => $has_more,
        ];
    }
}
