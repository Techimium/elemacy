<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Container;
use ElemacyPro\Extensions\Popups\Services\AnalyticsService;
use ElemacyPro\Extensions\Popups\Services\CounterLock;

require_once __DIR__ . '/IntegrationTestCase.php';

final class AnalyticsCountersTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(AnalyticsService::class)) {
            self::markTestSkipped('Requires the declared Pro pairing.');
        }
    }

    public function testSimultaneousFirstEventsCreateOneCounterWithoutLosingAcceptedEvents(): void
    {
        $id = $this->documents->create('Counter concurrency', 'popup');
        $this->concurrent($id, array_fill(0, 12, 'track'));
        self::assertSame(['12'], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
    }

    public function testExpiredLeaseRecoversAndOldWorkerCannotReleaseReplacement(): void
    {
        global $wpdb;
        $id = $this->documents->create('Counter expiry', 'popup');
        $old = new CounterLock($id);
        self::assertTrue($old->acquire());
        $key = 'elemacy_pro_counter_lock_' . $id;
        $wpdb->update($wpdb->options, ['option_value' => (time() - 1) . ':expired'], ['option_name' => $key]);
        $new = new CounterLock($id);
        self::assertTrue($new->acquire());
        $old->release();
        try {
            $new->assert_owned();
            self::assertSame('no', $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $key)));
        } finally {
            $new->release();
        }
    }

    public function testRecordingAnEventKeepsTheRestOfThePopupsCachedMetaAndUpdatesTheCounterInPlace(): void
    {
        global $wpdb;
        $id = $this->documents->create('Counter cache', 'popup');
        update_post_meta($id, '_elemacy_popup_triggers', [['id' => 't1', 'type' => 'page_load', 'params' => ['delay' => 2]]]);
        $service = Container::instance()->make(AnalyticsService::class);

        // Core's non-persistent object cache stands in for a persistent one.
        wp_cache_delete($id, 'post_meta');
        update_meta_cache('post', [$id]);
        self::assertIsArray(wp_cache_get($id, 'post_meta'), 'Fixture: the popup\'s meta is cached.');

        foreach ([1, 2] as $expected) {
            $service->track($id, 'impression');

            $cached = wp_cache_get($id, 'post_meta');
            self::assertIsArray($cached, 'Recording an event must not evict the popup\'s cached meta.');
            self::assertArrayHasKey('_elemacy_popup_triggers', $cached, 'Its other cached meta (triggers) is still there.');
            self::assertArrayHasKey('_elementor_data', $cached, 'Including its Elementor data.');

            $stored = (string) $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $id, AnalyticsService::META_IMPRESSIONS));
            self::assertSame((string) $expected, $stored);
            self::assertSame([$stored], $cached[AnalyticsService::META_IMPRESSIONS], 'The cached counter equals the stored one.');
        }

        self::assertSame(2, $service->get($id)['impressions'], 'Reading through the cache gives the same count.');
    }

    public function testFailedUpdateDoesNotFallbackToInsertion(): void
    {
        global $wpdb;
        $id = $this->documents->create('Counter failure', 'popup');
        $break = static function ($sql) {
            return strpos($sql, 'SET meta_value = GREATEST') !== false ? 'INVALID COUNTER UPDATE' : $sql;
        };
        add_filter('query', $break);
        $previous = $wpdb->suppress_errors(true);
        try {
            (Container::instance()->make(AnalyticsService::class))->record_impression($id);
            self::fail('Failed SQL was accepted.');
        } catch (\RuntimeException $error) {
            self::assertSame([], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
        } finally {
            remove_filter('query', $break);
            $wpdb->suppress_errors($previous);
        }
    }

    private function concurrent(int $id, array $operations): void
    {
        $barrier = dirname($GLOBALS['elemacy_test_environment']['root']) . '/analytics-barrier-' . wp_generate_uuid4();
        $workers = [];
        try {
            foreach ($operations as $number => $operation) {
                $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/analytics-request.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                fwrite($pipes[0], wp_json_encode(['id' => $id, 'operation' => $operation, 'barrier' => $barrier, 'worker' => $number]));
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 20;
            do {
                $ready = count(glob($barrier . '-*')) === count($operations);
                if (!$ready) {
                    usleep(10000);
                }
            } while (!$ready && microtime(true) < $deadline);
            self::assertTrue($ready, 'Workers did not reach simultaneous-start barrier.');
            file_put_contents($barrier, 'go');
            foreach ($workers as [$process, $pipes]) {
                $body = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $errors);
                self::assertSame('accepted', $body, $errors);
            }
            $workers = [];
            wp_cache_delete($id, 'post_meta');
        } finally {
            foreach ($workers as [$process, $pipes]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
            foreach (glob($barrier . '*') as $file) {
                unlink($file);
            }
        }
    }
}
