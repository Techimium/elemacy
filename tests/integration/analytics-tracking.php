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
        if ($this->ip !== '') {
            global $wpdb;
            $wpdb->delete($wpdb->options, ['option_name' => 'elemacy_pro_track_limit_' . hash_hmac('sha256', $this->ip, wp_salt('auth'))]);
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

    private function track(int $id, $event, string $nonce = 'valid', array $extra = []): \WP_REST_Response
    {
        $request = new \WP_REST_Request('POST', '/elemacy-analytics-fixture/v1/popups/' . $id . '/track');
        $request->set_header('X-WP-Nonce', $nonce === 'valid' ? wp_create_nonce('wp_rest') : $nonce);
        $request->set_body_params(['event' => $event] + $extra);
        return rest_get_server()->dispatch($request);
    }

    public function testOnlySupportedEventsAndBoundedRequestsCount(): void
    {
        $id = $this->popup();
        foreach (['', 'unsupported', 'Impression', ['impression']] as $event) {
            self::assertGreaterThanOrEqual(400, $this->track($id, $event)->get_status());
        }
        self::assertGreaterThanOrEqual(400, $this->track($id, 'impression', 'invalid')->get_status());
        self::assertSame(422, $this->track($id, 'impression', 'valid', ['extra' => str_repeat('x', 2049)])->get_status());
        self::assertSame([], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
        self::assertSame(200, $this->track($id, 'impression')->get_status());
        self::assertSame(200, $this->track($id, 'conversion')->get_status());
        self::assertSame(['impressions' => 1, 'conversions' => 1], (Container::instance()->make(AnalyticsService::class))->get($id));
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
            self::assertGreaterThanOrEqual(400, $this->track($id, 'impression')->get_status());
            self::assertSame([], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
        }
        $id = $this->popup();
        $request = new \WP_REST_Request('GET', '/elemacy-analytics-fixture/v1/popups/' . $id . '/analytics');
        self::assertGreaterThanOrEqual(400, rest_get_server()->dispatch($request)->get_status());
        wp_set_current_user(get_user_by('login', 'elemacy_test_admin')->ID);
        self::assertSame(200, rest_get_server()->dispatch($request)->get_status());
    }

    public function testTrackingLimitRejectsBeforeCountingAndExpiryRecovers(): void
    {
        global $wpdb;
        $id = $this->popup();
        $key = 'elemacy_pro_track_limit_' . hash_hmac('sha256', $this->ip, wp_salt('auth'));
        add_option($key, (time() + 60) . ':120', '', false);
        self::assertSame(429, $this->track($id, 'impression')->get_status());
        self::assertSame([], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
        $wpdb->update($wpdb->options, ['option_value' => (time() - 1) . ':120'], ['option_name' => $key]);
        self::assertSame(200, $this->track($id, 'impression')->get_status());
        self::assertSame(['1'], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
    }
}
