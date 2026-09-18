<?php
require dirname(__DIR__) . '/bootstrap.php';
try {
    (new \Elemacy\Modules\Forms\Services\SubmissionRateLimiter())->consume($argv[1]);
    echo 'accepted';
} catch (\Elemacy\Core\Exceptions\HttpException $error) {
    echo 'limited';
}
