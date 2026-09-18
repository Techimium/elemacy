<?php

require dirname(__DIR__) . '/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$ready = $input['barrier'] . '-' . $input['worker'];
file_put_contents($ready, 'ready');
$deadline = microtime(true) + 20;
while (!file_exists($input['barrier']) && microtime(true) < $deadline) {
    usleep(10000);
}
if (!file_exists($input['barrier'])) {
    throw new RuntimeException('Analytics fixture barrier timed out.');
}
$service = \Elemacy\Core\Container::instance()->make(\ElemacyPro\Extensions\Popups\Services\AnalyticsService::class);
if ($input['operation'] === 'normalize') {
    $service->consolidate($input['id']);
} else {
    $service->record_impression($input['id']);
}
echo 'accepted';
