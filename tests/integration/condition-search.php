<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Conditions\ConditionSearchService;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * A condition's saved value is always shown by name in the editor, whatever
 * became of the item it names, while searching offers only published content.
 */
final class ConditionSearchTest extends IntegrationTestCase
{
    private const CONFIG = ['entity' => 'post', 'post_type' => 'page'];

    /** @var int[] */
    private array $pages = [];

    protected function tearDown(): void
    {
        try {
            foreach ($this->pages as $id) {
                wp_delete_post($id, true);
            }
            $this->pages = [];
        } finally {
            parent::tearDown();
        }
    }

    private function page(string $title, string $status = 'publish'): int
    {
        $id = wp_insert_post(['post_type' => 'page', 'post_status' => $status, 'post_title' => $title], true);
        self::assertIsInt($id);
        $this->pages[] = $id;

        return $id;
    }

    public function testASavedTargetIsNamedWhateverItsStatusAndSearchOffersOnlyPublishedContent(): void
    {
        $id = $this->page('Condition search drafted target');
        wp_update_post(['ID' => $id, 'post_status' => 'draft']);

        self::assertSame(
            [['value' => (string) $id, 'label' => 'Condition search drafted target']],
            (new ConditionSearchService())->search(self::CONFIG, '', [$id]),
            'A drafted target still resolves to its title.'
        );
        self::assertSame([], (new ConditionSearchService())->search(self::CONFIG, 'Condition search drafted'), 'Searching offers only published pages.');
    }

    public function testADeletedTargetIsReturnedAsUnavailableWithItsId(): void
    {
        $kept = $this->page('Condition search kept target');
        $gone = $this->page('Condition search deleted target');
        wp_delete_post($gone, true);

        self::assertSame([
            ['value' => (string) $kept, 'label' => 'Condition search kept target'],
            ['value' => (string) $gone, 'label' => sprintf('#%d (unavailable)', $gone), 'unavailable' => true],
        ], (new ConditionSearchService())->search(self::CONFIG, '', [$kept, $gone]));
    }
}
