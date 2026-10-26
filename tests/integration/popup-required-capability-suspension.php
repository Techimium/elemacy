<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Modules\Popups\Constants\MetaKeys;
use Elemacy\Modules\Popups\Services\PopupManager;
use Elemacy\Modules\Popups\Services\TriggerManager;
use Elemacy\Modules\Popups\Triggers\BaseTrigger;
use Elemacy\Modules\Popups\Triggers\MockTrigger;
use Elemacy\Tests\Integration\Fixtures\LibraryDocument;
use Elemacy\Tests\Integration\Fixtures\ThemeLifecycleHarness;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemeLifecycleHarness.php';

/**
 * A non-functional stand-in only until {@see RecoverableRealTrigger} below
 * registers under the same name — simulating an extension being inactive,
 * then activated. Kept separate from the shared elemacy_fixture/mock_trigger
 * fixture (registered by fixtures/mu-plugins/popup-availability-suspension.php)
 * so overriding it here can never affect the other tests in this file that
 * rely on that shared fixture staying a mock throughout the whole run.
 */
final class RecoverableMockTrigger extends MockTrigger
{
    public function get_name(): string
    {
        return 'elemacy_fixture/recoverable_trigger';
    }

    public function get_label(): string
    {
        return 'Recoverable Fixture Trigger';
    }
}

final class RecoverableRealTrigger extends BaseTrigger
{
    public function get_name(): string
    {
        return 'elemacy_fixture/recoverable_trigger';
    }

    public function get_label(): string
    {
        return 'Recoverable Fixture Trigger (now real)';
    }
}

/**
 * design.md D5/task 4.3: a popup carrying a required rule or trigger that
 * cannot actually run — unregistered entirely, or only a non-functional mock
 * stand-in (elemacy_fixture/mock_rule, elemacy_fixture/mock_trigger,
 * registered regardless of whether the sibling Pro plugin is active by
 * fixtures/mu-plugins/popup-availability-suspension.php) — must never reach
 * the frontend: no popup markup, no engine script, no config naming it.
 * Treating the missing capability as absent (skipped) rather than
 * unavailable would let the popup display to an audience its own,
 * unenforceable rule/trigger could have excluded or was meant to gate.
 *
 * Control cases confirm the fixture technique and the pre-existing
 * available/real-predicate paths still work: a fully available rule+trigger
 * combination still matches and renders, and a real (non-mock,
 * non-unknown) rule whose predicate genuinely evaluates false still excludes
 * the popup the same way it always has — distinct code paths from
 * "unavailable", both ending in "not shown", per FeatureAvailability's
 * contract.
 */
final class PopupRequiredCapabilitySuspensionTest extends IntegrationTestCase
{
    private ThemeLifecycleHarness $harness;
    private int $host_id;
    private array $owned = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->harness = new ThemeLifecycleHarness();
        if (!wp_get_theme('twentytwentyfive')->exists()) {
            self::markTestSkipped("Theme 'twentytwentyfive' is not available in this environment.");
        }
        $this->harness->switch_theme('twentytwentyfive');

