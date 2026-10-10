<?php

namespace Elemacy\Tests\Integration\Fixtures;

/**
 * One front-end page request, rendered in a fresh PHP process by
 * theme-page-request.php so nothing a previous render loaded or rendered
 * carries over. Returns the page and how many times each third-party shell
 * hook callback ran, including runs whose output was discarded.
 */
final class ThemePageRequest
{
    /**
     * @param string[] $keep_native Sides opted out of native chrome suppression.
     * @return array{html: string, calls: array<string, int>}
     */
    public static function render(string $theme, string $url, string $user = 'guest', array $keep_native = []): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/theme-page-request.php'], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start a theme page request.');
        }
        fwrite($pipes[0], wp_json_encode(['theme' => $theme, 'url' => $url, 'user' => $user, 'keep_native' => $keep_native]));
        fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new \RuntimeException('Theme page request failed: ' . $errors . $body);
        }

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}
