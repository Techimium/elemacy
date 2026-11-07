<?php

namespace Elemacy\Tests\Integration\Fixtures;

final class MigrationLeaseFixture extends \Elemacy\Core\Migrator
{
    private string $prefix;

    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    protected function option_key(): string
    {
        return $this->prefix . '_schema';
    }

    protected function migrations(): array
    {
        return ['1' => function (): void {
            global $wpdb;
            usleep(100000);
            if (1 !== $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", $this->prefix . '_counter'))) {
                throw new \RuntimeException('Fixture mutation failed.');
            }
        }];
    }
}
