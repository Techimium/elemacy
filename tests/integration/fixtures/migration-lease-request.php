<?php

require dirname(__DIR__) . '/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
require __DIR__ . '/MigrationLeaseFixture.php';
// Intentionally prime a potentially stale schema value before all workers start.
get_option($input['prefix'] . '_schema');
file_put_contents($input['barrier'] . '-' . $input['worker'], 'ready');
$deadline = microtime(true) + 20;
while (!file_exists($input['barrier']) && microtime(true) < $deadline) {
    usleep(10000);
}
if (!file_exists($input['barrier'])) {
    throw new RuntimeException('Migration barrier timed out.');
}
(new Elemacy\Tests\Integration\Fixtures\MigrationLeaseFixture($input['prefix']))->sync(false);
echo 'completed';
