<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Core\Container;
use Elemacy\Loop\Contracts\ItemPresenterInterface;

/**
 * Which presenter renders the items of each widget type the pagination
 * endpoint may serve. A widget type with no entry is refused, so registering
 * one is what opens a widget to the endpoint.
 *
 * @internal
 */
final class ItemPresenterRegistry
{
    private static ?self $instance = null;

    /** @var array<string, class-string<ItemPresenterInterface>> */
    private array $presenters = [];

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $widget_type Elementor widget name.
     * @param string $presenter   An ItemPresenterInterface class, built from the container on first use.
     * @return void
     */
    public function register(string $widget_type, string $presenter): void
    {
        $this->presenters[$widget_type] = $presenter;
    }

    public function for_widget(string $widget_type): ?ItemPresenterInterface
    {
        if (!isset($this->presenters[$widget_type])) {
            return null;
        }

        $presenter = Container::instance()->singleton($this->presenters[$widget_type]);

        return $presenter instanceof ItemPresenterInterface ? $presenter : null;
    }
}
