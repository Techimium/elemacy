<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Migrations\DatabaseLease;
use Elemacy\Core\Migrations\BatchCursor;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/MigrationLeaseFixture.php';

final class MigrationLeasesTest extends IntegrationTestCase
{
    public function testIndependentProcessesExecuteDueMutationOnlyOnce(): void
    {
        global $wpdb;
        $prefix = 'elemacy_fixture_lease_' . wp_generate_uuid4();
        $barrier = dirname($GLOBALS['elemacy_test_environment']['root']) . '/' . $prefix;
        add_option($prefix . '_schema', '0.0.0', '', false);
        add_option($prefix . '_counter', '0', '', false);
        $workers = [];
        try {
            for ($number = 0; $number < 8; ++$number) {
                $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/migration-lease-request.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                fwrite($pipes[0], wp_json_encode(['prefix' => $prefix, 'barrier' => $barrier, 'worker' => $number]));
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 20;
            while (count(glob($barrier . '-*')) !== 8 && microtime(true) < $deadline) {
                usleep(10000);
            }
            self::assertCount(8, glob($barrier . '-*'));
            file_put_contents($barrier, 'go');
            foreach ($workers as [$process, $pipes]) {
                $body = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $errors);
                self::assertSame('completed', $body, $errors);
            }
            $workers = [];
            self::assertSame('1', $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $prefix . '_counter')));
            // This process still cached the old marker: claiming a new lease must reread it.
            (new Fixtures\MigrationLeaseFixture($prefix))->sync(false);
            self::assertSame('1', $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $prefix . '_counter')));
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
            foreach (['_schema', '_counter', '_schema_lock'] as $suffix) {
                delete_option($prefix . $suffix);
            }
        }
    }

    public function testExpiredLeaseRecoversWithoutOldOwnerDeletingIt(): void
    {
        global $wpdb;
        $key = 'elemacy_fixture_lease_' . wp_generate_uuid4();
        $old = new DatabaseLease($key);
        $new = new DatabaseLease($key);
        self::assertTrue($old->acquire(30));
        self::assertFalse($new->acquire(30));
        $wpdb->update($wpdb->options, ['option_value' => (time() - 1) . ':expired'], ['option_name' => $key]);
        self::assertTrue($new->acquire(30));
        $old->release();
        try {
            $new->assert_owned();
            self::assertSame('no', $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $key)));
        } finally {
            $new->release();
        }
    }

    public function testCursorDoesNotAdvanceAfterLosingItsLease(): void
    {
        global $wpdb;
        $name = 'fixture_' . wp_generate_uuid4();
        $cursor = new BatchCursor($name);
        self::assertTrue($cursor->acquire_lock());
        $key = 'elemacy_migration_cursor_lock_' . $name;
        $wpdb->update($wpdb->options, ['option_value' => (time() - 1) . ':expired'], ['option_name' => $key]);
        try {
            $cursor->advance(20);
            self::fail('Expired cursor lease advanced progress.');
        } catch (\RuntimeException $error) {
            self::assertSame(0, $cursor->get_offset());
        } finally {
            $cursor->release_lock();
            delete_option($key);
            delete_option('elemacy_migration_cursor_' . $name);
        }
    }
}
