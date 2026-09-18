<?php

namespace Elemacy\Loop\Pagination;

defined('ABSPATH') || exit;

use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Loop\Pagination\DTO\PublishedLoopDefinition;
use Elemacy\Loop\Pagination\DTO\PublicLoopRequest;
use JsonException;

/**
 * Reads the persisted authoring tree; never executes a source or an Elementor
 * widget. Builder placement and source-context policy remain separate checks.
 */
final class PublishedLoopDefinitionResolver
{
    const UNAVAILABLE = PublicRenderPolicy::UNAVAILABLE;

    /** @var PublicRenderPolicy */
    private PublicRenderPolicy $policy;

    /** @param PublicRenderPolicy $policy Native document eligibility. */
    public function __construct(PublicRenderPolicy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Resolve a request against the current saved definition and revision.
     *
     * @param PublicLoopRequest $request Validated request shape.
     * @return PublishedLoopDefinition
     * @throws ValidationException If the definition is inaccessible or stale.
     */
    public function resolve(PublicLoopRequest $request): PublishedLoopDefinition
    {
        $definition = $this->find($request->owner_id(), $request->widget_id());
        if (!hash_equals($definition->fingerprint(), $request->fingerprint())) {
            throw PublicLoopRequest::refresh_required();
        }
        return $definition;
    }

    /**
     * Read server-owned settings for payload generation or request resolution.
     *
     * A published library owner is not proof of an authorized public placement;
     * the placement policy must still validate its host/reference chain.
     *
     * @param int    $owner_id Authoring document ID.
     * @param string $widget_id Exact saved Loop Grid widget ID.
     * @return PublishedLoopDefinition
     * @throws ValidationException On invalid authoring data or access.
     */
    public function find(int $owner_id, string $widget_id): PublishedLoopDefinition
    {
        $this->policy->assert_owner($owner_id);

        $raw = get_post_meta($owner_id, '_elementor_data', true);
        if (!is_string($raw) || '' === $raw) {
            throw self::unavailable();
        }
        try {
            $tree = json_decode($raw, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $error) {
            throw self::unavailable();
        }
        if (!is_array($tree)) {
            throw self::unavailable();
        }
        $element = $this->find_element($tree, $widget_id);
        if ('widget' !== ($element['elType'] ?? null) || 'elemacy-loop-grid' !== ($element['widgetType'] ?? null)
            || !isset($element['settings']) || !is_array($element['settings'])) {
            throw self::unavailable();
        }
        $settings = $element['settings'];
        if (array_key_exists('data_source', $settings) && (!is_string($settings['data_source']) || '' === $settings['data_source'])) {
            throw self::unavailable();
        }

        $template_id = $this->template_id($settings['template_id'] ?? null);
        $this->policy->assert_template($template_id);

        $fingerprint = hash('sha256', get_current_blog_id() . ':' . $owner_id . ':' . $widget_id . ':' . $raw);
        return new PublishedLoopDefinition($owner_id, $widget_id, $template_id, $settings, $fingerprint);
    }

    /**
     * Require a unique identity across containers and widgets in the saved tree.
     *
     * @param array  $tree Saved Elementor elements.
     * @param string $widget_id Identity being resolved.
     * @return array
     * @throws ValidationException If an ID is absent or ambiguous.
     */
    private function find_element(array $tree, string $widget_id): array
    {
        $stack = [$tree];
        $found = null;
        while ($stack) {
            foreach (array_pop($stack) as $element) {
                if (!is_array($element)) {
                    continue;
                }
                if (($element['id'] ?? null) === $widget_id) {
                    if (null !== $found) {
                        throw self::unavailable();
                    }
                    $found = $element;
                }
                if (isset($element['elements']) && is_array($element['elements'])) {
                    $stack[] = $element['elements'];
                }
            }
        }
        if (null === $found) {
            throw self::unavailable();
        }
        return $found;
    }

    /**
     * @param mixed $value Persisted Elementor SELECT2 value (integer or digits).
     * @return int
     * @throws ValidationException On malformed, absent or overflowing IDs.
     */
    private function template_id($value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/\A[1-9][0-9]*\z/', $value)
            && (string) (int) $value === $value) {
            return (int) $value;
        }
        throw self::unavailable();
    }

    /** @return ValidationException Client-safe unavailable-definition error. */
    private static function unavailable(): ValidationException
    {
        return PublicRenderPolicy::unavailable();
    }
}
