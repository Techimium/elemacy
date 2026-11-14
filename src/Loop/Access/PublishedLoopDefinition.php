<?php

namespace Elemacy\Loop\Access;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\ItemPresenterInterface;

/**
 * Saved authoring definition, independent of Elementor widget instances.
 *
 * @internal
 */
final class PublishedLoopDefinition
{
    /** @var int */
    private int $owner_id;
    /** @var string */
    private string $widget_id;
    /** @var ItemPresenterInterface */
    private ItemPresenterInterface $presenter;
    /** @var array */
    private array $settings;
    /** @var string */
    private string $fingerprint;

    /**
     * @param int                    $owner_id Authoring document ID.
     * @param string                 $widget_id Saved widget identity.
     * @param ItemPresenterInterface $presenter What the saved widget renders its items with, already checked.
     * @param array                  $settings Saved authoring settings; source policy adds runtime context.
     * @param string                 $fingerprint Site-bound owner document revision.
     */
    public function __construct(int $owner_id, string $widget_id, ItemPresenterInterface $presenter, array $settings, string $fingerprint)
    {
        $this->owner_id = $owner_id;
        $this->widget_id = $widget_id;
        $this->presenter = $presenter;
        $this->settings = $settings;
        $this->fingerprint = $fingerprint;
    }

    /** @return int */
    public function owner_id(): int
    {
        return $this->owner_id;
    }

    /** @return string */
    public function widget_id(): string
    {
        return $this->widget_id;
    }

    /** @return ItemPresenterInterface */
    public function presenter(): ItemPresenterInterface
    {
        return $this->presenter;
    }

    /** @return array */
    public function settings(): array
    {
        return $this->settings;
    }

    /** @return string */
    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    /** @return string */
    public function source_key(): string
    {
        return $this->settings['data_source'] ?? 'posts';
    }
}
