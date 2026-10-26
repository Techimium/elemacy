<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Container;
use Elemacy\Core\Hooks;
use Elemacy\Modules\Popups\DTO\PopupDTO;
use Elemacy\Modules\Popups\DTO\RuleDTO;
use Elemacy\Modules\Popups\DTO\TriggerDTO;
use Elemacy\TemplateLibrary\LibraryPostType;
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
     * @return PopupDTO[]
     */
    public function resolve_all(): array
    {
        // A library item's own front-end page or preview already renders that
        // document as the page. Every other popup over it would be an overlay
        // on the thing being previewed.
        if (is_singular(LibraryPostType::POST_TYPE)) {
            return [];
        }

        // The shared resolver returns popups whose display conditions match the
        // request (using the cached candidate index); popup-specific gates are
        // applied here.
        $ids = TemplateResolver::instance()->resolve_group('popup');

        if (!empty($ids)) {
            // Warm the post + meta caches once so the hydration below issues no
            // per-popup queries (each get()/get_post_meta() becomes a cache hit).
            _prime_post_caches($ids, false, true);
        }

        $service = Container::instance()->singleton(PopupService::class);
        $matched = [];

        foreach ($ids as $id) {
            $popup = $service->get($id);

            if (!$popup) {
                continue;
            }

            // A password-protected popup is never a public overlay: its
            // content would be shown to everyone, or an empty shell would.
            if ('' !== (string) get_post_field('post_password', (int) $popup->id)) {
                continue;
            }

            // A published popup with no Elementor elements is never shown.
            if (!$this->has_content($popup)) {
                continue;
            }

            // Every configured rule and trigger must be available before this
            // popup is handed to the frontend, then server-evaluable advanced
            // rules (logged-in, etc.) gate it; client-side rules (frequency,
            // devices, …) are enforced in engine.js.
            if (!$this->passes_required_capabilities($popup)) {
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

    /** Server-side eligibility independent of frontend display orchestration/query matching. */
    public function is_runtime_eligible(PopupDTO $popup): bool
    {
        if (
            $popup->status !== 'publish' || post_password_required((int) $popup->id)
            || !$this->has_content($popup) || !$this->passes_required_capabilities($popup)
        ) {
            return false;
        }
        foreach ($popup->conditions as $condition) {
            if (\Elemacy\Conditions\ConditionManager::instance()->get_availability($condition->type)->is_unavailable()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether the popup has any authored Elementor content. A never-edited
     * document stores an empty elements array; showing it would paint only the
     * overlay backdrop with no visible content box.
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
     * Drop the popup if any attached rule or trigger is unavailable, or if any
     * server-evaluable rule fails.
     */
    protected function passes_required_capabilities(PopupDTO $popup): bool
    {
        if (!$this->has_available_triggers($popup)) {
            return false;
        }

        $rules = is_array($popup->rules) ? $popup->rules : [];

        if (empty($rules)) {
            return true;
        }

        $manager = RuleManager::instance();

        foreach (RuleDTO::collection($rules) as $dto) {
            if ($manager->get_availability($dto->type)->is_unavailable()) {
                return false;
            }

            $rule = $manager->get($dto->type);

            if ($rule->is_server_evaluable() && !$rule->check($dto)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Every configured trigger must be available: one the loaded runtime
     * cannot execute must not be silently dropped from its way-to-open set,
     * since an administrator authored it as part of how this popup opens.
     */
    protected function has_available_triggers(PopupDTO $popup): bool
    {
        $triggers = is_array($popup->triggers) ? $popup->triggers : [];

        if (empty($triggers)) {
            return true;
        }

        $manager = TriggerManager::instance();

        foreach (TriggerDTO::collection($triggers) as $dto) {
            if ($manager->get_availability($dto->type)->is_unavailable()) {
                return false;
            }
        }

        return true;
    }
}
