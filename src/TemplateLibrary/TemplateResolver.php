<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionEvaluator;
use Elemacy\Conditions\DTO\ConditionRuleDTO;

/**
 * Resolves which library items apply to the current request, evaluating display
 * conditions against the cached candidate index. Serves both theme locations
 * (resolve) and popups (resolve_group).
 */
class TemplateResolver
{
    protected static ?self $instance = null;

    protected ResolverCache $cache;

    /**
     * @var array<string, int|null>
     */
    protected static array $resolved = [];

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function __construct(?ResolverCache $cache = null)
    {
        $this->cache = $cache ?? new ResolverCache();
    }

    /**
     * The single item of $type that applies: the first whose conditions match,
     * else the first with no conditions (global fallback). Returns its post id.
     */
    public function resolve(string $type): ?int
    {
        if (array_key_exists($type, static::$resolved)) {
            return static::$resolved[$type];
        }

        $candidates = $this->cache->get_index()[$type] ?? [];
        $fallback   = null;
        $match      = null;

        foreach ($candidates as $entry) {
            if (empty($entry['conditions'])) {
                $fallback = $fallback ?? (int) $entry['id'];
                continue;
            }

            if ($this->conditions_match($entry['conditions'])) {
                $match = (int) $entry['id'];
                break;
            }
        }

        return static::$resolved[$type] = ($match ?? $fallback);
    }

    /**
     * Every item in $group that applies (collect-all). An item with no
     * conditions is global. Returns post ids.
     *
     * @return int[]
     */
    public function resolve_group(string $group): array
    {
        $index   = $this->cache->get_index();
        $matched = [];

        foreach (TypeRegistry::instance()->names_in_group($group) as $type) {
            foreach ($index[$type] ?? [] as $entry) {
                if (empty($entry['conditions']) || $this->conditions_match($entry['conditions'])) {
                    $matched[] = (int) $entry['id'];
                }
            }
        }

        return $matched;
    }

    protected function conditions_match(array $conditions): bool
    {
        return ConditionEvaluator::instance()->evaluate(ConditionRuleDTO::collection($conditions));
    }
}
