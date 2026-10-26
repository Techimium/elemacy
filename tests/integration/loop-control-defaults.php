<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Elementor\Widgets\BaseWidget;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Http\Exceptions\ValidationException;
use Elementor\Base_Data_Control;
use Elementor\Controls_Manager;
use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Loop Grid, Loop Carousel and the Loop Item document save their settings
 * through Elementor, which stores nothing for a control left at its default
 * and silently drops a second control registered under an existing name. So a
 * renamed or removed loop control, a changed default or a name clash would
 * silently change every saved site. This pins every value-holding loop
 * control (CONTRACTS.md: "Released defaults are frozen too"), grouped by the
 * plugin whose data source registers it: a control conditioned on a Pro
 * source's `data_source` / `preview_data_source` is `pro`, every other one is
 * `free`.
 */
final class LoopControlDefaultsTest extends IntegrationTestCase
{
    private const FROZEN = __DIR__ . '/fixtures/loop-control-defaults.json';

    /** The Loop Item document's own loop controls; the rest of that stack is Elementor's or another module's. */
    private const PREVIEW_SECTION = 'elemacy_preview_section';

    /**
     * Changing a pinned control needs a migration that writes the old value
     * into saved widgets, and then a deliberate regeneration of the fixture
     * (run free-pro with ACF Pro, so every Pro control is registered):
     *
     *     ELEMACY_WRITE_LOOP_CONTROL_DEFAULTS=1 php tests/integration/run.php --filter LoopControlDefaultsTest
     *
     * Review the diff by hand.
     */
    public function testLoopControlNamesAndDefaultsAreFrozen(): void
    {
        $current = $this->current_controls();

        if (getenv('ELEMACY_WRITE_LOOP_CONTROL_DEFAULTS')) {
            file_put_contents(self::FROZEN, wp_json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            self::markTestSkipped('Wrote ' . self::FROZEN . '; review it, then run again without ELEMACY_WRITE_LOOP_CONTROL_DEFAULTS.');
        }

        self::assertFileExists(self::FROZEN, 'The frozen loop controls fixture is missing.');
        $frozen = json_decode((string) file_get_contents(self::FROZEN), true, 512, JSON_THROW_ON_ERROR);
        $export = static function ($value): string {
            return (string) wp_json_encode($value);
        };

        foreach ($current as $owner => $stacks) {
            foreach ($stacks as $stack => $controls) {
                foreach ($controls as $name => $control) {
                    self::assertArrayHasKey($name, $frozen[$owner][$stack] ?? [], "$owner/$stack: control \"$name\" (default " . $export($control['default']) . ') is not pinned. Add it to loop-control-defaults.json (see this test\'s docblock).');
                    self::assertSame(
                        $export($frozen[$owner][$stack][$name]),
                        $export($control),
                        "$owner/$stack: control \"$name\" changed from " . $export($frozen[$owner][$stack][$name]) . ' to ' . $export($control) . '. Saved widgets rely on it: ship a migration, or restore it.'
                    );
                }
            }
        }

        foreach ($frozen as $owner => $stacks) {
            // Pro controls exist only while Pro is active; Pro's options-page
            // control exists only with ACF Pro's options pages.
            if ('pro' === $owner && (!class_exists(\ElemacyPro\Core\ElemacyPro::class) || !function_exists('acf_get_options_pages'))) {
                continue;
            }
            foreach ($stacks as $stack => $controls) {
                foreach (array_keys($controls) as $name) {
                    self::assertArrayHasKey($name, $current[$owner][$stack] ?? [], "$owner/$stack: pinned control \"$name\" is no longer registered; saved widgets still hold it.");
                }
            }
        }
    }

    public function testNoTwoRegistrationsClaimTheSameControlName(): void
    {
        self::assertSame([], $this->redeclared_controls(), 'Elementor silently drops a control whose name is already registered.');
    }

    public function testAClashingSourceControlIsCaught(): void
    {
        $registry = LoopDataSourceRegistry::instance();
        $original = $registry->all();
        $registry->register(new class () implements LoopDataSourceInterface {
            public function get_key(): string
            {
                return 'fixture_clash';
            }

            public function get_label(): string
            {
                return 'Fixture clash';
            }

            public function register_controls(BaseWidget $widget): void
            {
                // Posts already owns `orderby`.
                $widget->add_control('orderby', ['type' => Controls_Manager::TEXT, 'condition' => ['data_source' => 'fixture_clash']]);
            }

            public function get_items(array $settings): LoopResultDTO
            {
                return new LoopResultDTO();
            }

            public function register_preview_controls(PreviewablePageBase $document): void
            {
            }

            public function resolve_preview_item(array $settings): ?LoopItemInterface
            {
                return null;
            }
        });

        try {
            self::assertContains('orderby', $this->redeclared_controls());
        } finally {
            $property = new \ReflectionProperty($registry, 'sources');
            $property->setAccessible(true);
            $property->setValue($registry, $original);
            $this->rebuild_stacks();
        }
    }

    /**
     * A later page reads the raw saved widget, where Elementor stored nothing
     * for a control left at its default, while the first page sees those
     * defaults filled in. Every source with a public policy must therefore
     * reach the same public settings from both.
     */
    public function testEverySourceResolvesTheSamePublicSettingsWithOrWithoutItsDefaults(): void
    {
        $post = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Default parity'], true);
        self::assertIsInt($post);
        $term = wp_insert_term('Default parity ' . bin2hex(random_bytes(4)), 'category');
        self::assertIsArray($term);
        $facts = ['post_id' => $post, 'term_id' => (int) $term['term_id']];
        $authored = ['acf_repeater' => ['repeater_field' => $this->repeater_on($post)]];
        $grid = $this->rebuild_stacks()['elemacy-loop-grid'];

        try {
            $checked = [];
            foreach (LoopDataSourceRegistry::instance()->all() as $key => $source) {
                if (!$source instanceof PublicLoopSourcePolicyInterface) {
                    continue;
                }
                $minimal = ['template_id' => 1, 'data_source' => $key, 'pagination_type' => 'ajax'] + ($authored[$key] ?? []);
                $defaults = [];
                foreach ($grid as $name => $control) {
                    $for = $control['condition']['data_source'] ?? null;
                    if ((null === $for || $key === $for) && array_key_exists('default', $control)
                        && Plugin::instance()->controls_manager->get_control($control['type'] ?? '') instanceof Base_Data_Control) {
                        $defaults[$name] = $control['default'];
                    }
                }

                $raw = $this->public_settings($source, $minimal, $facts);
                self::assertSame(
                    $this->public_settings($source, $minimal + $defaults, $facts),
                    $raw,
                    "$key: page 2 (raw saved settings) must resolve what page 1 (defaults filled in) resolved."
                );
                if ('acf_repeater' !== $key || function_exists('acf_get_options_pages')) {
                    self::assertStringStartsNotWith('refused', $raw, "$key: two refusals would prove nothing.");
                }
                $checked[] = $key;
            }
            self::assertContains('posts', $checked);
            self::assertContains('terms', $checked);
            self::assertContains('users', $checked);
        } finally {
            wp_delete_post($post, true);
            wp_delete_term((int) $term['term_id'], 'category');
            if (function_exists('acf_remove_local_field_group')) {
                acf_remove_local_field_group('group_elemacy_parity');
            }
        }
    }

    /** @return string The public settings, or the refusal, as comparable JSON. */
    private function public_settings(PublicLoopSourcePolicyInterface $source, array $settings, array $facts): string
    {
        try {
            $public = $source->get_public_settings($settings, $facts, 2);
            ksort($public);

            return (string) wp_json_encode($public);
        } catch (ValidationException $error) {
            return 'refused: ' . $error->getMessage();
        }
    }

    /** A top-level repeater with rows on $post when ACF Pro is available, so ACF's comparison is not two refusals. */
    private function repeater_on(int $post): string
    {
        if (!function_exists('acf_add_local_field_group') || !acf_is_field_type('repeater')) {
            return 'elemacy_parity_rows';
        }
        acf_add_local_field_group([
            'key' => 'group_elemacy_parity', 'title' => 'Elemacy parity rows',
            'fields' => [[
                'key' => 'field_elemacy_parity_rows', 'name' => 'elemacy_parity_rows', 'label' => 'Rows', 'type' => 'repeater',
                'sub_fields' => [['key' => 'field_elemacy_parity_label', 'name' => 'label', 'label' => 'Label', 'type' => 'text']],
            ]],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
        ]);
        update_field('field_elemacy_parity_rows', [['label' => 'A']], $post);

        return 'elemacy_parity_rows';
    }

    /** @return array<string, array<string, array<string, array{type: string, default: mixed}>>> owner => stack => name => control */
    private function current_controls(): array
    {
        $pro_sources = [];
        foreach (LoopDataSourceRegistry::instance()->all() as $key => $source) {
            if (0 === strpos(get_class($source), 'ElemacyPro\\')) {
                $pro_sources[] = $key;
            }
        }

        $current = ['free' => [], 'pro' => []];
        foreach ($this->rebuild_stacks() as $stack => $controls) {
            foreach ($controls as $name => $control) {
                if ('elemacy_loop' === $stack && self::PREVIEW_SECTION !== ($control['section'] ?? null)) {
                    continue;
                }
                if (!Plugin::instance()->controls_manager->get_control($control['type'] ?? '') instanceof Base_Data_Control) {
                    continue;
                }
                $source = $control['condition']['data_source'] ?? $control['condition']['preview_data_source'] ?? null;
                $owner = in_array($source, $pro_sources, true) ? 'pro' : 'free';
                $current[$owner][$stack][$name] = ['type' => (string) $control['type'], 'default' => $control['default'] ?? null];
            }
        }
        foreach ($current as &$stacks) {
            ksort($stacks);
            foreach ($stacks as &$controls) {
                ksort($controls);
            }
        }

        return $current;
    }

    /** @return string[] Control names Elementor refused as already registered while the loop stacks were rebuilt. */
    private function redeclared_controls(): array
    {
        $redeclared = [];
        $listen = static function ($function, $message) use (&$redeclared): void {
            if (preg_match('/Cannot redeclare control with same name "([^"]+)"/', (string) $message, $match)) {
                $redeclared[] = $match[1];
            }
        };
        add_action('doing_it_wrong_run', $listen, 10, 2);
        // Recorded above; raised as a PHP notice it would abort Elementor
        // midway through a controls section under PHPUnit.
        add_filter('doing_it_wrong_trigger_error', '__return_false');
        try {
            $this->rebuild_stacks();
        } finally {
            remove_filter('doing_it_wrong_trigger_error', '__return_false');
            remove_action('doing_it_wrong_run', $listen, 10);
        }

        return $redeclared;
    }

    /**
     * Registers the loop widgets' and the Loop Item document's controls from
     * scratch: Elementor caches a stack per element type, built once.
     *
     * @return array<string, array<string, array>> stack => name => control
     */
    private function rebuild_stacks(): array
    {
        $manager = Plugin::instance()->controls_manager;
        $elements = [];
        foreach (['elemacy-loop-grid', 'elemacy-loop-carousel'] as $name) {
            $elements[$name] = Plugin::instance()->widgets_manager->get_widget_types($name);
            self::assertNotNull($elements[$name], "$name must be registered.");
        }
        $document = Plugin::instance()->documents->get($this->documents->create('Loop control defaults', 'loop', 'draft'), false);
        self::assertInstanceOf(PreviewablePageBase::class, $document);
        $elements['elemacy_loop'] = $document;

        $stacks = [];
        foreach ($elements as $name => $element) {
            $manager->delete_stack($element);
            $element->get_controls();
            $stack = $manager->get_stacks()[$element->get_unique_name()] ?? null;
            self::assertIsArray($stack, "$name: its control stack must be registered.");
            $stacks[$name] = array_merge($stack['controls'], $stack['style_controls'] ?? []);
        }

        return $stacks;
    }
}
