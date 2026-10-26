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
     * Per-type results for this request, keyed by acting site and type.
     *
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

    /** Drops the per-type evaluation memo. */
    public static function reset(): void
    {
        static::$resolved = [];
    }

    /**
     * The single item of $type that applies, or null. Returns its post id.
     *
     * A type targeting its whole location takes the newest item. Otherwise an
     * item applies only through an inclusion condition that matches — rules
     * that only exclude name nowhere to display — and of those, the one that
     * matched most specifically wins. Every candidate is evaluated rather
     * than stopping at the first match, because a broad template appearing
     * earlier in the index must not beat a narrow one appearing later. See
     * docs/ARCHITECTURE.md § Template precedence.
     */
    public function resolve(string $type): ?int
    {
        $key = get_current_blog_id() . ':' . $type;

        if (array_key_exists($key, static::$resolved)) {
            return static::$resolved[$key];
        }

        $candidates = $this->cache->get_index()[$type] ?? [];
        $definition = TypeRegistry::instance()->get($type);

        if ($definition !== null && $definition->targets_location()) {
            $newest = reset($candidates);
            static::$resolved[$key] = false !== $newest ? (int) $newest['id'] : null;

            return static::$resolved[$key];
        }

        $match = null;
        $best  = null;

        foreach ($candidates as $entry) {
            $rules = ConditionRuleDTO::collection($entry['conditions'] ?? []);

            if (!ConditionRuleDTO::any_include($rules)) {
                continue;
            }

            $specificity = ConditionEvaluator::instance()->match($rules);

            if ($specificity === null) {
                continue;
            }

            // Strictly greater: the first candidate to reach a level keeps it,
            // and the index is already ordered newest-first, so that ordering
            // is the tie-break within one level.
            if ($best === null || $specificity > $best) {
                $best  = $specificity;
                $match = (int) $entry['id'];
            }
        }

        static::$resolved[$key] = $match;

        return static::$resolved[$key];
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
