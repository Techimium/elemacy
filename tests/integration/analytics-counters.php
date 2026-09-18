<?php

namespace Elemacy\Tests\Integration;

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

    public function testDuplicateNormalizationPreservesConcurrentEventsAndRepeatedRuns(): void
    {
        $id = $this->documents->create('Counter normalization', 'popup');
        add_post_meta($id, AnalyticsService::META_IMPRESSIONS, 5);
        add_post_meta($id, AnalyticsService::META_IMPRESSIONS, 10);
        add_post_meta($id, AnalyticsService::META_IMPRESSIONS, -2);
        add_post_meta($id, AnalyticsService::META_CONVERSIONS, -5);
        $this->concurrent($id, array_merge(['normalize'], array_fill(0, 8, 'track')));
        self::assertSame(['18'], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
        self::assertSame(['0'], get_post_meta($id, AnalyticsService::META_CONVERSIONS));
        (new AnalyticsService())->consolidate($id);
        self::assertSame(['18'], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
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
            (new AnalyticsService())->record_impression($id);
            self::fail('Failed SQL was accepted.');
        } catch (\RuntimeException $error) {
            self::assertSame([], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
        } finally {
            remove_filter('query', $break);
            $wpdb->suppress_errors($previous);
        }
    }

    public function testSchemaMigrationResumesAfterBusyCounterAndIsIdempotent(): void
    {
        $id = $this->documents->create('Counter migration resume', 'popup');
        add_post_meta($id, AnalyticsService::META_IMPRESSIONS, 3);
        add_post_meta($id, AnalyticsService::META_IMPRESSIONS, 8);
        $marker = get_option('elemacy_pro_schema_version');
        $cursor = get_option('elemacy_pro_counter_migration_cursor', false);
        update_option('elemacy_pro_schema_version', '0.0.0', false);
        delete_option('elemacy_pro_counter_migration_cursor');
        $lock = new CounterLock($id);
        self::assertTrue($lock->acquire());
        try {
            $migration = new \ElemacyPro\Core\Migrator();
            $migration->run('0.0.0', '1');
            self::assertSame('0.0.0', get_option('elemacy_pro_schema_version'));
            self::assertSame(['3', '8'], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
            $lock->release();
            $migration->run('0.0.0', '1');
            self::assertSame('1', get_option('elemacy_pro_schema_version'));
            self::assertSame(['8'], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
            $migration->run('0.0.0', '1');
            self::assertSame(['8'], get_post_meta($id, AnalyticsService::META_IMPRESSIONS));
        } finally {
            $lock->release();
            update_option('elemacy_pro_schema_version', $marker, false);
            if ($cursor === false) {
                delete_option('elemacy_pro_counter_migration_cursor');
            } else {
                update_option('elemacy_pro_counter_migration_cursor', $cursor, false);
            }
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
