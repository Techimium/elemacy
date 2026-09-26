<?php

/**
 * Performance evidence for template precedence: resolution now evaluates
 * every candidate of a type instead of stopping at the first match, so the
 * cost is linear in the number of templates of that type rather than in the
 * position of the first matching one.
 *
 * This measures the worst case — every candidate matches, so a first-match
 * resolver would have stopped at candidate one while the current one scores
 * all of them — and fails if a single resolution exceeds the budget below.
 *
 *   ELEMACY_TEST_ENV=<env.json> php tests/performance/template-precedence-resolution.php
 *   ELEMACY_TEST_ENV=<env.json> php tests/performance/template-precedence-resolution.php --templates=500 --runs=200
 *
 * Not a PHPUnit test: it reports a measurement, and its fixture deliberately
 * creates far more templates of one type than any real site has.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/../integration/bootstrap.php';

use Elemacy\Conditions\ConditionRepository;
use Elemacy\TemplateLibrary\ResolverCache;
use Elemacy\TemplateLibrary\TemplateResolver;

function elemacy_precedence_arg(string $name, string $default): string
{
    foreach ($GLOBALS['argv'] as $arg) {
        if (0 === strpos($arg, "--{$name}=")) {
            return substr($arg, strlen("--{$name}="));
        }
    }

    return $default;
}

$over_budget = false;
$template_count = (int) elemacy_precedence_arg('templates', '200');
$runs = (int) elemacy_precedence_arg('runs', '100');

/**
 * The budget one resolution of one location must stay inside. A request
 * resolves at most a handful of locations (header, footer, the content
 * location), so this is the per-location share of a single page render.
 */
$budget_ms = (float) elemacy_precedence_arg('budget-ms', '5.0');

$administrator = get_user_by('login', 'elemacy_test_admin');

if (!$administrator instanceof WP_User) {
    fwrite(STDERR, "Requires the disposable integration environment; provision with tests/integration/setup.py.\n");
    exit(1);
}

wp_set_current_user($administrator->ID);

$conditions = new ConditionRepository();
$created = [];

$post_id = wp_insert_post([
    'post_title' => 'Precedence performance target',
    'post_type' => 'post',
    'post_status' => 'publish',
    'post_content' => 'Precedence performance fixture.',
], true);

if (is_wp_error($post_id)) {
    fwrite(STDERR, 'Could not create the fixture post: ' . $post_id->get_error_message() . "\n");
    exit(1);
}

try {
    // Worst case: every template matches, so every one must be scored.
    for ($i = 0; $i < $template_count; $i++) {
        $document = \Elementor\Plugin::instance()->documents->create(
            'wp-page',
            ['post_type' => 'elemacy_library', 'post_title' => 'Precedence perf ' . $i, 'post_status' => 'publish'],
            ['_elemacy_template_type' => 'header']
        );

        if (is_wp_error($document)) {
            throw new RuntimeException($document->get_error_message());
        }

        $id = $document->get_main_id();
        $created[] = $id;

        $conditions->save($id, [
            ['type' => 'general/entire_site', 'value' => '', 'operator' => 'include'],
            ['type' => 'singular/post_type', 'value' => 'post', 'operator' => 'include'],
        ]);
    }

    (new ResolverCache())->invalidate();
    TemplateResolver::reset();

    // Warm the candidate index so the measurement is the scoring pass alone,
    // which is the part this change made linear.
    $resolver = TemplateResolver::instance();
    $resolver->resolve('header');

    $started = microtime(true);

    for ($run = 0; $run < $runs; $run++) {
        TemplateResolver::reset();
        $resolved = $resolver->resolve('header');
    }

    $elapsed_ms = (microtime(true) - $started) * 1000;
    $per_resolution_ms = $elapsed_ms / max(1, $runs);

    printf("templates of one type : %d\n", $template_count);
    printf("resolutions measured  : %d\n", $runs);
    printf("per resolution        : %.4f ms\n", $per_resolution_ms);
    printf("per candidate         : %.4f ms\n", $per_resolution_ms / max(1, $template_count));
    printf("budget                : %.4f ms\n", $budget_ms);
    printf("resolved              : %s\n", var_export($resolved, true));

    // Never exit() from inside this try: PHP skips `finally` on exit, which
    // would leave every fixture template behind in the database.
    if ($per_resolution_ms > $budget_ms) {
        fwrite(STDERR, sprintf(
            "OVER BUDGET: %.4f ms per resolution exceeds %.4f ms.\n",
            $per_resolution_ms,
            $budget_ms
        ));
        $over_budget = true;
    } else {
        echo "within budget\n";
    }
} finally {
    foreach ($created as $id) {
        wp_delete_post($id, true);
    }

    wp_delete_post((int) $post_id, true);
    (new ResolverCache())->invalidate();
    TemplateResolver::reset();
}

exit($over_budget ? 1 : 0);
