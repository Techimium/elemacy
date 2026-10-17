<?php

namespace Elemacy\Loop\Pagination;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Pagination\DTO\PublicLoopRequest;

/** Shared bounds for public source policies and their initial paginated output. */
final class PublicLoopLimits
{
    public const MAX_ITEMS = 100;

    /**
     * @param mixed $value Saved count; zero/empty/unlimited becomes the bounded maximum.
     * @param int   $fallback Default when the setting is absent.
     * @return int
     * @throws ValidationException On malformed saved data.
     */
    public static function page_size($value, int $fallback = 6): int
    {
        if (null === $value) {
            $value = $fallback;
        }
        if ('' === $value) {
            return self::MAX_ITEMS;
        }
        if (!is_numeric($value) || !is_finite((float) $value)) {
            throw self::invalid_settings();
        }
        if ((float) $value <= 0 || (float) $value >= self::MAX_ITEMS) {
            return self::MAX_ITEMS;
        }
        return max(1, (int) $value);
    }

    /**
     * @param int $page One-based page.
     * @return void
     * @throws ValidationException On invalid page bounds.
     */
    public static function assert_page(int $page): void
    {
        if ($page < 1 || $page > PublicLoopRequest::MAX_PAGE) {
            throw self::invalid_settings();
        }
    }

    /**
     * @param array $settings Persisted settings.
     * @param array $keys Fields whose native controls store scalars.
     * @return void
     * @throws ValidationException On arrays/objects in scalar settings.
     */
    public static function assert_scalars(array $settings, array $keys): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $settings) && !is_scalar($settings[$key]) && null !== $settings[$key]) {
                throw self::invalid_settings();
            }
        }
    }

    /**
     * Whether pages after the first load in place: the AJAX switch is on, or
     * the pagination type is incremental (Load More / Infinite Scroll), which
     * always loads in place.
     *
     * @param array $settings Raw author settings.
     * @return bool
     */
    public static function is_ajax_paginated(array $settings): bool
    {
        return 'yes' === ($settings['pagination_ajax'] ?? '')
            || PaginationType::is_incremental($settings['pagination_type'] ?? '');
    }

    /**
     * For an AJAX-paginated widget, bounds its saved "how many" setting so its
     * classic-rendered first page agrees with the effective maximum its later
     * AJAX-loaded pages already enforce.
     *
     * @param array  $settings Raw author settings (get_settings_for_display()).
     * @param string $key The source's own "count" setting key.
     * @return array $settings with $key bounded, unchanged otherwise.
     */
    public static function bound_ajax_page_size(array $settings, string $key): array
    {
        if (isset($settings[$key]) && self::is_ajax_paginated($settings)) {
            try {
                $settings[$key] = self::page_size($settings[$key]);
            } catch (ValidationException $error) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Deliberate, see below.
                // Malformed authored value: leave it for the source's own
                // validation to handle exactly as before this change.
            }
        }

        return $settings;
    }

    /** @return ValidationException Client-safe invalid authoring-data error. */
    public static function invalid_settings(): ValidationException
    {
        return ValidationException::with_errors(
            ['code' => 'elemacy_loop_invalid_definition'],
            esc_html__('The saved loop settings are not available.', 'elemacy')
        );
    }
}
