<?php

namespace Elemacy\Loop\Pagination\DTO;

defined('ABSPATH') || exit;

/** Saved authoring definition, independent of Elementor widget instances. */
final class PublishedLoopDefinition
{
    /** @var int */
    private int $owner_id;
    /** @var string */
    private string $widget_id;
    /** @var int */
    private int $template_id;
    /** @var array */
    private array $settings;
    /** @var string */
    private string $fingerprint;

    /**
     * @param int    $owner_id Authoring document ID.
     * @param string $widget_id Saved widget identity.
     * @param int    $template_id Saved, validated loop template reference.
     * @param array  $settings Saved authoring settings; source policy adds runtime context.
     * @param string $fingerprint Site-bound owner document revision.
     */
    public function __construct(int $owner_id, string $widget_id, int $template_id, array $settings, string $fingerprint)
    {
        $this->owner_id = $owner_id;
        $this->widget_id = $widget_id;
        $this->template_id = $template_id;
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

    /** @return int */
    public function template_id(): int
    {
        return $this->template_id;
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