        $this->host_id = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish',
            'post_title' => 'Popup capability suspension host', 'post_content' => 'Plain content, no Elementor.',
        ], true);
        self::assertIsInt($this->host_id);

        update_option('elementor_element_cache_ttl', 'disable');
    }

    protected function tearDown(): void
    {
        try {
            foreach (array_reverse($this->owned) as $id) {
                wp_delete_post($id, true);
            }
            wp_delete_post($this->host_id, true);
        } finally {
            delete_option('elementor_element_cache_ttl');
            $this->harness->restore();
            parent::tearDown();
        }
    }

    private function create_popup(string $marker, array $triggers, array $rules): int
    {
        $id = $this->documents->create($marker, 'popup', 'publish', LibraryDocument::heading($marker));
        $this->owned[] = $id;

        if (!empty($triggers)) {
            update_post_meta($id, MetaKeys::TRIGGERS, $triggers);
        }

        if (!empty($rules)) {
            update_post_meta($id, MetaKeys::RULES, $rules);
        }

        return $id;
    }

    private function render_host(): string
    {
        return $this->harness->render(get_permalink($this->host_id));
    }

    public function testPopupWithAnUnknownTriggerTypeNeverReachesTheFrontend(): void
    {
        $this->create_popup('Unknown trigger popup', [
            ['id' => 'r1', 'type' => 'elemacy_fixture/totally_unregistered_trigger', 'params' => []],
        ], []);

        $html = $this->render_host();

        self::assertSame(0, substr_count($html, 'elemacy-popup'), 'A popup with an unregistered trigger must not render at all: ' . $html);
        self::assertSame(0, substr_count($html, 'elemacy-popups-engine'), 'The popup engine must not enqueue when the only popup is suspended.');
    }

    public function testPopupWithAnUnknownRuleTypeNeverReachesTheFrontend(): void
    {
        $this->create_popup('Unknown rule popup', [
            ['id' => 't1', 'type' => 'page_load', 'params' => []],
        ], [
            ['id' => 'r1', 'type' => 'elemacy_fixture/totally_unregistered_rule', 'params' => []],
        ]);

        wp_set_current_user(0); // Anonymous: nothing else would exclude this popup.
        $html = $this->render_host();

        self::assertSame(0, substr_count($html, 'elemacy-popup'), 'A popup with an unregistered rule must not render at all: ' . $html);
        self::assertSame(0, substr_count($html, 'elemacy-popups-engine'), 'The popup engine must not enqueue when the only popup is suspended.');
    }

    public function testPopupWithAMockTriggerNeverReachesTheFrontendRegardlessOfProState(): void
    {
        $this->create_popup('Mock trigger popup', [
            ['id' => 't1', 'type' => 'elemacy_fixture/mock_trigger', 'params' => []],
        ], []);

        $html = $this->render_host();

        self::assertSame(0, substr_count($html, 'elemacy-popup'), 'A popup whose only trigger is a non-functional mock must not render at all: ' . $html);
        self::assertSame(0, substr_count($html, 'elemacy-popups-engine'), 'The popup engine must not enqueue when the only popup is suspended.');
    }

    public function testPopupWithAMockRuleNeverReachesTheFrontendRegardlessOfProState(): void
    {
        $this->create_popup('Mock rule popup', [
            ['id' => 't1', 'type' => 'page_load', 'params' => []],
        ], [
            ['id' => 'r1', 'type' => 'elemacy_fixture/mock_rule', 'params' => []],
        ]);

        wp_set_current_user(0); // Anonymous: nothing else would exclude this popup.
        $html = $this->render_host();

        self::assertSame(0, substr_count($html, 'elemacy-popup'), 'A popup gated by a non-functional mock rule must not render at all, even though it could have excluded nobody: ' . $html);
        self::assertSame(0, substr_count($html, 'elemacy-popups-engine'), 'The popup engine must not enqueue when the only popup is suspended.');
    }

    public function testPopupWithOnlyAvailableCapabilitiesStillMatchesAndRenders(): void
    {
        $this->create_popup('Fully available popup', [
            ['id' => 't1', 'type' => 'page_load', 'params' => []],
        ], [
            ['id' => 'r1', 'type' => 'logged_in', 'params' => []],
        ]);

        wp_set_current_user(0); // Anonymous: passes the real "hide for logged-in users" predicate below.
        $html = $this->render_host();

        self::assertGreaterThan(0, substr_count($html, 'elemacy-popup'), 'A popup whose rule and trigger are both real and available must still render: ' . $html);
        self::assertGreaterThan(0, substr_count($html, 'elemacy-popups-engine'), 'The popup engine must enqueue when a popup actually matches.');
    }

    public function testARealRuleThatGenuinelyEvaluatesFalseStillExcludesTheAvailablePopup(): void
    {
        // IntegrationTestCase::setUp() leaves an administrator logged in, so
        // this real, available, server-evaluable rule's own predicate
        // legitimately fails — a different reason for the same "not shown"
        // outcome as the unavailable cases above, and must not be confused
        // with them.
        self::assertTrue(is_user_logged_in(), 'This scenario requires a logged-in current user.');

        $this->create_popup('Logged-in-excluded popup', [
            ['id' => 't1', 'type' => 'page_load', 'params' => []],
        ], [
            ['id' => 'r1', 'type' => 'logged_in', 'params' => []],
        ]);

        $html = $this->render_host();

        self::assertSame(0, substr_count($html, 'elemacy-popup'), 'A real, available rule whose predicate fails must still exclude the popup: ' . $html);
    }

    public function testRestoringAvailabilityReusesThePreservedPopupSettingsWithoutRecreation(): void
    {
        TriggerManager::instance()->register(new RecoverableMockTrigger());

        $popup_id = $this->create_popup('Recoverable popup', [
            ['id' => 't1', 'type' => 'elemacy_fixture/recoverable_trigger', 'params' => []],
        ], []);

        self::assertSame(
            0,
            substr_count($this->render_host(), 'elemacy-popup'),
            'Must be suspended while its only trigger is still a non-functional mock.'
        );

        // Simulate the extension activating: the real implementation
        // registers under the same name, overriding the mock — exactly how
        // elemacy-pro overrides free's mocks. Nothing about the popup's own
        // saved document or trigger configuration is touched here.
        TriggerManager::instance()->register(new RecoverableRealTrigger());

        $html = $this->render_host();
        self::assertGreaterThan(
            0,
            substr_count($html, 'elemacy-popup'),
            'Once available, the same saved popup must render using its preserved settings, with no recreation: ' . $html
        );
        self::assertStringContainsString('elemacy-popup-' . $popup_id, $html, 'It must be the very same popup document, not a new one.');
    }
}
