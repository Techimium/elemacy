<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Core\Constants\PostStatus;
use Elemacy\TemplateLibrary\ResolverCache;
use Elemacy\TemplateLibrary\TypeRegistry;

/**
 * Where a Theme Builder template displays, said the way the admin list shows
 * it, so an author sees a template that displays nowhere, one suspended by an
 * unavailable condition, or one a newer template overrides, without visiting
 * the site. Read from the resolver's own candidate index and the condition
 * registry: it describes the same decision the front end makes.
 */
class TemplateDisplaySummary
{
    public const DISPLAYED = 'displayed';
    public const NOT_DISPLAYED = 'not_displayed';
    public const SUSPENDED = 'suspended';
    public const UNPUBLISHED = 'unpublished';

    protected ResolverCache $cache;
    protected TypeRegistry $types;
    protected ConditionManager $conditions;

    public function __construct(?ResolverCache $cache = null, ?TypeRegistry $types = null, ?ConditionManager $conditions = null)
    {
        $this->cache      = $cache ?? new ResolverCache();
        $this->types      = $types ?? TypeRegistry::instance();
        $this->conditions = $conditions ?? ConditionManager::instance();
    }

    /**
     * @param int                $id     The template's post id.
     * @param string             $type   Its library type.
     * @param string             $status Its post status.
     * @param ConditionRuleDTO[] $rules  Its stored rules.
     * @return array{state: string, summary: string[], exclusions: int, unavailable: string[], overridden_by: array{id: int, title: string}|null}
     */
    public function describe(int $id, string $type, string $status, array $rules): array
    {
        $definition  = $this->types->get($type);
        $location    = $definition !== null && $definition->targets_location();
        $includes    = array_values(array_filter($rules, static fn(ConditionRuleDTO $rule): bool => $rule->is_include()));
        $unavailable = $location ? [] : $this->unavailable_labels($rules);

        if ($location) {
            $state = self::DISPLAYED;
        } elseif ($unavailable !== []) {
            $state = self::SUSPENDED;
        } elseif ($includes === []) {
            $state = self::NOT_DISPLAYED;
        } else {
            $state = self::DISPLAYED;
        }

        $published = PostStatus::PUBLISH === $status;

        return [
            'state'         => $published ? $state : self::UNPUBLISHED,
            'summary'       => $location
                ? [sprintf(/* translators: %s: template type label, e.g. "404 Page". */ __('Every %s', 'elemacy'), $definition->label)]
                : array_map([$this, 'label_of'], $includes),
            'exclusions'    => count($rules) - count($includes),
            'unavailable'   => $unavailable,
            'overridden_by' => $published && self::DISPLAYED === $state ? $this->overridden_by($id, $type, $location, $includes) : null,
        ];
    }

    /** "Condition" or "Condition: Choice" for a rule whose value is one of the condition's own choices. */
    protected function label_of(ConditionRuleDTO $rule): string
    {
        $condition = $this->conditions->get($rule->type);

        if ($condition === null) {
            return $rule->type;
        }

        if ('' !== $rule->value && 'select' === $condition->get_value_type()) {
            foreach ($condition->get_sub_values() as $choice) {
                if ((string) ($choice['value'] ?? '') === $rule->value) {
                    return $condition->get_label() . ': ' . $choice['label'];
                }
            }
        }

        return $condition->get_label();
    }

    /**
     * @param ConditionRuleDTO[] $rules
     * @return string[]
     */
    protected function unavailable_labels(array $rules): array
    {
        $labels = [];

        foreach ($rules as $rule) {
            if ($this->conditions->get_availability($rule->type)->is_unavailable()) {
                $labels[] = '' === trim($rule->type) ? __('Unnamed condition', 'elemacy') : $this->label_of($rule);
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * The newer published template of the same type that wins where this one
     * would display: for a location-wide type the newest one; otherwise the
     * newest one sharing an identical inclusion rule, as precedence breaks a
     * tie in favour of the newer template.
     *
     * @param int                $id       The template's post id.
     * @param string             $type     Its library type.
     * @param bool               $location Whether the type targets its whole location.
     * @param ConditionRuleDTO[] $includes Its inclusion rules.
     * @return array{id: int, title: string}|null
     */
    protected function overridden_by(int $id, string $type, bool $location, array $includes): ?array
    {
        // The index is newest first, so every entry before this one is newer.
        foreach ($this->cache->get_index()[$type] ?? [] as $entry) {
            $other = (int) $entry['id'];

            if ($other === $id) {
                return null;
            }

            if ($location || $this->wins_on_shared_inclusion(ConditionRuleDTO::collection($entry['conditions'] ?? []), $includes)) {
                return [
                    'id'    => $other,
                    'title' => get_the_title($other),
                ];
            }
        }

        return null;
    }

    /**
     * Whether a newer template with $rules displays wherever one of $includes
     * would: it is not suspended and carries an identical inclusion rule.
     *
     * @param ConditionRuleDTO[] $rules
     * @param ConditionRuleDTO[] $includes
     */
    protected function wins_on_shared_inclusion(array $rules, array $includes): bool
    {
        if ($this->unavailable_labels($rules) !== []) {
            return false;
        }

        $key = static fn(ConditionRuleDTO $rule): string => $rule->type . "\0" . $rule->value;
        $theirs = [];

        foreach ($rules as $rule) {
            if ($rule->is_include()) {
                $theirs[$key($rule)] = true;
            }
        }

        foreach ($includes as $rule) {
            if (isset($theirs[$key($rule)])) {
                return true;
            }
        }

        return false;
    }
}
