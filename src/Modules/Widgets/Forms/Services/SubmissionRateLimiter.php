<?php

namespace Elemacy\Modules\Widgets\Forms\Services;

use Elemacy\Http\Exceptions\HttpException;

defined('ABSPATH') || exit;

/** Fixed-window, per-IP bucket with database compare-and-swap and bounded retries. */
final class SubmissionRateLimiter
{
    private int $limit;
    private int $window;

    public function __construct(int $limit = 5, int $window = 60)
    {
        $this->limit = max(1, min(100, $limit));
        $this->window = max(1, min(3600, $window));
    }

    public function consume(string $ip): void
    {
        global $wpdb;
        $key = 'elemacy_form_limit_' . hash_hmac('sha256', $ip, wp_salt('auth'));
        $now = time();
        // Expired rows are expendable; bounded cleanup prevents an ever-growing visitor history.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) <= %d LIMIT 20", $wpdb->esc_like('elemacy_form_limit_') . '%', $now));
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $old = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key));
            if ($old === null) {
                $new = ($now + $this->window) . ':1';
                if (1 === $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, $new))) {
                    return;
                }
                continue;
            }
            $parts = explode(':', $old);
            $expires = (int) ($parts[0] ?? 0);
            $count = (int) ($parts[1] ?? $this->limit);
            if ($expires > $now && $count >= $this->limit) {
                break;
            }
            $new = $expires <= $now ? ($now + $this->window) . ':1' : $expires . ':' . ($count + 1);
            if (1 === $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $new, $key, $old))) {
                return;
            }
        }
        throw new HttpException(esc_html__('Too many submissions. Please try again in a minute.', 'elemacy'), 429);
    }
}
