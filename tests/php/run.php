<?php

/**
 * Executes the pure-logic unit tests for release-critical server code:
 * condition evaluation semantics, the migration runner, and the request
 * sanitizer. Run with:  php tests/php/run.php
 */

require __DIR__ . '/bootstrap.php';

use Elemacy\Conditions\BaseCondition;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\ConditionEvaluator;
use Elemacy\Conditions\MockCondition;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\Migrator;
use Elemacy\Core\Sanitizer;
use Elemacy\Modules\Popups\Services\EditorPreview;

/* ── Test doubles ───────────────────────────────────────────────── */

/**
 * Condition whose match result is fixed at construction.
 */
final class FixedCondition extends BaseCondition
{
    private string $name;
    private bool $matches;

    public function __construct(string $name, bool $matches)
    {
        $this->name    = $name;
        $this->matches = $matches;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_type(): string
    {
        return 'general';
    }

    public function get_label(): string
    {
        return $this->name;
    }

    public function check(ConditionRuleDTO $rule): bool
    {
        return $this->matches;
    }
}

final class FixedMockCondition extends MockCondition
{
    public function get_name(): string
    {
        return 'mock/locked';
    }

    public function get_type(): string
    {
        return 'general';
    }

    public function get_label(): string
    {
        return 'Locked';
    }
}

/**
 * Migrator with an injectable, deliberately unsorted migration map that logs
 * each execution.
 */
final class RecordingMigrator extends Migrator
{
    /** @var array<int, array{string, string, string}> */
    public array $log = [];

    protected function migrations(): array
    {
        // Deliberately out of order: run() must sort by version itself.
        return [
            '1.2.0'  => function ($from, $to) {
                $this->log[] = ['1.2.0', $from, $to];
            },
            '1.0.5'  => function ($from, $to) {
                $this->log[] = ['1.0.5', $from, $to];
            },
            '1.10.0' => function ($from, $to) {
                $this->log[] = ['1.10.0', $from, $to];
            },
        ];
    }
}

final class TestableEditorPreview extends EditorPreview
{
    public function get_empty_document_state_css(): string
    {
        return $this->empty_document_state_css();
    }

    public function get_frame_css(string $wrapper): string
    {
        return $this->frame_css($wrapper);
    }

    public function get_topbar_css(string $wrapper): string
    {
        return $this->topbar_css($wrapper);
    }
}

/* ── Popup editor empty state ───────────────────────────────────── */

check('popup creation prompt is hidden only after content exists', static function () {
    $css = (new TestableEditorPreview())->get_empty_document_state_css();

    return false !== strpos(
        $css,
        '[data-elementor-type="elemacy_popup"] .elementor-section-wrap:not(:empty) + #elementor-add-new-section'
    );
});

check('popup preview wrapper adds no visual decoration', static function () {
    $preview = new TestableEditorPreview();
    $css     = $preview->get_frame_css('.elementor-123')
        . $preview->get_topbar_css('.elementor-123');

    return false === strpos($css, 'background')
        && false === strpos($css, 'border-radius')
        && false === strpos($css, 'box-shadow');
});

check('popup frontend wrapper adds no visual decoration', static function () {
    $css = file_get_contents(
        dirname(__DIR__, 2) . '/src/Modules/Popups/assets/styles/popups.css'
    );

    $box_rules = [];
    preg_match_all('/[^{}]*\.elemacy-popup__box[^{}]*\{([^}]*)\}/', $css, $box_rules);
    $box_css = implode('', $box_rules[1]);

    return false === strpos($box_css, 'background')
        && false === strpos($box_css, 'border-radius')
        && false === strpos($box_css, 'box-shadow');
});

/* ── ConditionEvaluator semantics ───────────────────────────────── */

$manager = ConditionManager::instance();
$manager->register(new FixedCondition('test/yes', true));
$manager->register(new FixedCondition('test/no', false));
$manager->register(new FixedMockCondition());

$evaluator = ConditionEvaluator::instance();

$rule = static function (string $type, string $operator = 'include'): ConditionRuleDTO {
    return ConditionRuleDTO::from_array(['type' => $type, 'operator' => $operator]);
};

check('no conditions → show everywhere', static fn() => $evaluator->evaluate([]) === true);

check('single matching include → shown', static fn() =>
    $evaluator->evaluate([$rule('test/yes')]) === true);

check('single non-matching include → hidden', static fn() =>
    $evaluator->evaluate([$rule('test/no')]) === false);

check('includes OR together (no + yes → shown)', static fn() =>
    $evaluator->evaluate([$rule('test/no'), $rule('test/yes')]) === true);

check('matching exclude vetoes a matching include', static fn() =>
    $evaluator->evaluate([$rule('test/yes'), $rule('test/yes', 'exclude')]) === false);

check('non-matching exclude alone → show everywhere', static fn() =>
    $evaluator->evaluate([$rule('test/no', 'exclude')]) === true);

check('unknown include type is skipped (→ show everywhere)', static fn() =>
    $evaluator->evaluate([$rule('test/unregistered')]) === true);

check('unknown type skipped but real include still decides', static fn() =>
    $evaluator->evaluate([$rule('test/unregistered'), $rule('test/no')]) === false);

check('mock include fails closed (pro deactivated → template hidden)', static fn() =>
    $evaluator->evaluate([$rule('mock/locked')]) === false);

check('mock exclude never vetoes (check is false)', static fn() =>
    $evaluator->evaluate([$rule('mock/locked', 'exclude'), $rule('test/yes')]) === true);

/* ── Migrator ordering, range and stamping ──────────────────────── */

check('runs due migrations in ascending version order', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.0', '2.0.0');
    $versions = array_column($migrator->log, 0);

    return $versions === ['1.0.5', '1.2.0', '1.10.0'];
});

