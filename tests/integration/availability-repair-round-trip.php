<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Conditions\ConditionRepository;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Modules\Popups\DTO\RuleDTO;
use Elemacy\Modules\Popups\DTO\TriggerDTO;
use Elemacy\Modules\Popups\DTO\UpdatePopupDTO;
use Elemacy\Modules\Popups\Resources\PopupResource;
use Elemacy\Modules\Popups\Services\PopupService;
use Elemacy\Modules\Popups\Services\RuleManager;
use Elemacy\Modules\Popups\Services\TriggerManager;
use Elemacy\Tests\Integration\Fixtures\LibraryDocument;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * design.md D5/task 4.4: an unavailable saved condition/rule/trigger
 * (unknown, or a non-functional mock stand-in) must round-trip
 * byte-equivalently through an edit that never touches it — a title-only
 * popup update, or any ConditionRepository save/get cycle used by both
 * Popups and Theme Builder — and the resource that serves it back to the
 * admin app must keep reporting its availability so it can be identified
 * and repaired, rather than the value silently disappearing or the item
 * becoming a default/match-all definition.
 */
final class AvailabilityRepairRoundTripTest extends IntegrationTestCase
{
    private array $posts = [];

    protected function tearDown(): void
    {
        try {
            foreach (array_reverse($this->posts) as $id) {
                wp_delete_post($id, true);
            }
        } finally {
            $this->posts = [];
            parent::tearDown();
        }
    }

    private function create_host(string $title): int
    {
        $id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title], true);
        self::assertIsInt($id);
        $this->posts[] = $id;

        return $id;
    }

    public function testConditionRepositorySaveGetRoundTripsUnknownAndMockConditionsUnchanged(): void
    {
        $host = $this->create_host('Availability round-trip host');
        $repository = new ConditionRepository();

        $stored = [
            ['id' => 'c1', 'type' => 'elemacy_fixture/totally_unregistered_condition', 'operator' => 'include', 'value' => 'x'],
            ['id' => 'c2', 'type' => 'general/entire_site', 'operator' => 'exclude', 'value' => ''],
        ];

        $repository->save($host, $stored);
        $fetched = ConditionRuleDTO::to_arrays($repository->get($host));

        self::assertSame($stored, $fetched, 'A save()/get() cycle that never touches an unavailable condition must return it byte-for-byte unchanged.');

        // Saving again (simulating a title-only edit resubmitting the exact
        // same fetched rules) must not further mutate or drop anything.
        $repository->save($host, $fetched);
        self::assertSame($stored, ConditionRuleDTO::to_arrays($repository->get($host)));

        self::assertTrue(
            ConditionManager::instance()->get_availability('elemacy_fixture/totally_unregistered_condition')->is_unavailable(),
            'The unknown identifier must still be reported unavailable after the round-trip, not silently adopted as valid.'
        );
    }

    public function testPopupTitleOnlyUpdatePreservesUnavailableRulesAndTriggersByteEquivalently(): void
    {
        $popup_id = $this->documents->create('Availability repair popup', 'popup', 'publish', LibraryDocument::heading('Availability repair popup'));
        $this->posts[] = $popup_id;

        update_post_meta($popup_id, \Elemacy\Modules\Popups\Constants\MetaKeys::TRIGGERS, [
            ['id' => 't1', 'type' => 'elemacy_fixture/mock_trigger', 'params' => []],
        ]);
        update_post_meta($popup_id, \Elemacy\Modules\Popups\Constants\MetaKeys::RULES, [
            ['id' => 'r1', 'type' => 'elemacy_fixture/totally_unregistered_rule', 'params' => ['note' => 'kept as authored']],
        ]);

        $service = new PopupService();
        $before = $service->get($popup_id);
        self::assertNotNull($before);

        // Exactly what a real edit screen does: reload the current values,
        // change only the title, and resubmit everything else untouched.
        $update = UpdatePopupDTO::from_array([
            'id' => $popup_id,
            'title' => 'Availability repair popup (renamed)',
            'type' => $before->type,
            'status' => $before->status,
            'conditions' => ConditionRuleDTO::to_arrays($before->conditions),
            'triggers' => TriggerDTO::to_arrays($before->triggers),
            'rules' => RuleDTO::to_arrays($before->rules),
        ]);
        $service->update($popup_id, $update);

        $after = $service->get($popup_id);
        self::assertSame('Availability repair popup (renamed)', $after->title, 'The title-only edit must actually apply.');
        self::assertSame(
            TriggerDTO::to_arrays($before->triggers),
            TriggerDTO::to_arrays($after->triggers),
            'The unavailable trigger must survive a title-only edit byte-equivalently.'
        );
        self::assertSame(
            RuleDTO::to_arrays($before->rules),
            RuleDTO::to_arrays($after->rules),
            'The unavailable rule, including its preserved params, must survive a title-only edit byte-equivalently.'
        );

        // The resource the admin app reads back must still flag both as
        // unavailable (identifiable for repair), not present them as valid
        // nor silently omit them.
        $resource = PopupResource::make($after);
        self::assertFalse($resource['triggers'][0]['availability']['available']);
        self::assertSame(
            \Elemacy\Core\Capabilities\FeatureAvailability::EXTENSION_INACTIVE,
            $resource['triggers'][0]['availability']['reason_code']
        );
        self::assertFalse($resource['rules'][0]['availability']['available']);
        self::assertSame(
            \Elemacy\Core\Capabilities\FeatureAvailability::UNKNOWN_IDENTIFIER,
            $resource['rules'][0]['availability']['reason_code']
        );

        // Never silently converted into a default/match-all definition: the
        // manager-level lookups the resource relied on must still resolve
        // the same way directly, independent of the resource layer itself.
        self::assertTrue(TriggerManager::instance()->get_availability('elemacy_fixture/mock_trigger')->is_unavailable());
        self::assertTrue(RuleManager::instance()->get_availability('elemacy_fixture/totally_unregistered_rule')->is_unavailable());
    }
}
