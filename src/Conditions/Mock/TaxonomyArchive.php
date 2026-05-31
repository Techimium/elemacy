<?php

namespace Elemacy\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Conditions\MockCondition;
use Elemacy\Conditions\Support\Taxonomies;
use WP_Taxonomy;

/**
 * Mock for pro's per-taxonomy "{Taxonomy} Archive" condition. One instance is
 * registered per public taxonomy, matching the name pro overrides.
 */
class TaxonomyArchive extends MockCondition
{
    protected string $taxonomy;
    protected string $singular;

    public function __construct(WP_Taxonomy $taxonomy)
    {
        $this->taxonomy = $taxonomy->name;
        $this->singular = $taxonomy->labels->singular_name ?? $taxonomy->label;
    }

    /**
     * One mock per public taxonomy. Called at registration time (on `init`),
     * once taxonomies are available.
     *
     * @return self[]
     */
    public static function make_all(): array
    {
        return array_map(
            static fn(WP_Taxonomy $taxonomy): self => new self($taxonomy),
            Taxonomies::public_objects()
        );
    }

    public function get_name(): string
    {
        return 'archive/tax/' . $this->taxonomy;
    }

    public function get_type(): string
    {
        return 'archive';
    }

    public function get_label(): string
    {
        /* translators: %s: taxonomy singular name, e.g. "Category". */
        return sprintf(__('%s Archive', 'elemacy'), $this->singular);
    }
}
