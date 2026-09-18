<?php

/** Stable CLI entry point for the native PHPUnit suite, separate from fast stubs. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

try {
    require_once __DIR__ . '/environment.php';
    elemacy_test_environment((string) getenv('ELEMACY_TEST_ENV'));
    require dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!class_exists(PHPUnit\TextUI\Command::class)) {
        throw new RuntimeException('Run composer install with development dependencies to install PHPUnit.');
    }

    $arguments = array_slice($argv, 1);
    $scenarios = [
        'environment-guards.php' => 'EnvironmentGuardsTest',
        'document-lifecycle.php' => 'DocumentLifecycleTest',
        'fixture-catalog.php' => 'FixtureCatalogTest',
        'public-loop.php' => 'PublicLoopTest',
        'public-loop-request.php' => 'PublicLoopRequestTest',
        'published-loop-definition.php' => 'PublishedLoopDefinitionTest',
        'public-render-policy.php' => 'PublicRenderPolicyTest',
        'current-query-archives.php' => 'CurrentQueryArchivesTest',
        'loop-ineligibility-notice.php' => 'LoopIneligibilityNoticeTest',
    ];
    if (isset($arguments[0], $scenarios[$arguments[0]])) {
        array_splice($arguments, 0, 1, ['--filter', $scenarios[$arguments[0]]]);
    }
    exit((new PHPUnit\TextUI\Command())->run(array_merge([
        'phpunit', '--configuration', __DIR__ . '/phpunit.xml.dist',
    ], $arguments), false));
} catch (Throwable $error) {
    fwrite(STDERR, 'Integration bootstrap refused: ' . $error->getMessage() . "\n");
    exit(1);
}
