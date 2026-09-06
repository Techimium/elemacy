<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Modules\Popups\DTO\PopupDTO;
use Elemacy\Modules\Popups\DTO\RuleDTO;
use Elemacy\TemplateLibrary\TemplateResolver;

/**
 * Resolves which published popups match the current request based on their
 * display conditions.
 */
class PopupDisplayResolver
{
    protected static ?self $instance = null;

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Return every published popup whose display conditions match the current
     * request.
     *
     * A popup with no conditions is treated as global (shows everywhere).
     * Otherwise the shared condition engine decides (include-OR / exclude-AND /
     * no-include = show everywhere).
     *
     * @return PopupDTO[]
     */
    public function resolve_all(): array
    {
        // The shared resolver returns popups whose display conditions match the
        // request (using the cached candidate index); popup-specific gates are
        // applied here.
        $ids = TemplateResolver::instance()->resolve_group('popup');

        if (!empty($ids)) {
            // Warm the post + meta caches once so the hydration below issues no
            // per-popup queries (each get()/get_post_meta() becomes a cache hit).
            _prime_post_caches($ids, false, true);
        }

        $service = new PopupService();
        $matched = [];

        foreach ($ids as $id) {
            $popup = $service->get($id);

            if (!$popup) {
                continue;
            }

            // A published popup with no Elementor elements is never shown
            if (!$this->has_content($popup)) {
                continue;
            }

            // Server-evaluable advanced rules (logged-in, etc.) gate the popup
            // before it is handed to the frontend. Client-side rules (frequency,
            // devices, …) are enforced in engine.js.
            if (!$this->passes_server_rules($popup)) {
                continue;
            }

            $matched[] = $popup;
        }

        /**
         * Filter the list of popups that matched the current request.
         *
         * @param PopupDTO[] $matched
         */
        return apply_filters(Hooks::POPUP_MATCHED_FILTER, $matched);
    }

    /**
     * Whether the popup has any authored Elementor content. A never-edited
     * document stores an empty elements array; showing it would paint only the
     * overlay backdrop with no visible content box.
     *
     * Mirrors Elementor's own Document::get_elements_data() (a json_decode of the
     * _elementor_data meta) without instantiating a Document on the hot path; the
     * meta is already cache-primed in resolve_all().
     */
    protected function has_content(PopupDTO $popup): bool
    {
        $raw = get_post_meta((int) $popup->id, '_elementor_data', true);

        if (empty($raw)) {
            return false;
        }

        $elements = json_decode((string) $raw, true);

        return is_array($elements) && !empty($elements);
    }

    /**
     * Drop the popup if any server-evaluable rule attached to it fails.
     */
    protected function passes_server_rules(PopupDTO $popup): bool
    {
        $rules = is_array($popup->rules) ? $popup->rules : [];

        if (empty($rules)) {
            return true;
        }

        $manager = RuleManager::instance();

        foreach (RuleDTO::collection($rules) as $dto) {
            $rule = $manager->get($dto->type);

            if ($rule && $rule->is_server_evaluable() && !$rule->check($dto)) {
                return false;
            }
        }

        return true;
    }
}
