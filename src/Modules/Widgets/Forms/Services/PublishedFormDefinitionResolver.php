<?php

namespace Elemacy\Modules\Widgets\Forms\Services;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elementor\Plugin;

defined('ABSPATH') || exit;

/** Saved form settings and a signed proof of its rendered host placement. */
final class PublishedFormDefinitionResolver
{
    private TypeRegistry $types;
    private PlacementResolverRegistry $placements;

    public function __construct(TypeRegistry $types, PlacementResolverRegistry $placements)
    {
        $this->types = $types;
        $this->placements = $placements;
    }

    public function resolve(int $owner, string $widget, string $context = ''): array
    {
        $element = $this->element($owner, $widget);
        if (get_post_type($owner) === 'elemacy_library') {
            $claims = $this->verify($context, $owner, $widget);
            $this->assert_placement($owner, $claims);
        }
        $instance = Plugin::$instance->elements_manager->create_element_instance($element);
        if (!$instance) {
            throw self::unavailable();
        }
        return $instance->get_settings();
    }

    /** Called while Elementor is rendering the actual placement, never from a public signing endpoint. */
    public function context(int $owner, string $widget): string
    {
        $this->element($owner, $widget);
        if (get_post_type($owner) !== 'elemacy_library') {
            return '';
        }
        $placement = $this->placements->resolve_current($owner);
        if (!$placement) {
            throw self::unavailable();
        }
        $host = is_singular() ? (int) get_queried_object_id() : 0;
        if ($host) {
            $this->accessible($host);
        }
        global $wp_query;
        $query = [];
        foreach (['p', 'page_id', 'name', 'pagename', 'post_type', 's', 'cat', 'tag_id', 'author', 'taxonomy', 'term', 'year', 'monthnum', 'day'] as $key) {
            $value = $wp_query ? $wp_query->get($key) : null;
            if ($value !== '' && $value !== null && $value !== 0 && $value !== false && $value !== []) {
                $query[$key] = $value;
            }
        }
        $revisions = [];
        foreach (array_unique(array_merge([$owner], $host ? [$host] : [], $placement['facts']['lineage'] ?? [])) as $id) {
            $this->accessible((int) $id);
            $revisions[(string) $id] = $this->revision((int) $id);
        }
        $claims = [
            'site' => get_current_blog_id(),
            'user' => get_current_user_id(),
            'owner' => $owner,
            'widget' => $widget,
            'host' => $host,
            'query' => $query,
            'placement' => $placement,
            'revisions' => $revisions,
            'expires' => time() + DAY_IN_SECONDS,
        ];
        $encoded = rtrim(strtr(base64_encode(wp_json_encode($claims)), '+/', '-_'), '=');
        $token = $encoded . '.' . $this->mac($encoded);
        if (strlen($token) > 8192) {
            throw self::unavailable();
        }
        return $token;
    }

    private function verify(string $token, int $owner, string $widget): array
    {
        if (strlen($token) > 8192) {
            throw self::unavailable();
        }
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || !hash_equals($this->mac($parts[0]), $parts[1])) {
            throw self::unavailable();
        }
        $claims = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true, 32);
        if (!is_array($claims) || ($claims['site'] ?? null) !== get_current_blog_id()
            || ($claims['user'] ?? null) !== get_current_user_id() || ($claims['owner'] ?? null) !== $owner
            || ($claims['widget'] ?? null) !== $widget || ($claims['expires'] ?? 0) < time()
            || !is_array($claims['revisions'] ?? null) || !isset($claims['revisions'][$owner])) {
            throw self::unavailable();
        }
        foreach ($claims['revisions'] as $id => $revision) {
            $this->accessible((int) $id);
            if (!hash_equals($this->revision((int) $id), $revision)) {
                throw self::unavailable();
            }
        }
        return $claims;
    }

    private function assert_placement(int $owner, array $claims): void
    {
        // Rebuild only server-signed host query facts, then ask the owning builder again.
        $previous = [];
        foreach (['wp_query', 'wp_the_query', 'post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages'] as $key) {
            $previous[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
        }
        try {
            $query = new \WP_Query($claims['query'] + [
				'posts_per_page' => 1,
				'no_found_rows' => true
			]);
            $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Scoped host reconstruction, restored in finally.
            if ($query->post) {
                $GLOBALS['post'] = $query->post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Scoped host reconstruction, restored in finally.
                setup_postdata($query->post);
            }
            $placement = $claims['placement'];
            if (($placement['type'] ?? '') === 'nested_loop') {
                // The signed lineage was issued only while this exact stack rendered;
                // every member and the host have been rechecked against their live revision/access.
                $lineage = $placement['facts']['lineage'] ?? [];
                if (!$lineage || count($lineage) > 10 || end($lineage) !== $owner) {
                    throw self::unavailable();
                }
            } else {
                TemplateResolver::reset();
                $current = $this->placements->resolve_public_context($owner);
                if (!$current || $current['type'] !== $placement['type']) {
                    throw self::unavailable();
                }
            }
        } finally {
            TemplateResolver::reset();
            foreach ($previous as $key => [$exists, $value]) {
                if ($exists) {
                    $GLOBALS[$key] = $value;
                } else {
                    unset($GLOBALS[$key]);
                }
            }
        }
    }

    private function element(int $owner, string $widget): array
    {
        $this->accessible($owner);
        if ('builder' !== get_post_meta($owner, '_elementor_edit_mode', true) || $widget === '' || strlen($widget) > 100) {
            throw self::unavailable();
        }
        $tree = json_decode((string) get_post_meta($owner, '_elementor_data', true), true);
        if (!is_array($tree)) {
            throw self::unavailable();
        }
        $stack = [$tree];
        $found = null;
        while ($stack) {
            foreach (array_pop($stack) as $element) {
                if (!is_array($element)) {
                    continue;
                }
                if (($element['id'] ?? null) === $widget) {
                    if ($found !== null) {
                        throw self::unavailable();
                    }
                    $found = $element;
                }
                if (!empty($element['elements']) && is_array($element['elements'])) {
                    $stack[] = $element['elements'];
                }
            }
        }
        if (($found['elType'] ?? '') !== 'widget' || ($found['widgetType'] ?? '') !== 'elemacy-form' || !is_array($found['settings'] ?? null)) {
            throw self::unavailable();
        }
        return $found;
    }

    private function accessible(int $id): void
    {
        $post = get_post($id);
        if (!$post || post_password_required($post)
            || ($post->post_status !== 'publish' && !($post->post_status === 'private' && current_user_can('read_post', $id)))) {
            throw self::unavailable();
        }
        if ($post->post_type === 'elemacy_library') {
            if (!$this->types->get((string) get_post_meta($id, '_elemacy_template_type', true))) {
                throw self::unavailable();
            }
        } elseif ($post->post_status === 'publish' && !is_post_publicly_viewable($post)) {
            throw self::unavailable();
        }
    }

    private function revision(int $id): string
    {
        $meta = [];
        foreach (['_elementor_data', '_elementor_page_settings', '_elemacy_template_type', '_elemacy_conditions', '_elemacy_popup_triggers', '_elemacy_popup_rules'] as $key) {
            $meta[$key] = get_post_meta($id, $key, true);
        }
        return hash('sha256', wp_json_encode($meta));
    }

    private function mac(string $payload): string
    {
        return hash_hmac('sha256', $payload, wp_salt('auth') . '|elemacy_form_placement');
    }

    private static function unavailable(): ValidationException
    {
        return new ValidationException(esc_html__('This form is unavailable. Please refresh the page.', 'elemacy'));
    }
}
