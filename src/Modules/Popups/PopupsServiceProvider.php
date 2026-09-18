<?php

namespace Elemacy\Modules\Popups;

defined('ABSPATH') || exit;

use Elemacy\Core\Container;
use Elemacy\Core\Contracts\ServiceProviderInterface;
use Elemacy\Core\Hooks;
use Elemacy\Modules\Popups\Documents\DocumentManager;
use Elemacy\Modules\Popups\Services\EditorPreview;
use Elemacy\Modules\Popups\Services\PopupFrontendAssets;
use Elemacy\Modules\Popups\Services\PopupManager;
use Elemacy\Modules\Popups\Services\TriggerRuleBootstrap;
use Elemacy\Modules\Popups\Support\DocumentDisplay;
use Elemacy\TemplateLibrary\LibraryChanged;
use Elemacy\TemplateLibrary\LibraryPostType;

/**
 * The collaborators Popups::init() used to construct directly.
 */
class PopupsServiceProvider implements ServiceProviderInterface
{
    protected bool $booted = false;

    public function register(Container $container): void
    {
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        (new DocumentManager())->register_hooks();
        (new EditorPreview())->register_hooks();
        new PopupFrontendAssets();
        PopupManager::instance()->register_hooks();
        (new TriggerRuleBootstrap())->register();
        $this->register_cache_invalidation();
    }

    /**
     * Popups owns resetting its own request-level caches (task 11.3,
     * design.md D11) rather than TemplateLibrary reaching into them —
     * TemplateLibrary's LibraryBootstrap only owns ResolverCache/
     * TemplateResolver, its own domain (D7: no lateral module dependency).
     */
    protected function register_cache_invalidation(): void
    {
        $reset = static function ($post_id): void {
            if (get_post_type($post_id) === LibraryPostType::POST_TYPE) {
                PopupManager::instance()->reset();
                DocumentDisplay::forget((int) $post_id);
            }
        };

        add_action('save_post', $reset);
        add_action('before_delete_post', $reset);
        add_action('trashed_post', $reset);
        add_action('untrashed_post', $reset);

        add_action(Hooks::LIBRARY_CHANGED_ACTION, static function (LibraryChanged $event): void {
            PopupManager::instance()->reset();
            DocumentDisplay::forget($event->id);
        });

        add_action('switch_blog', static function (): void {
            PopupManager::instance()->reset();
            DocumentDisplay::reset();
        });
    }
}
