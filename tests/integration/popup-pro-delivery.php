<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Conditions\ConditionRepository;
use Elemacy\Core\Container;
use Elemacy\Modules\Popups\Constants\MetaKeys;
use Elemacy\Modules\Popups\Resources\PopupConfigResource;
use Elemacy\Modules\Popups\Services\PopupService;
use Elemacy\TemplateLibrary\Constants\MetaKeys as LibraryMetaKeys;
use Elemacy\Tests\Integration\Fixtures\LibraryDocument;
use Elemacy\Tests\Integration\Fixtures\ThemePageRequest;
use Elemacy\Tests\Integration\Fixtures\RealRestRoutes;
use ElemacyPro\Extensions\Popups\Services\FrontendAssets;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RealRestRoutes.php';
require_once __DIR__ . '/fixtures/ThemePageRequest.php';

/**
 * What Pro changes about which popups are delivered and how a page counts a
 * visit. Each page is rendered in its own process, as a real request is.
 */
final class PopupProDeliveryTest extends IntegrationTestCase
{
    use RealRestRoutes;

    private const THEME = 'theme-generic-chrome';

    private int $host_id;
    private array $owned = [];
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(FrontendAssets::class)) {
            self::markTestSkipped('Requires the declared Pro pairing.');
        }

        $this->host_id = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish',
            'post_title' => 'Pro delivery host', 'post_content' => 'Plain content, no Elementor.',
        ], true);
        self::assertIsInt($this->host_id);
        $this->owned[] = $this->host_id;

        update_option('elementor_element_cache_ttl', 'disable');
        $this->boot_real_routes();
    }

    protected function tearDown(): void
    {
        try {
            $this->restore_rest_server();
            foreach (array_reverse($this->owned) as $id) {
                wp_delete_post($id, true);
            }
            foreach ($this->options as $name => $previous) {
                false === $previous ? delete_option($name) : update_option($name, $previous);
            }
            delete_option('elementor_element_cache_ttl');
            delete_option('elemacy_fixture_leaked_loop_ids');
        } finally {
            parent::tearDown();
        }
    }

    private function set_option(string $name, $value): void
    {
        if (!array_key_exists($name, $this->options)) {
            $this->options[$name] = get_option($name, false);
        }
        update_option($name, $value);
    }

    private function create_popup(string $marker, array $conditions = [], array $rules = [], array $triggers = []): int
    {
        $id = $this->documents->create($marker, 'popup', 'publish', LibraryDocument::heading($marker));
        $this->owned[] = $id;

        update_post_meta($id, LibraryMetaKeys::TEMPLATE_TYPE, 'popup');
        (new ConditionRepository())->save($id, $conditions ?: [['type' => 'general/entire_site', 'operator' => 'include', 'value' => '']]);

        if ($rules) {
            update_post_meta($id, MetaKeys::RULES, $rules);
        }
        if ($triggers) {
            update_post_meta($id, MetaKeys::TRIGGERS, $triggers);
        }

        return $id;
    }

    private function render(string $url, string $user = 'guest'): string
    {
        return ThemePageRequest::render(self::THEME, $url, $user)['html'];
    }

    private function delivered(string $html, int $id): bool
    {
        return false !== strpos($html, 'data-elemacy-popup-id="' . $id . '"') && 1 === preg_match('#"id":' . $id . '[,}]#', $html);
    }

    private function schedule(?string $start, ?string $end): array
    {
        return [['id' => 'r1', 'type' => 'schedule', 'params' => ['start' => (string) $start, 'end' => (string) $end]]];
    }

    public function testAnEndedScheduleIsNotDeliveredButAFutureOneIs(): void
    {
        $fmt = static fn (int $offset): string => gmdate('Y-m-d\TH:i', time() + $offset);
        $ended = $this->create_popup('Ended campaign', [], $this->schedule($fmt(-7 * DAY_IN_SECONDS), $fmt(-DAY_IN_SECONDS)));
        $active = $this->create_popup('Active campaign', [], $this->schedule($fmt(-DAY_IN_SECONDS), $fmt(DAY_IN_SECONDS)));
        $future = $this->create_popup('Future campaign', [], $this->schedule($fmt(DAY_IN_SECONDS), $fmt(2 * DAY_IN_SECONDS)));
        $this->set_option('timezone_string', 'UTC');

        $html = $this->render(get_permalink($this->host_id));

        self::assertStringNotContainsString('data-elemacy-popup-id="' . $ended . '"', $html, 'An ended campaign is not in the page source: ' . $html);
        self::assertDoesNotMatchRegularExpression('#"id":' . $ended . '[,}]#', $html, 'Nor in the browser config.');
        self::assertTrue($this->delivered($html, $active), 'A running campaign is delivered.');
        self::assertTrue($this->delivered($html, $future), 'A campaign that has not started is still delivered; the browser enforces its start.');
    }

    public function testTheEndOfAScheduleIsReadInTheSiteTimezone(): void
    {
        // A site at UTC+6, whose clock says "30 minutes ago" for a time that is 5.5 hours ahead of UTC's.
        $this->set_option('timezone_string', '');
        $this->set_option('gmt_offset', 6);
        $local = static fn (int $offset): string => gmdate('Y-m-d\TH:i', time() + 6 * HOUR_IN_SECONDS + $offset);

        $ended = $this->create_popup('Ended thirty minutes ago, site time', [], $this->schedule(null, $local(-30 * MINUTE_IN_SECONDS)));
        $running = $this->create_popup('Ends in thirty minutes, site time', [], $this->schedule(null, $local(30 * MINUTE_IN_SECONDS)));

        $html = $this->render(get_permalink($this->host_id));

        self::assertFalse($this->delivered($html, $ended), 'Read as UTC this end would still be hours away; in the site\'s UTC+6 it has passed.');
        self::assertStringNotContainsString('data-elemacy-popup-id="' . $ended . '"', $html);
        self::assertTrue($this->delivered($html, $running));
    }

    public function testTheVisitCounterIsPrintedOnAPageWithNoPopupAndNotOnAFeedOrAPreview(): void
    {
        $html = $this->render(get_permalink($this->host_id));
        self::assertStringContainsString('id="elemacy-pro-visit-counter"', $html, 'A page with no popup still counts the visit.');
        self::assertStringContainsString("localStorage.setItem(p+'pageviews'", $html);
        self::assertStringContainsString('(window,"elemacy_' . get_current_blog_id() . '_")', $html, "The counter's keys start with this site's prefix.");
        self::assertSame(1, substr_count($html, 'elemacy-pro-visit-counter'), 'Once per page.');

        $feed = $this->render(home_url('/?feed=rss2'));
        self::assertStringNotContainsString('elemacy-pro-visit-counter', $feed, 'A feed is not a visit.');

        $preview = $this->render(add_query_arg(['elementor-preview' => $this->host_id, 'ver' => time()], get_permalink($this->host_id)), 'admin');
        self::assertStringNotContainsString('elemacy-pro-visit-counter', $preview, 'The Elementor preview is not a visit.');
    }

    public function testTheVisitCounterIsNotPrintedInTheAdmin(): void
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        $previous_screen = $GLOBALS['current_screen'] ?? null;
        set_current_screen('dashboard');
        self::assertTrue(is_admin(), 'Fixture: this is an admin request.');
        ob_start();
        try {
            (new FrontendAssets())->print_visit_counter();
        } finally {
            $printed = (string) ob_get_clean();
            $GLOBALS['current_screen'] = $previous_screen; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring what the test replaced.
        }

        self::assertSame('', $printed);
    }

    public function testOnPopupCloseKeepsItsSourceAndALegacyTriggerMeansAnyOtherPopup(): void
    {
        $source = $this->create_popup('Chain source popup');
        $chained = $this->create_popup('Chained popup', [], [], [['id' => 't1', 'type' => 'on_popup_close', 'params' => ['popup_id' => (string) $source]]]);
        $legacy = $this->create_popup('Legacy chained popup', [], [], [['id' => 't2', 'type' => 'on_popup_close', 'params' => []]]);

        $service = Container::instance()->singleton(PopupService::class);
        $config = static fn (int $id): array => PopupConfigResource::make($service->get_or_fail($id));

        self::assertSame($source, $config($chained)['triggers'][0]['popup_id'], 'The chosen source reaches the browser as a number.');
        self::assertSame(0, $config($legacy)['triggers'][0]['popup_id'], 'A trigger saved without a source means any other popup.');

        // Saved and read back through the admin API, the choice survives.
        wp_set_current_user(get_user_by('login', 'elemacy_test_admin')->ID);
        $read = json_decode((string) wp_json_encode(rest_do_request('/elemacy/v1/popups/' . $chained)->get_data()), true);
        self::assertSame((string) $source, (string) $read['data']['triggers'][0]['params']['popup_id']);

        // The trigger offers the site's popups by title, after "any other popup".
        $types = json_decode((string) wp_json_encode(rest_do_request('/elemacy/v1/popups/triggers')->get_data()), true)['data'];
        $schema = array_values(array_filter($types, static fn (array $type): bool => 'on_popup_close' === $type['value']))[0]['params_schema'];
        self::assertSame('popup_id', $schema[0]['key']);
        self::assertSame('0', $schema[0]['options'][0]['value'], '"Any other popup" comes first.');
        self::assertContains((string) $source, array_column($schema[0]['options'], 'value'));
        self::assertStringContainsString('Chain source popup', wp_json_encode($schema[0]['options']));
    }

    public function testProConditionsUseTheViewedPostEvenWhenAnotherPluginsLoopLeftTheGlobalPostElsewhere(): void
    {
        $other = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Leaked loop post', 'post_content' => 'x'], true);
        self::assertIsInt($other);
        $this->owned[] = $other;
        update_option('elemacy_fixture_leaked_loop_ids', [$other]);

        $for = static fn (int $post): array => [['type' => 'singular/specific_post', 'operator' => 'include', 'value' => (string) $post]];
        $viewed = $this->create_popup('Targets the viewed post', $for($this->host_id));
        $leaked = $this->create_popup('Targets the loop\'s last post', $for($other));

        $html = $this->render(add_query_arg('elemacy_leaked_loop', '1', get_permalink($this->host_id)));

        self::assertStringContainsString('Targets the viewed post', $html, 'The popup for the page being viewed is delivered: ' . $html);
        self::assertTrue($this->delivered($html, $viewed));
        self::assertFalse($this->delivered($html, $leaked), 'The post another plugin\'s loop left behind is not the page being viewed.');
    }
}
