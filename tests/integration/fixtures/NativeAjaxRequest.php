<?php

namespace Elemacy\Tests\Integration\Fixtures;

final class NativeAjaxRequest
{
    public static function loop(string $request, string $paginate_base = ''): string
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/public-loop-ajax-request.php'], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start a native AJAX fixture.');
        }
        fwrite($pipes[0], wp_json_encode(['request' => $request, 'paginate_base' => $paginate_base]));
        fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new \RuntimeException('Native AJAX fixture failed: ' . $errors);
        }
        return $body;
    }
}
