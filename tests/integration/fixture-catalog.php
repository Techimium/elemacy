<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Tests\Integration\Fixtures\ArchitectureCatalog;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ArchitectureCatalog.php';

/**
 * Constructs ArchitectureCatalog, which requires ACF Pro's repeater
 * field type (task 9.6: excluded from the Free-only CI job that runs
 * without a licensed ACF Pro installation; run directly with
 * --acf-plugin for full local coverage).
 *
 * @group requires-acf-pro
 */
final class FixtureCatalogTest extends IntegrationTestCase
{
    public function testNativeArchitectureFixturesCanBeCreatedAndCleaned(): void
    {
        $fixtures = new ArchitectureCatalog();
        try {
            $catalog = $fixtures->create();
            foreach (['posts', 'library'] as $group) {
                foreach (['published' => 'publish', 'draft' => 'draft', 'private' => 'private', 'protected' => 'publish'] as $name => $status) {
                    self::assertSame($status, get_post_status($catalog[$group][$name]));
                }
                self::assertNotEmpty(get_post($catalog[$group]['protected'])->post_password);
            }
            self::assertSame(['posts', 'terms', 'users', 'acf_repeater'], array_keys($catalog['sources']));
            self::assertSame(['header', 'single', 'footer'], array_keys($catalog['locations']));
            foreach ($catalog['locations'] as $location => $id) {
                self::assertSame($location, get_post_meta($id, '_elemacy_template_type', true));
                self::assertSame($location === 'single' ? 'elemacy_single' : 'wp-page', get_post_meta($id, '_elementor_template_type', true));
            }
            self::assertSame('section', get_post_meta($catalog['library']['wrong_type'], '_elemacy_template_type', true));
            self::assertSame('header', get_post_meta($catalog['library']['wrong_group'], '_elemacy_template_type', true));
            self::assertSame(['center', 'custom'], array_keys($catalog['popups']));
            self::assertSame('', get_post_meta($catalog['native_host'], '_elementor_edit_mode', true));
            foreach ([$catalog['elementor_host'], $catalog['nested_loop']] as $owner) {
                $elements = json_decode(get_post_meta($owner, '_elementor_data', true), true);
                self::assertSame('elemacy-loop-grid', $elements[0]['elements'][0]['widgetType']);
                self::assertSame($catalog['library']['published'], $elements[0]['elements'][0]['settings']['template_id']);
            }
            self::assertSame('Native term metadata', get_term_meta($catalog['term'], 'fixture_label', true));
            self::assertStringEndsWith('@example.invalid', get_userdata($catalog['user'])->user_email);
            $rows = get_field($catalog['acf']['key'], $catalog['acf']['post_id']);
            self::assertCount(3, $rows);
            self::assertSame(['First native row', 'Second native row', 'Third native row'], array_column($rows, 'label'));
            self::assertSame([['label' => 'Nested native row']], $rows[0]['children']);
            self::assertFalse($rows[1]['children'], 'Native ACF formats an empty repeater as false.');
            self::assertSame($rows, get_field($catalog['acf']['key'], $catalog['acf']['options_id']));
            self::assertNotEmpty(acf_get_options_pages()[$catalog['acf']['options_id']]);
            $display = get_post_meta($catalog['popups']['custom'], '_elementor_page_settings', true);
            self::assertSame('custom', $display['elemacy_position']);
            self::assertSame(11, $display['elemacy_offset_x_mobile']['size']);

            $fixtures->cleanup();
            self::assertNull(get_post($catalog['elementor_host']));
            self::assertNull(get_post($catalog['library']['published']));
            self::assertFalse(get_userdata($catalog['user']));
            self::assertNull(get_term($catalog['term'], 'category'));
            self::assertArrayNotHasKey($catalog['acf']['options_id'], (array) acf_get_options_pages());
            global $wpdb;
            self::assertSame('0', $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like($catalog['acf']['options_id'] . '_') . '%',
                $wpdb->esc_like('_' . $catalog['acf']['options_id'] . '_') . '%'
            )), 'ACF options values and field references must be removed.');
        } finally {
            $fixtures->cleanup();
        }
    }
}
