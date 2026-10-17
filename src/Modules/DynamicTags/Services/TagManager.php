<?php

namespace Elemacy\Modules\DynamicTags\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Support\ConfigList;
use Elemacy\Support\Utils;
use Elementor\Core\DynamicTags\Base_Tag;

/**
 * Registers the dynamic tag groups and tags free ships, listed in
 * src/Modules/DynamicTags/Config/tags.php, then fires
 * Hooks::DYNAMIC_TAGS_REGISTER_ACTION so add-ons can register their own.
 */
class TagManager
{
    protected bool $hooked = false;

    /**
     * Attaches this class's WordPress hooks. Idempotent: a second call does
     * nothing.
     */
    public function register_hooks(): void
    {
        if ($this->hooked) {
            return;
        }

        $this->hooked = true;

        add_action('elementor/dynamic_tags/register', [$this, 'register_tags']);
    }

    /**
     * @param \Elementor\Core\DynamicTags\Manager $tags_manager
     */
    public function register_tags($tags_manager): void
    {
        foreach (require Utils::get_plugin_path('src/Modules/DynamicTags/Config/tags.php') as $name => $group) {
            if (isset($group['requires']) && !class_exists($group['requires'])) {
                continue;
            }

            $tags_manager->register_group($name, ['title' => $group['title']]);

            foreach (ConfigList::build($group['tags'], Base_Tag::class) as $tag) {
                $tags_manager->register($tag);
            }
        }

        do_action(Hooks::DYNAMIC_TAGS_REGISTER_ACTION, $tags_manager);
    }
}
