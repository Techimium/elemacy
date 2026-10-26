<?php

namespace Elemacy\Conditions\Archive;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\BaseCondition;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Support\PostTypes;

class PostType extends BaseCondition
{
    public function get_name(): string
    {
        return 'archive/post_type';
    }

    public function get_type(): string
    {
        return 'archive';
    }

    public function get_label(): string
    {
        return __('Post Type Archive', 'elemacy');
    }

    /** One post type archive with a value; every archive without one. */
    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::narrowed($rule->value, Specificity::TYPE, Specificity::SECTION);
    }

    /**
     * Decided from the request alone, never from the posts it lists, so an
     * empty archive matches as a full one does. A post type's archives are
     * its own archive (the blog index for Posts), the archive of any
     * taxonomy registered for it, and, for Posts, date and author archives.
     */
    public function check(ConditionRuleDTO $rule): bool
    {
        $value = $rule->value;

        if ($value === '') {
            return is_archive() || is_home();
        }

        if ($value === 'post' && (is_home() || is_date() || is_author())) {
            return true;
        }

        return is_post_type_archive($value) || $this->is_taxonomy_archive_of($value);
    }

    private function is_taxonomy_archive_of(string $post_type): bool
    {
        if (!is_category() && !is_tag() && !is_tax()) {
            return false;
        }

        $term = get_queried_object();
        $taxonomy = $term instanceof \WP_Term ? get_taxonomy($term->taxonomy) : false;

        return $taxonomy && in_array($post_type, (array) $taxonomy->object_type, true);
    }

    public function get_sub_values(): array
    {
        return PostTypes::as_sub_values();
    }
}
