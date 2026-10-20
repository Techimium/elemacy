<?php

namespace Elemacy\Tests\Integration\Fixtures;

/**
 * One front-end page request, rendered in a fresh PHP process by
 * theme-page-request.php so nothing a previous render loaded or rendered
 * carries over. Returns the page and how many times each third-party shell
 * hook callback ran, including runs whose output was discarded, every
 * Elementor document it rendered, and where it redirected to.
 */
final class ThemePageRequest
{
    /**
     * @param string[] $keep_native Sides opted out of native chrome suppression.
     * @param bool     $force_generic Serve the theme through the generic adapter even if it has a dedicated one.
     * @param array<string, string> $third_party Hook => marker: a plugin-style callback on that hook that prints the marker.
     * @param array{options?: array<string, mixed>, exit_on_template_redirect?: string} $request
     *        `options`: option values forced for this request only (for example
     *        `elementor_css_print_method`); `exit_on_template_redirect`: a body
     *        an endpoint prints on template_redirect before exiting, as
     *        WooCommerce's `?wc-ajax=` does.
     * @return array{html: string, calls: array<string, int>, missing_callbacks: string[], renders: array<int, array{id: int, during: string[]}>, redirect: string|null}
     */
    public static function render(string $theme, string $url, string $user = 'guest', array $keep_native = [], bool $force_generic = false, array $third_party = [], array $request = []): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/theme-page-request.php'], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start a theme page request.');
        }
        fwrite($pipes[0], wp_json_encode(['theme' => $theme, 'url' => $url, 'user' => $user, 'keep_native' => $keep_native, 'force_generic' => $force_generic, 'third_party' => $third_party] + $request));
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
