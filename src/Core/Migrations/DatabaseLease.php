<?php

namespace Elemacy\Core\Migrations;

defined('ABSPATH') || exit;

/** Non-autoloaded, atomically claimed lease for bounded idempotent migration steps. */
final class DatabaseLease
{
    private string $key;
    private string $token = '';

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function acquire(int $ttl): bool
    {
        global $wpdb;
        $token = (time() + max(1, $ttl)) . ':' . wp_generate_uuid4();
        $insert = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $this->key, $token));
        if (1 === $insert || (false !== $insert && 1 === $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) <= %d", $token, $this->key, time())))) {
            $this->token = $token;
            return true;
        }
        return false;
    }

    public function assert_owned(): void
    {
        global $wpdb;
        $current = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->key));
        if ($this->token === '' || $current !== $this->token || (int) $this->token <= time()) {
            throw new \RuntimeException('Migration lease expired; the idempotent step must retry.');
        }
    }

    public function release(): void
    {
        global $wpdb;
        if ($this->token !== '') {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $this->key, $this->token));
            $this->token = '';
        }
    }
}
