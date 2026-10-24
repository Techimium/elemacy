<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Container;
use Elemacy\Http\Route;
use ElemacyPro\Extensions\Popups\Services\AnalyticsService;
use ElemacyPro\Extensions\Popups\Services\TrackingRateLimiter;

require_once __DIR__ . '/IntegrationTestCase.php';

final class AnalyticsTrackingTest extends IntegrationTestCase
{
    private array $route_snapshot = [];
    private array $rest_snapshot = [];
    private $previous_ip;
    private string $ip = '';

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(AnalyticsService::class)) {
            self::markTestSkipped('Requires the declared Pro pairing.');
        }
        $this->previous_ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->ip = 'analytics-fixture-' . wp_generate_uuid4();
        $_SERVER['REMOTE_ADDR'] = $this->ip;
        $server = rest_get_server();
        foreach (['endpoints', 'namespaces', 'route_options'] as $name) {
            $property = new \ReflectionProperty($server, $name);
            $property->setAccessible(true);
            $this->rest_snapshot[$name] = $property->getValue($server);
        }
        foreach (['routes', 'namespace_name', 'group_stack'] as $name) {
            $property = new \ReflectionProperty(Route::class, $name);
            $property->setAccessible(true);
            $this->route_snapshot[$name] = $property->getValue();
            $property->setValue(null, $name === 'namespace_name' ? 'elemacy-analytics-fixture/v1' : []);
        }
        require ELEMACY_PRO_PATH . 'src/Extensions/Popups/Config/api.php';
        foreach (Route::get_routes() as $route) {
            $route->register();
        }
        wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        foreach ($this->route_snapshot as $name => $value) {
            $property = new \ReflectionProperty(Route::class, $name);
            $property->setAccessible(true);
            $property->setValue(null, $value);
        }
        foreach ($this->rest_snapshot as $name => $value) {
            $property = new \ReflectionProperty(rest_get_server(), $name);
            $property->setAccessible(true);
            $property->setValue(rest_get_server(), $value);
        }
        remove_all_filters(TrackingRateLimiter::CLIENT_IP_FILTER);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        if ($this->ip !== '') {
            global $wpdb;
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('elemacy_pro_track_limit_') . '%'));
            if ($this->previous_ip === null) {
                unset($_SERVER['REMOTE_ADDR']);
            } else {
                $_SERVER['REMOTE_ADDR'] = $this->previous_ip;
            }
        }
        parent::tearDown();
    }

    private function popup(string $status = 'publish'): int
    {
        wp_set_current_user(get_user_by('login', 'elemacy_test_admin')->ID);
        $id = $this->documents->create('Tracking policy', 'popup', $status);
        wp_set_current_user(0);
        return $id;
    }

    /**
     * The public beacon as a visitor sends it: no cookies, and no nonce unless
     * $nonce is given (the beacon carries none; a page cached last week would
     * carry a stale one, which must make no difference).
     */
    private function track(int $id, $event, ?string $nonce = null, array $extra = []): \WP_REST_Response
    {
        $request = new \WP_REST_Request('POST', '/elemacy-analytics-fixture/v1/popups/' . $id . '/track');
        if (null !== $nonce) {
            $request->set_header('X-WP-Nonce', $nonce);
        }
        $request->set_body_params(['event' => $event] + $extra);
        return rest_get_server()->dispatch($request);
    }

    private function bucket_key(string $address): string
    {
        return 'elemacy_pro_track_limit_' . hash_hmac('sha256', TrackingRateLimiter::group($address), wp_salt('auth'));
    }

    /** Fills $address's allowance for the next minute. */
    private function exhaust(string $address): void
    {
        add_option($this->bucket_key($address), (time() + 60) . ':120', '', false);
    }

    public function testOnlySupportedEventsAndBoundedRequestsCount(): void
    {
        $id = $this->popup();
        foreach (['', 'unsupported', 'Impression', ['impression']] as $event) {
            self::assertSame(422, $this->track($id, $event)->get_status(), 'An unsupported event is a validation error: ' . wp_json_encode($event));
        }
        self::assertSame(422, $this->track($id, 'impression', null, ['extra' => str_repeat('x', 2049)])->get_status());
        self::assertSame([], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
        self::assertSame(200, $this->track($id, 'impression')->get_status());
        self::assertSame(200, $this->track($id, 'conversion')->get_status());
        self::assertSame(['impressions' => 1, 'conversions' => 1], (Container::instance()->make(AnalyticsService::class))->get($id));
    }

    public function testABeaconWithoutNonceOrCookiesIsRecordedAndAStaleNonceChangesNothing(): void
    {
        $id = $this->popup();

        self::assertSame(200, $this->track($id, 'impression')->get_status(), 'A beacon carries no nonce and no cookies.');
        self::assertSame(200, $this->track($id, 'impression', 'stale-nonce-from-a-week-old-page')->get_status(), 'A page cached long ago would send an expired nonce; it must make no difference.');
        self::assertSame(200, $this->track($id, 'conversion', '')->get_status());

        self::assertSame(['impressions' => 2, 'conversions' => 1], (Container::instance()->make(AnalyticsService::class))->get($id));
    }

    public function testIneligibleDocumentsCannotCountAndAdminAnalyticsRetainsCapabilityPolicy(): void
    {
        $ids = [$this->popup('draft'), $this->popup('private'), $this->popup(), $this->popup(), $this->popup()];
        wp_update_post(['ID' => $ids[2], 'post_password' => 'secret']);
        update_post_meta($ids[3], '_elementor_data', '[]');
        update_post_meta($ids[4], '_elemacy_popup_triggers', [['type' => 'unregistered', 'params' => []]]);
        wp_set_current_user(get_user_by('login', 'elemacy_test_admin')->ID);
        $ids[] = $this->documents->create('Wrong document', 'header');
        wp_set_current_user(0);
        foreach ($ids as $id) {
            self::assertSame(404, $this->track($id, 'impression')->get_status(), "Document $id is not a deliverable popup, so it is not found.");
            self::assertSame([], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
        }
        $id = $this->popup();
        $request = new \WP_REST_Request('GET', '/elemacy-analytics-fixture/v1/popups/' . $id . '/analytics');
        self::assertSame(403, rest_get_server()->dispatch($request)->get_status(), 'The administrator endpoint stays closed to a visitor.');
        wp_set_current_user(get_user_by('login', 'elemacy_test_admin')->ID);
        self::assertSame(200, rest_get_server()->dispatch($request)->get_status());
    }

    public function testTheAnalyticsEndpointAnswersOnlyForPopups(): void
    {
        wp_set_current_user(get_user_by('login', 'elemacy_test_admin')->ID);
        $page = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Ordinary page']);
        $header = $this->documents->create('Header template', 'header');
        $draft = $this->documents->create('Draft popup', 'popup', 'draft');

        try {
            $get = static fn (int $id): \WP_REST_Response => rest_get_server()->dispatch(new \WP_REST_Request('GET', '/elemacy-analytics-fixture/v1/popups/' . $id . '/analytics'));

            self::assertSame(404, $get($page)->get_status(), 'An ordinary page has no popup analytics.');
            self::assertSame(404, $get($header)->get_status(), 'Nor does another kind of library document.');
            self::assertSame(404, $get(999999999)->get_status());
            self::assertSame(200, $get($draft)->get_status(), 'A draft popup is still a popup: administrators see its (zero) counts.');
        } finally {
            wp_delete_post($page, true);
        }
    }

    public function testTrackingLimitRejectsBeforeCountingAndExpiryRecovers(): void
    {
        global $wpdb;
        $id = $this->popup();
        $key = $this->bucket_key($this->ip);
        add_option($key, (time() + 60) . ':120', '', false);
        self::assertSame(429, $this->track($id, 'impression')->get_status());
        self::assertSame([], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
        $wpdb->update($wpdb->options, ['option_value' => (time() - 1) . ':120'], ['option_name' => $key]);
        self::assertSame(200, $this->track($id, 'impression')->get_status());
        self::assertSame(['1'], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
    }

    public function testASiteBehindAProxyCanSupplyTheRealClientAddressThroughTheFilter(): void
    {
        $id = $this->popup();
        // The proxy's own address is the one the server sees, and it is spent.
        $this->exhaust($this->ip);
        self::assertSame(429, $this->track($id, 'impression')->get_status(), 'Without the filter every visitor is the proxy.');

        add_filter(TrackingRateLimiter::CLIENT_IP_FILTER, static fn () => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');

        foreach (['203.0.113.10', '203.0.113.11'] as $visitor) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = $visitor;
            for ($event = 0; $event < 100; ++$event) {
                self::assertSame(200, $this->track($id, 'impression')->get_status(), "Event $event of visitor $visitor is within the limit.");
            }
        }

        self::assertSame(200, (Container::instance()->make(AnalyticsService::class))->get($id)['impressions'], 'Both visitors\' events were recorded.');
    }

    public function testAnUntrustedForwardingHeaderCannotRotateTheLimit(): void
    {
        $id = $this->popup();
        add_option($this->bucket_key($this->ip), (time() + 60) . ':119', '', false);

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        self::assertSame(200, $this->track($id, 'impression')->get_status(), 'The last event within the limit.');

        foreach (['198.51.100.2', '198.51.100.3', '198.51.100.4'] as $rotated) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = $rotated;
            self::assertSame(429, $this->track($id, 'impression')->get_status(), 'A different forwarding header does not buy a new allowance.');
        }
        self::assertSame(1, (Container::instance()->make(AnalyticsService::class))->get($id)['impressions']);
    }

    public function testIpv6AddressesInOneSlash64ShareAnAllowance(): void
    {
        $id = $this->popup();
        $this->exhaust('2001:db8:1:2::1');

        $_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:aaaa:bbbb:cccc:dddd';
        self::assertSame(429, $this->track($id, 'impression')->get_status(), 'Another address in the same /64 is the same client.');

        $_SERVER['REMOTE_ADDR'] = '2001:db8:1:3::1';
        self::assertSame(200, $this->track($id, 'impression')->get_status(), 'A different /64 has its own allowance.');
    }
}
