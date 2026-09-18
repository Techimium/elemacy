<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Tests\Integration\Fixtures\ArchitectureCatalog;
use ElemacyPro\Extensions\LoopDataSources\AcfRepeaterDataSource;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ArchitectureCatalog.php';

/**
 * Constructs ArchitectureCatalog, which requires ACF Pro's repeater
 * field type (task 9.6: excluded from the Free-only CI job that runs
 * without a licensed ACF Pro installation; run directly with
 * --acf-plugin for full local coverage).
 *
 * @group requires-acf-pro
 */
final class AcfRepeaterPublicPolicyTest extends IntegrationTestCase
{
    private ArchitectureCatalog $fixtures;
    private array $catalog;
    private AcfRepeaterDataSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = new ArchitectureCatalog();
        try {
            $this->catalog = $this->fixtures->create();
        } catch (\Throwable $error) {
            $this->fixtures->cleanup();
            throw $error;
        }
        $this->source = new AcfRepeaterDataSource();
        wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        try {
            $this->fixtures->cleanup();
        } finally {
            parent::tearDown();
        }
    }

    public function testPublishedSpecificPostRowsRender(): void
    {
        $settings = $this->source->get_public_settings($this->catalog['sources']['acf_repeater'], [], 1);
        self::assertSame($this->catalog['posts']['published'], $settings['specific_post_id']);

        $result = $this->source->get_items($settings);
        self::assertCount(2, $result->items);
    }

    /** @dataProvider inaccessibleSpecificPosts */
    public function testInaccessibleSpecificPostIsRejectedForGuests(string $status): void
    {
        $saved = $this->catalog['sources']['acf_repeater'];
        $saved['specific_post_id'] = $this->catalog['posts'][$status];

        $this->expectException(ValidationException::class);
        $this->source->get_public_settings($saved, [], 1);
    }

    public static function inaccessibleSpecificPosts(): array
    {
        return [
            'draft' => ['draft'],
            'private' => ['private'],
            'password protected' => ['protected'],
        ];
    }

    public function testUnregisteredOptionsPageIsRejectedForGuests(): void
    {
        $saved = [
            'repeater_field' => $this->catalog['acf']['name'],
            'source_type' => 'options_page',
            'options_page_id' => 'not_a_registered_options_page',
            'rows_number' => 2, 'rows_offset' => 0,
        ];

        $this->expectException(ValidationException::class);
        $this->source->get_public_settings($saved, [], 1);
    }

    public function testRegisteredOptionsPageRowsRender(): void
    {
        $saved = [
            'repeater_field' => $this->catalog['acf']['name'],
            'source_type' => 'options_page',
            'options_page_id' => $this->catalog['acf']['options_id'],
            'rows_number' => 2, 'rows_offset' => 0,
        ];

        $settings = $this->source->get_public_settings($saved, [], 1);
        $result = $this->source->get_items($settings);
        self::assertCount(2, $result->items);
    }

    public function testCurrentPostComesFromVerifiedContextNeverFromSettings(): void
    {
        $saved = [
            'repeater_field' => $this->catalog['acf']['name'],
            'source_type' => 'current_post',
            'rows_number' => 2, 'rows_offset' => 0,
            // A spoofed current_post_id alongside saved settings must be
            // ignored — only the placement-verified $context supplies it.
            'current_post_id' => $this->catalog['posts']['draft'],
        ];

        $settings = $this->source->get_public_settings($saved, ['post_id' => $this->catalog['posts']['published']], 1);
        self::assertSame($this->catalog['posts']['published'], $settings['current_post_id']);
    }

    public function testCurrentPostWithoutVerifiedContextIsRejected(): void
    {
        $saved = [
            'repeater_field' => $this->catalog['acf']['name'],
            'source_type' => 'current_post',
            'rows_number' => 2, 'rows_offset' => 0,
        ];

        $this->expectException(ValidationException::class);
        $this->source->get_public_settings($saved, [], 1);
    }

    public function testUnregisteredOrRenamedFieldIsRejected(): void
    {
        $saved = $this->catalog['sources']['acf_repeater'];
        $saved['repeater_field'] = 'not_a_registered_field_' . bin2hex(random_bytes(4));

        $this->expectException(ValidationException::class);
        $this->source->get_public_settings($saved, [], 1);
    }

    public function testLegacyUnlimitedRowsNumberIsBounded(): void
    {
        $saved = $this->catalog['sources']['acf_repeater'];
        $saved['rows_number'] = 0;

        $settings = $this->source->get_public_settings($saved, [], 1);
        self::assertSame(100, $settings['rows_number']);
    }
}