check('stamps DB version after the last completed step', static function () {
    update_option(OptionKeys::DB_VERSION, '1.0.0');
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.0', '2.0.0');

    return get_option(OptionKeys::DB_VERSION) === '1.10.0';
});

check('skips migrations at or below the installed version', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.5', '2.0.0');

    return array_column($migrator->log, 0) === ['1.2.0', '1.10.0'];
});

check('skips migrations above the target version', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.0', '1.2.0');

    return array_column($migrator->log, 0) === ['1.0.5', '1.2.0'];
});

check('nothing runs when already current', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.10.0', '1.10.0');

    return $migrator->log === [];
});

check('callbacks receive (from, to)', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.0', '2.0.0');

    return $migrator->log[0][1] === '1.0.0' && $migrator->log[0][2] === '2.0.0';
});

/* ── Sanitizer rules ────────────────────────────────────────────── */

check('ARRAY: JSON string decodes to array', static fn() =>
    Sanitizer::apply_rule('{"a":1,"b":[2,3]}', Sanitizer::ARRAY) === ['a' => 1, 'b' => [2, 3]]);

check('ARRAY: array passes through untouched', static fn() =>
    Sanitizer::apply_rule([1, 2], Sanitizer::ARRAY) === [1, 2]);

check('ARRAY: scalar wraps instead of unserializing (object-injection guard)', static function () {
    $payload = 'O:8:"stdClass":0:{}'; // PHP-serialized — must NOT be unserialized.
    $result  = Sanitizer::apply_rule($payload, Sanitizer::ARRAY);

    return $result === [$payload];
});

check('ARRAY: empty string becomes empty array', static fn() =>
    Sanitizer::apply_rule('', Sanitizer::ARRAY) === []);

check('ARRAY_DEEP: string leaves sanitized, non-strings untouched', static function () {
    $result = Sanitizer::apply_rule(
        ['a' => "<script>x</script>ok", 'b' => 5, 'c' => ['d' => true, 'e' => " spaced  out "]],
        Sanitizer::ARRAY_DEEP
    );

    return $result['a'] === 'ok' && $result['b'] === 5 && $result['c']['d'] === true && $result['c']['e'] === 'spaced out';
});

check('DATETIME: valid input normalized to Y-m-d H:i:s', static fn() =>
    Sanitizer::apply_rule('2026-07-17T10:30:00+00:00', Sanitizer::DATETIME) === '2026-07-17 10:30:00');

check('DATETIME: garbage returns null', static fn() =>
    Sanitizer::apply_rule('not-a-date', Sanitizer::DATETIME) === null);

check('INT/BOOL casts', static fn() =>
    Sanitizer::apply_rule('42abc', Sanitizer::INT) === 42 && Sanitizer::apply_rule('1', Sanitizer::BOOL) === true);

check('null passes through every rule untouched', static fn() =>
    Sanitizer::apply_rule(null, Sanitizer::TEXT) === null);

conclude();
