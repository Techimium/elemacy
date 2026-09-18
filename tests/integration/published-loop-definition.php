<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Modules\Widgets\DTO\PublicLoopRequest;
use Elemacy\Modules\Widgets\Services\LoopDataSourceRegistry;
use Elemacy\Modules\Widgets\Services\PublishedLoopDefinitionResolver;
use Elemacy\Modules\Widgets\Services\PublicRenderPolicy;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Tests\Integration\Fixtures\ArchitectureCatalog;
use Elemacy\Tests\Integration\Fixtures\ObservedLoopSource;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ArchitectureCatalog.php';
require_once __DIR__ . '/fixtures/ObservedLoopSource.php';

/**
 * Constructs ArchitectureCatalog, which requires ACF Pro's repeater
 * field type (task 9.6: excluded from the Free-only CI job that runs
 * without a licensed ACF Pro installation; run directly with
 * --acf-plugin for full local coverage).
 *
 * @group requires-acf-pro
 */
final class PublishedLoopDefinitionTest extends IntegrationTestCase
{
    /** @dataProvider savedDefinitions */
    public function testOnlyTheSavedAccessibleLoopGridDefinitionResolves(string $case): void
    {
        $fixtures = new ArchitectureCatalog();
        $registry = LoopDataSourceRegistry::instance();
        $original_sources = $registry->all();
        $observed = [];
        $renders = 0;
        $observe_render = static function ($data) use (&$renders) { ++$renders; return $data; };
        try {
            $catalog = $fixtures->create();
            $resolver = new PublishedLoopDefinitionResolver(new PublicRenderPolicy(TypeRegistry::instance(), $registry));
            $owner = $catalog['elementor_host'];
            $widget = $catalog['widget_id'];
            $definition = $resolver->find($owner, $widget);
            $fingerprint = $definition->fingerprint();
            $tree = json_decode(get_post_meta($owner, '_elementor_data', true), true);

            if (strpos($case, 'template_') === 0 && isset($catalog['library'][substr($case, 9)])) {
                $tree[0]['elements'][0]['settings']['template_id'] = $catalog['library'][substr($case, 9)];
            } elseif ($case === 'absent_widget') {
                $widget = 'absent1';
            } elseif ($case === 'container_id') {
                $widget = 'eac1001';
            } elseif ($case === 'other_widget_type') {
                $tree[0]['elements'][0]['widgetType'] = 'heading';
            } elseif ($case === 'duplicate_widget_id') {
                $tree[0]['elements'][] = $tree[0]['elements'][0];
            } elseif ($case === 'owner_draft' || $case === 'owner_private') {
                wp_update_post(['ID' => $owner, 'post_status' => substr($case, 6)]);
            } elseif ($case === 'owner_protected') {
                wp_update_post(['ID' => $owner, 'post_password' => 'fixture-password']);
            } elseif ($case === 'wrong_elementor_type') {
                update_post_meta($definition->template_id(), '_elementor_template_type', 'wp-page');
            } elseif ($case === 'array_reference') {
                $tree[0]['elements'][0]['settings']['template_id'] = [$definition->template_id()];
            } elseif ($case === 'overflow_reference') {
                $tree[0]['elements'][0]['settings']['template_id'] = '9999999999999999999999999';
            } elseif ($case === 'null_source') {
                $tree[0]['elements'][0]['settings']['data_source'] = null;
            } elseif ($case === 'stale') {
                $tree[0]['elements'][0]['settings']['posts_per_page'] = 3;
            } elseif ($case === 'valid_string_reference') {
                $tree[0]['elements'][0]['settings']['template_id'] = (string) $definition->template_id();
            }
            if ($case !== 'valid') {
                update_post_meta($owner, '_elementor_data', wp_slash($case === 'malformed_tree' ? '{broken' : wp_json_encode($tree)));
            }
            if ($case === 'valid_string_reference') {
                $fingerprint = $resolver->find($owner, $widget)->fingerprint();
            }

            foreach ($original_sources as $key => $source) {
                $observed[$key] = new ObservedLoopSource($source);
                // Deliberately substitutes the already-registered real source
                // with an instrumented wrapper for this test only: replace(),
                // not register() (task 10.1's RegistryIdentifierGuard treats
                // that as a duplicate-implementation conflict).
                $registry->replace($observed[$key]);
            }
            add_filter('elementor/frontend/builder_content_data', $observe_render);
            wp_set_current_user(0);
            $request = PublicLoopRequest::from_json(wp_json_encode([
                'version' => 1, 'owner_id' => $owner, 'widget_id' => $widget,
                'page' => 1, 'fingerprint' => $fingerprint,
            ]));
            $exception = null;
            $resolved = null;
            try {
                $resolved = $resolver->resolve($request);
            } catch (ValidationException $error) {
                $exception = $error;
            }
            foreach ($observed as $source) {
                self::assertSame(0, $source->query_calls, 'Definition lookup must never invoke a source.');
            }
            self::assertSame(0, $renders, 'Definition lookup must never render Elementor content.');
            if (strpos($case, 'valid') === 0) {
                self::assertNull($exception);
                self::assertSame($owner, $resolved->owner_id());
                self::assertSame($widget, $resolved->widget_id());
                self::assertSame($catalog['library']['published'], $resolved->template_id());
                self::assertSame('posts', $resolved->source_key());
                self::assertSame($tree[0]['elements'][0]['settings'], $resolved->settings());
            } else {
                self::assertInstanceOf(ValidationException::class, $exception);
                self::assertSame($case === 'stale' ? PublicLoopRequest::REFRESH_REQUIRED : PublishedLoopDefinitionResolver::UNAVAILABLE,
                    $exception->get_errors()['code']);
            }
        } finally {
            remove_filter('elementor/frontend/builder_content_data', $observe_render);
            $property = new \ReflectionProperty($registry, 'sources');
            $property->setAccessible(true);
            $property->setValue($registry, $original_sources);
            $fixtures->cleanup();
        }
    }

    public static function savedDefinitions(): array
    {
        $cases = [];
        foreach (['valid', 'valid_string_reference', 'absent_widget', 'container_id', 'other_widget_type',
            'duplicate_widget_id', 'owner_draft', 'owner_private', 'owner_protected', 'template_draft',
            'template_private', 'template_protected', 'template_wrong_type', 'template_wrong_group',
            'wrong_elementor_type', 'array_reference', 'overflow_reference', 'null_source', 'malformed_tree', 'stale'] as $case) {
            $cases[$case] = [$case];
        }
        return $cases;
    }
}
