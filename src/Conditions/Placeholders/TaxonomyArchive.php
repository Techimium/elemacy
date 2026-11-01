<?php

namespace Elemacy\Conditions\Placeholders;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Conditions\Support\Taxonomies;
use WP_Taxonomy;

/**
 * Placeholder for Pro's per-taxonomy "{Taxonomy} Archive" condition. One instance is
 * registered per public taxonomy, matching the name pro overrides.
 *
 * @internal
 */
class TaxonomyArchive extends PlaceholderCondition
{
    protected string $taxonomy;
    protected string $singular;

    public function __construct(WP_Taxonomy $taxonomy)
    {
        $this->taxonomy = $taxonomy->name;
        $this->singular = $taxonomy->labels->singular_name ?? $taxonomy->label;
    }

    /**
     * One placeholder per public taxonomy. Called at registration time (on `init`),
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

    /** One term archive with a value; the whole taxonomy without one. */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::narrowed($rule->value, Specificity::OBJECT, Specificity::TYPE);
    }
}
