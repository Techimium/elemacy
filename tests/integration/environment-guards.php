<?php

namespace Elemacy\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvironmentGuardsTest extends TestCase
{
    private ?string $invalid_manifest = null;

    protected function tearDown(): void
    {
        if ($this->invalid_manifest && is_file($this->invalid_manifest)) {
            unlink($this->invalid_manifest);
        }
        parent::tearDown();
    }

    public function testMissingConfigurationIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Set ELEMACY_TEST_ENV');
        elemacy_test_environment('');
    }

    public function testDevelopmentDatabaseIsRefusedBeforeLoadingWordPress(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing a database');
        elemacy_test_environment($this->invalidManifest(['database' => 'elemacy_test']));
    }

    public function testUnrelatedWordPressRootIsRefusedBeforeLoadingWordPress(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing a WordPress root');
        elemacy_test_environment($this->invalidManifest(['root' => dirname(__DIR__, 5)]));
    }

    public function testChangedWordPressConfigurationIsRefusedBeforeLoadingWordPress(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing a replaced WordPress configuration');
        elemacy_test_environment($this->invalidManifest(['wp_config_sha256' => str_repeat('0', 64)]));
    }

    private function invalidManifest(array $changes): string
    {
        $environment = elemacy_test_environment((string) getenv('ELEMACY_TEST_ENV'));
        $this->invalid_manifest = dirname($environment['root']) . '/invalid-environment-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($this->invalid_manifest, json_encode(array_replace($environment, $changes)));
        chmod($this->invalid_manifest, 0600);
        return $this->invalid_manifest;
    }
}
