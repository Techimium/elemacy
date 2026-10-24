<?php
require dirname(__DIR__) . '/bootstrap.php';
try {
    (new \Elemacy\Forms\Services\SubmissionRateLimiter())->consume($argv[1]);
    echo 'accepted';
} catch (\Elemacy\Http\Exceptions\HttpException $error) {
    echo 'limited';
}
