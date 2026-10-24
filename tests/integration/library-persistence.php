<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Container;
use Elemacy\Conditions\ConditionRepository;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Http\Exceptions\NotFoundException;
use Elemacy\Modules\Popups\DTO\CreatePopupDTO;
use Elemacy\Modules\Popups\DTO\TriggerDTO;
use Elemacy\Modules\Popups\DTO\UpdatePopupDTO;
use Elemacy\Modules\Popups\Services\PopupService;
use Elemacy\Modules\ThemeBuilder\DTO\CreateTemplateDTO;
use Elemacy\Modules\ThemeBuilder\DTO\UpdateTemplateDTO;
use Elemacy\Modules\ThemeBuilder\Services\TemplateService;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Http\Route;
use Elemacy\Core\Hooks;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\TemplateLibrary\DocumentDuplicator;
use Elemacy\TemplateLibrary\DocumentWriter;
use Elemacy\TemplateLibrary\DTO\CreateBlockTemplateDTO;
use Elemacy\TemplateLibrary\DTO\UpdateBlockTemplateDTO;
use Elemacy\TemplateLibrary\LibraryChanged;
use Elemacy\TemplateLibrary\LibraryRepository;
use Elemacy\TemplateLibrary\ResolverCache;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\TemplateLibrary\Services\BlockTemplateService;
use Elemacy\TemplateLibrary\DTO\BlockTemplateListFilterDTO;
use Elemacy\Modules\Popups\DTO\PopupListFilterDTO;
use Elemacy\Modules\ThemeBuilder\DTO\TemplateListFilterDTO;
use Elemacy\TemplateLibrary\TypeDefinition;
use Elemacy\TemplateLibrary\TypeRegistry;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Stands in for a hypothetical extension's own Elementor document class —
 * deliberately not a real Elementor document subclass, since resolving a
 * registered type's document TYPE STRING (task 5.2) only ever calls this
 * one static method, never instantiates it.
 */
final class FixtureExtensionBlockDocument
{
    public static function get_type(): string
    {
        return 'elemacy_fixture_extension_block';
    }
}

/**
 * Injects a required-write failure at a chosen point (task 5.7) — the
 * $fail_after-th call to set_meta() throws instead of succeeding, standing
 * in for a required write that real WordPress essentially never fails on
 * its own, so the calling primitive's own compensating-cleanup path can be
 * verified deliberately rather than left untested because it never occurs.
 */
final class FailingLibraryRepository extends LibraryRepository
{
    private int $fail_after;
    private int $set_meta_calls = 0;

    /** @var int[] */
    public array $deleted = [];

    public function __construct(int $fail_after)
    {
        parent::__construct();

        $this->fail_after = $fail_after;
    }

    public function set_meta(int $id, string $key, $value): void
    {
        ++$this->set_meta_calls;

        if ($this->set_meta_calls > $this->fail_after) {
            throw new \RuntimeException('Injected required-write failure.');
        }

        parent::set_meta($id, $key, $value);
    }

    public function delete(int $id): void
    {
        $this->deleted[] = $id;
        parent::delete($id);
    }
}

/**
 * design.md D6/task 5.1: fixtures reproducing the concrete defects D6 exists
 * to fix, run against the CURRENT (pre-5.2..5.7) write paths — a deliberate
 * red baseline captured before `LibraryRepository`/`DocumentWriter`/
 * `DocumentDuplicator` replace them. Which of these currently pass or fail is
 * recorded in docs/RELEASE-VALIDATION.md; each later task in phase 5 turns
 * the relevant failures green without changing the assertions here (a test
 * whose assertion changes to accommodate new code is not verifying the fix).
 */
final class LibraryPersistenceTest extends IntegrationTestCase
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

    private function own(int $id): int
    {
        $this->posts[] = $id;

        return $id;
    }

    /* ── Block document-type desynchronization on update() ──────────── */

    public function testChangingABlockTemplatesTypeMustSyncTheElementorDocumentTypeToo(): void
    {
        $service = Container::instance()->make(BlockTemplateService::class);
        $created = $service->create(CreateBlockTemplateDTO::from_array([
            'title' => 'Block type sync', 'type' => 'section', 'status' => 'publish',
        ]));
        $this->own($created->id);

        self::assertSame(
            'wp-page',
            get_post_meta($created->id, '_elementor_template_type', true),
            'Sanity: a section block starts on the wp-page document.'
        );

        $service->update($created->id, UpdateBlockTemplateDTO::from_array([
            'id' => $created->id, 'type' => 'loop',
        ]));

        self::assertSame(
            'loop',
            get_post_meta($created->id, MetaKeys::TEMPLATE_TYPE, true),
            'The library type itself must change.'
        );
        self::assertSame(
            'elemacy_loop',
            get_post_meta($created->id, '_elementor_template_type', true),
            'The Elementor document type must stay synchronized with the new library type — a Loop Item needs elemacy_loop, not the stale wp-page left over from creation.'
        );
    }

    /* ── Task 5.2: an extension-registered block type needs no edit here ── */

    public function testARegisteredExtensionBlockTypeIsCreatedAndUpdatedWithoutEditingBlockTemplateService(): void
    {
        TypeRegistry::instance()->register(new TypeDefinition(
            'fixture_extension_block',
            'Fixture Extension Block',
            'block',
            'fixture-extension',
            FixtureExtensionBlockDocument::class
        ));

        $service = Container::instance()->make(BlockTemplateService::class);
        $created = $service->create(CreateBlockTemplateDTO::from_array([
            'title' => 'Extension block', 'type' => 'fixture_extension_block', 'status' => 'publish',
        ]));
        $this->own($created->id);

        self::assertSame(
            'elemacy_fixture_extension_block',
            get_post_meta($created->id, '_elementor_template_type', true),
            'create() must resolve a newly registered extension type\'s own document, with no BlockTemplateService change.'
        );

        $service->update($created->id, UpdateBlockTemplateDTO::from_array(['id' => $created->id, 'title' => 'Renamed']));

        self::assertSame(
            'elemacy_fixture_extension_block',
            get_post_meta($created->id, '_elementor_template_type', true),
            'An update that does not touch type must leave the resolved document type exactly as it was.'
        );
    }

    /* ── Task 5.3: DocumentWriter/LibraryRepository shared primitives ── */

    public function testDocumentWriterCreateSyncsLibraryTypeAndElementorDocumentTypeForBlockAndTheme(): void
    {
        $writer = Container::instance()->make(DocumentWriter::class);

        $block_id = $writer->create('block', ['post_title' => 'DW block', 'post_status' => 'publish'], 'loop');
        $this->own($block_id);
        self::assertSame('loop', get_post_meta($block_id, MetaKeys::TEMPLATE_TYPE, true));
        self::assertSame('elemacy_loop', get_post_meta($block_id, '_elementor_template_type', true));

        $theme_id = $writer->create('theme', ['post_title' => 'DW theme', 'post_status' => 'publish'], 'single');
        $this->own($theme_id);
        self::assertSame('single', get_post_meta($theme_id, MetaKeys::TEMPLATE_TYPE, true));
        self::assertSame('elemacy_single', get_post_meta($theme_id, '_elementor_template_type', true));
    }

    public function testDocumentWriterCreateHonorsAnExplicitDocumentTypeOverrideForPopups(): void
    {
        $writer = Container::instance()->make(DocumentWriter::class);

        $topbar_id = $writer->create('popup', ['post_title' => 'DW topbar', 'post_status' => 'draft'], 'topbar', 'elemacy_popup');
        $this->own($topbar_id);

        self::assertSame('topbar', get_post_meta($topbar_id, MetaKeys::TEMPLATE_TYPE, true));
        self::assertSame(
            'elemacy_popup',
            get_post_meta($topbar_id, '_elementor_template_type', true),
            'Every popup type shares one Elementor document regardless of its own registered document_class (none is registered for popups) — the explicit override must be honored, not the registry-driven per-type fallback.'
        );
    }

    public function testDocumentWriterUpdateActuallyFixesTheBlockDocumentTypeDesyncBug(): void
    {
        $writer = Container::instance()->make(DocumentWriter::class);
        $id = $writer->create('block', ['post_title' => 'DW desync fix', 'post_status' => 'publish'], 'section');
        $this->own($id);
        self::assertSame('wp-page', get_post_meta($id, '_elementor_template_type', true));

        $writer->update($id, 'block', [], 'loop');

        self::assertSame('loop', get_post_meta($id, MetaKeys::TEMPLATE_TYPE, true));
        self::assertSame(
            'elemacy_loop',
            get_post_meta($id, '_elementor_template_type', true),
            'This is task 5.1\'s reproduced bug, fixed through the new shared primitive: changing type through DocumentWriter must keep both metadata values synchronized.'
        );
    }

    public function testDocumentWriterUpdateRejectsAWrongGroupIdWithoutMutation(): void
    {
        $writer = Container::instance()->make(DocumentWriter::class);
        $block_id = $writer->create('block', ['post_title' => 'DW wrong group', 'post_status' => 'publish'], 'section');
        $this->own($block_id);

        $caught = null;

        try {
            $writer->update($block_id, 'popup', ['post_title' => 'Hijacked']);
        } catch (NotFoundException $error) {
            $caught = $error;
        }

        self::assertNotNull($caught, 'A popup-group update targeting a block id must be rejected.');
        self::assertSame('DW wrong group', get_post($block_id)->post_title);
    }

    public function testDocumentWriterRejectsAnInvalidTypeForTheGroupWithTheSameErrorEnvelopeAsExistingServices(): void
    {
        $writer = Container::instance()->make(DocumentWriter::class);
        $caught = null;

        try {
            $writer->create('block', ['post_title' => 'Invalid type'], 'not-a-real-block-type');
        } catch (ValidationException $error) {
            $caught = $error;
        }

        self::assertNotNull($caught, 'An unregistered type for the group must be rejected.');
        self::assertSame(['type' => ['Invalid template type.']], $caught->get_errors(), 'Same error shape BlockTemplateService/TemplateService already throw for this case.');
    }

    public function testLibraryRepositorySetMetaToAnAlreadyCurrentValueIsNotMisclassifiedAsAFailedWrite(): void
    {
        $repository = new LibraryRepository();
        $host = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Repeat meta host'], true);
        self::assertIsInt($host);
        $this->own($host);

        // update_post_meta() returns false the second time because the value
        // already matches — that is not a write failure, and set_meta() must
        // not surface it as one (it has no return value at all to check).
        $repository->set_meta($host, '_elemacy_fixture_repeat', 'same-value');
        $repository->set_meta($host, '_elemacy_fixture_repeat', 'same-value');

        self::assertSame('same-value', get_post_meta($host, '_elemacy_fixture_repeat', true));

        // The same, through a full update() call: changing type to the value
        // it already has must not throw despite update_post_meta() reporting
        // no rows changed for TEMPLATE_TYPE.
        $writer = Container::instance()->make(DocumentWriter::class);
        $id = $writer->create('block', ['post_title' => 'Repeat type update', 'post_status' => 'publish'], 'section');
        $this->own($id);

        $caught = null;

        try {
            $writer->update($id, 'block', [], 'section');
        } catch (\Throwable $error) {
            $caught = $error;
        }

        self::assertNull($caught, 'Updating a type to the value it already has must not throw.');
        self::assertSame('section', get_post_meta($id, MetaKeys::TEMPLATE_TYPE, true));
    }

    /* ── Listing must not disturb the request's global post ─────────── */

    public function testListingTemplatesNeverLeavesAGlobalPostBehind(): void
    {
        $block = Container::instance()->make(BlockTemplateService::class);
        $popups = Container::instance()->make(PopupService::class);
        $templates = Container::instance()->make(TemplateService::class);

        $this->own($block->create(CreateBlockTemplateDTO::from_array(['title' => 'Listing loop A', 'type' => 'loop', 'status' => 'publish']))->id);
        $this->own($block->create(CreateBlockTemplateDTO::from_array(['title' => 'Listing loop B', 'type' => 'loop', 'status' => 'publish']))->id);
        $this->own($popups->create(CreatePopupDTO::from_array(['title' => 'Listing popup', 'type' => 'popup', 'status' => 'publish']))->id);
        $this->own($templates->create(CreateTemplateDTO::from_array(['title' => 'Listing template', 'type' => 'single', 'status' => 'publish']))->id);

        // An Elementor editor render_widget request: the main query is a preview
        // query with no current post. wp_reset_postdata() only restores the main
        // query's post, so a listing that used the_post() would leave the last
        // template it listed as the global post, and a Loop widget rendering its
        // item template would then hit Elementor's "Template ID cannot be the same
        // as the currently edited template".
        $saved = [$GLOBALS['wp_query'] ?? null, $GLOBALS['wp_the_query'] ?? null, $GLOBALS['post'] ?? null];
        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new \WP_Query();
        unset($GLOBALS['post']);

        try {
            $listings = [
                'block templates' => static function () use ($block) {
                    return $block->get_all(BlockTemplateListFilterDTO::from_array([]));
                },
                'block templates (unbounded)' => static function () use ($block) {
                    return $block->get_all_unbounded(BlockTemplateListFilterDTO::from_array([]));
                },
                'popups' => static function () use ($popups) {
                    return $popups->get_all(PopupListFilterDTO::from_array([]));
                },
                'theme templates' => static function () use ($templates) {
                    return $templates->get_all(TemplateListFilterDTO::from_array([]));
                },
            ];

            foreach ($listings as $what => $list) {
                $result = $list();
                $items = is_array($result) ? $result : $result->items;

                self::assertNotEmpty($items, "Sanity: the $what listing must return the items created above.");
                self::assertFalse(get_the_ID(), "Listing $what must not leave a global post behind.");
            }
        } finally {
            [$GLOBALS['wp_query'], $GLOBALS['wp_the_query']] = [$saved[0], $saved[1]];
            if (null !== $saved[2]) {
                $GLOBALS['post'] = $saved[2];
            } else {
                unset($GLOBALS['post']);
            }
        }
    }

    /* ── Wrong-group writes: must already fail without mutation ─────── */

    public function testPopupServiceRejectsAWriteTargetingABlockTemplatesId(): void
    {
        $block = Container::instance()->make(BlockTemplateService::class);
        $created = $block->create(CreateBlockTemplateDTO::from_array([
            'title' => 'Not a popup', 'type' => 'section', 'status' => 'publish',
        ]));
        $this->own($created->id);

        $popups = Container::instance()->make(PopupService::class);
        $caught = null;

        try {
            $popups->update($created->id, UpdatePopupDTO::from_array(['id' => $created->id, 'title' => 'Hijacked']));
        } catch (NotFoundException $error) {
            $caught = $error;
        }

        self::assertNotNull($caught, 'A popup write targeting a block template id must be rejected.');
        self::assertSame('Not a popup', get_post($created->id)->post_title, 'The block template must be left untouched by the failed cross-group write.');
    }

    public function testTemplateServiceRejectsAWriteTargetingAPopupsId(): void
    {
        $popups = Container::instance()->make(PopupService::class);
        $created = $popups->create(CreatePopupDTO::from_array([
            'title' => 'Not a template', 'type' => 'popup', 'status' => 'draft',
        ]));
        $this->own($created->id);

        $templates = Container::instance()->make(TemplateService::class);
        $caught = null;

        try {
            $templates->update($created->id, UpdateTemplateDTO::from_array(['id' => $created->id, 'title' => 'Hijacked']));
        } catch (NotFoundException $error) {
            $caught = $error;
        }

        self::assertNotNull($caught, 'A theme-template write targeting a popup id must be rejected.');
        self::assertSame('Not a template', get_post($created->id)->post_title, 'The popup must be left untouched by the failed cross-group write.');
    }

    /* ── Supported condition/rule/trigger DTO collections ────────────── */

    public function testConditionRepositorySaveAcceptsSupportedConditionRuleDtoObjectsNotJustArrays(): void
    {
        $host = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Condition DTO host'], true);
        self::assertIsInt($host);
        $this->own($host);

        $rule = new ConditionRuleDTO();
        $rule->id = 'c1';
        $rule->type = 'general/entire_site';
        $rule->operator = 'include';
        $rule->value = '';

        $repository = new ConditionRepository();
        $repository->save($host, [$rule]);

        $saved = $repository->get($host);
        self::assertCount(1, $saved, 'A supported ConditionRuleDTO object input must persist as one condition, not an empty rule set.');
        self::assertSame('general/entire_site', $saved[0]->type);
    }

    public function testConditionRepositorySaveRejectsMalformedInputWithoutOverwritingStoredTargeting(): void
    {
        $host = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Malformed condition host'], true);
        self::assertIsInt($host);
        $this->own($host);

        $repository = new ConditionRepository();
        $repository->save($host, [
            ['id' => 'c1', 'type' => 'general/entire_site', 'operator' => 'include', 'value' => ''],
        ]);
        $before = $repository->get($host);

        $caught = null;

        try {
            // A bare string is neither a ConditionRuleDTO nor an array — it
            // must be rejected outright, not silently substituted with a
            // blank rule (the pre-existing behavior this task fixes).
            $repository->save($host, ['not-a-rule-at-all']);
        } catch (ValidationException $error) {
            $caught = $error;
        }

        self::assertNotNull($caught, 'Malformed input must be rejected, not silently converted to a blank rule.');
        self::assertSame(['conditions' => ['One or more conditions has an unsupported shape.']], $caught->get_errors());

        $after = $repository->get($host);
        self::assertCount(1, $after, 'The previously stored condition must survive a rejected write untouched.');
        self::assertSame($before[0]->type, $after[0]->type);
    }

    public function testPopupServiceSaveAcceptsSupportedTriggerDtoObjectsNotJustArrays(): void
    {
        $popups = Container::instance()->make(PopupService::class);
        $created = $popups->create(CreatePopupDTO::from_array([
            'title' => 'DTO trigger popup', 'type' => 'popup', 'status' => 'draft',
        ]));
        $this->own($created->id);

        $trigger = new TriggerDTO();
        $trigger->id = 't1';
        $trigger->type = 'page_load';
        $trigger->params = ['delay' => 3];

        $popups->update($created->id, UpdatePopupDTO::from_array(['id' => $created->id, 'triggers' => [$trigger]]));

        $after = $popups->get($created->id);
        self::assertCount(1, $after->triggers, 'A supported TriggerDTO object input must persist as one trigger, not an empty list.');
        self::assertSame('page_load', $after->triggers[0]->type);
        self::assertSame(['delay' => 3], $after->triggers[0]->params);
    }

    /* ── Duplication must not copy generated/runtime metadata ────────── */

    public function testDuplicatingABlockTemplateDoesNotCopyElementorsGeneratedCssCache(): void
    {
        $service = Container::instance()->make(BlockTemplateService::class);
        $created = $service->create(CreateBlockTemplateDTO::from_array([
            'title' => 'Has generated CSS', 'type' => 'section', 'status' => 'publish',
        ]));
        $this->own($created->id);

        // Simulates Elementor's own generated-CSS cache without needing a real
        // render pass to produce it.
        update_post_meta($created->id, '_elementor_css', ['time' => time(), 'fonts' => [], 'status' => 'inline']);

        $duplicate = $service->duplicate($created->id);
        $this->own($duplicate->id);

        self::assertSame(
            '',
            get_post_meta($duplicate->id, '_elementor_css', true),
            'The duplicate must not inherit the source\'s generated CSS cache — Elementor regenerates its own for a new document.'
        );
    }

    public function testDuplicatingAPopupDoesNotCopyAnExtensionDeclaredAnalyticsCounter(): void
    {
        // A fixture contribution to the *existing* POPUP_DUPLICATE_EXCLUDED_META_FILTER
        // — exactly how elemacy-pro's own AnalyticsService declares its two real
        // counters — so this Free test does not depend on whether elemacy-pro
        // is installed in this environment.
        add_filter(Hooks::POPUP_DUPLICATE_EXCLUDED_META_FILTER, static function (array $keys): array {
            $keys[] = '_elemacy_fixture_popup_impressions';

            return $keys;
        });

        $popups = Container::instance()->make(PopupService::class);
        $created = $popups->create(CreatePopupDTO::from_array([
            'title' => 'Has analytics', 'type' => 'popup', 'status' => 'draft',
        ]));
        $this->own($created->id);

        update_post_meta($created->id, '_elemacy_fixture_popup_impressions', 4200);

        $duplicate = $popups->duplicate($created->id);
        $this->own($duplicate->id);

        self::assertSame(
            '',
            get_post_meta($duplicate->id, '_elemacy_fixture_popup_impressions', true),
            'The duplicate must not inherit a meta key an extension declared excluded through the existing popup duplicate-exclusion filter.'
        );
    }

    /* ── Duplication must preserve multiplicity and byte-equivalent JSON ── */

    public function testDuplicatingATemplatePreservesMultiValuedMetadataMultiplicity(): void
    {
        $service = Container::instance()->make(BlockTemplateService::class);
        $created = $service->create(CreateBlockTemplateDTO::from_array([
            'title' => 'Multi meta source', 'type' => 'section', 'status' => 'publish',
        ]));
        $this->own($created->id);

        add_post_meta($created->id, '_elemacy_fixture_multi', 'first', false);
        add_post_meta($created->id, '_elemacy_fixture_multi', 'second', false);
        add_post_meta($created->id, '_elemacy_fixture_multi', 'third', false);

        $duplicate = $service->duplicate($created->id);
        $this->own($duplicate->id);

        self::assertSame(
            ['first', 'second', 'third'],
            get_post_meta($duplicate->id, '_elemacy_fixture_multi'),
            'All three values stored under the same meta key must survive duplication with their multiplicity intact, not collapse to only the last one.'
        );
    }

    public function testDuplicatingATemplatePreservesElementorDataWithBackslashesQuotesAndUnicodeByteEquivalently(): void
    {
        $service = Container::instance()->make(BlockTemplateService::class);
        $created = $service->create(CreateBlockTemplateDTO::from_array([
            'title' => 'Nasty content', 'type' => 'section', 'status' => 'publish',
        ]));
        $this->own($created->id);

        $elements = [[
            'id' => 'eac1', 'elType' => 'widget', 'widgetType' => 'heading',
            'settings' => ['title' => 'Quote " backslash \\ and emoji 😀 and é'],
            'elements' => [],
        ]];
        $json = wp_json_encode($elements);

        // Stored exactly as Elementor's own Document::save() stores it —
        // wp_slash() first specifically to counteract update_post_meta()'s
        // own wp_unslash() of its $meta_value argument (see core/base/
        // document.php's own comment on the same line for the same reason).
        update_post_meta($created->id, '_elementor_data', wp_slash($json));

        self::assertSame(
            $json,
            get_post_meta($created->id, '_elementor_data', true),
            'Sanity: a single read must already return the exact original JSON.'
        );

        $duplicate = $service->duplicate($created->id);
        $this->own($duplicate->id);

        self::assertSame(
            $json,
            get_post_meta($duplicate->id, '_elementor_data', true),
            'Duplication must preserve _elementor_data byte-equivalently, including its own JSON escaping — a naive copy that does not re-slash the value before update_post_meta() would have it silently unslashed away.'
        );
    }

    /* ── Task 5.5: DocumentDuplicator itself, exercised directly ────── */

    public function testDocumentDuplicatorExcludesItsOwnBaseGeneratedElementorCaches(): void
    {
        $writer = Container::instance()->make(DocumentWriter::class);
        $id = $writer->create('block', ['post_title' => 'DD generated caches', 'post_status' => 'publish'], 'section');
        $this->own($id);

        update_post_meta($id, '_elementor_css', ['time' => time(), 'status' => 'inline']);
        update_post_meta($id, '_elementor_page_assets', ['styles' => ['x'], 'scripts' => ['y']]);
        update_post_meta($id, '_elementor_element_cache', ['a1' => '<div>cached</div>']);

        $duplicator = Container::instance()->make(DocumentDuplicator::class);
        $duplicate_id = $duplicator->duplicate($id, 'block');
        $this->own($duplicate_id);

        self::assertSame('', get_post_meta($duplicate_id, '_elementor_css', true));
        self::assertSame('', get_post_meta($duplicate_id, '_elementor_page_assets', true));
        self::assertSame('', get_post_meta($duplicate_id, '_elementor_element_cache', true));
    }

    public function testDocumentDuplicatorHonorsExtraExcludedMetaAndTheSharedFilter(): void
    {
        add_filter(Hooks::LIBRARY_DUPLICATE_EXCLUDED_META_FILTER, static function (array $keys, string $group): array {
            if ($group === 'block') {
                $keys[] = '_elemacy_fixture_shared_filter_excluded';
            }

            return $keys;
        }, 10, 2);

        $writer = Container::instance()->make(DocumentWriter::class);
        $id = $writer->create('block', ['post_title' => 'DD extra exclusions', 'post_status' => 'publish'], 'section');
        $this->own($id);

        update_post_meta($id, '_elemacy_fixture_caller_excluded', 'x');
        update_post_meta($id, '_elemacy_fixture_shared_filter_excluded', 'y');
        update_post_meta($id, '_elemacy_fixture_kept', 'z');

        $duplicator = Container::instance()->make(DocumentDuplicator::class);
        $duplicate_id = $duplicator->duplicate($id, 'block', ['_elemacy_fixture_caller_excluded']);
        $this->own($duplicate_id);

        self::assertSame('', get_post_meta($duplicate_id, '_elemacy_fixture_caller_excluded', true), 'A caller-supplied exclusion must be honored.');
        self::assertSame('', get_post_meta($duplicate_id, '_elemacy_fixture_shared_filter_excluded', true), 'The shared LIBRARY_DUPLICATE_EXCLUDED_META_FILTER contribution must be honored.');
        self::assertSame('z', get_post_meta($duplicate_id, '_elemacy_fixture_kept', true), 'An ordinary authoring meta key must still be copied.');
    }

    public function testDocumentDuplicatorPreservesMultiValuedMetadataMultiplicity(): void
    {
        $writer = Container::instance()->make(DocumentWriter::class);
        $id = $writer->create('block', ['post_title' => 'DD multi meta', 'post_status' => 'publish'], 'section');
        $this->own($id);

        add_post_meta($id, '_elemacy_fixture_multi', 'first', false);
        add_post_meta($id, '_elemacy_fixture_multi', 'second', false);
        add_post_meta($id, '_elemacy_fixture_multi', 'third', false);

        $duplicator = Container::instance()->make(DocumentDuplicator::class);
        $duplicate_id = $duplicator->duplicate($id, 'block');
        $this->own($duplicate_id);

        self::assertSame(['first', 'second', 'third'], get_post_meta($duplicate_id, '_elemacy_fixture_multi'));
    }

    public function testDocumentDuplicatorPreservesElementorDataByteEquivalentlyIncludingSlashing(): void
    {
        $writer = Container::instance()->make(DocumentWriter::class);
        $id = $writer->create('block', ['post_title' => 'DD nasty content', 'post_status' => 'publish'], 'section');
        $this->own($id);

        $elements = [[
            'id' => 'eac1', 'elType' => 'widget', 'widgetType' => 'heading',
            'settings' => ['title' => 'Quote " backslash \\ and emoji 😀 and é'],
            'elements' => [],
        ]];
        $json = wp_json_encode($elements);
        update_post_meta($id, '_elementor_data', wp_slash($json));

        $duplicator = Container::instance()->make(DocumentDuplicator::class);
        $duplicate_id = $duplicator->duplicate($id, 'block');
        $this->own($duplicate_id);

        self::assertSame($json, get_post_meta($duplicate_id, '_elementor_data', true));
    }

    public function testDocumentDuplicatorRejectsAWrongGroupIdWithoutCreatingAnything(): void
    {
        $writer = Container::instance()->make(DocumentWriter::class);
        $id = $writer->create('block', ['post_title' => 'DD wrong group', 'post_status' => 'publish'], 'section');
        $this->own($id);

        $duplicator = Container::instance()->make(DocumentDuplicator::class);
        $caught = null;

        try {
            $duplicator->duplicate($id, 'popup');
        } catch (NotFoundException $error) {
            $caught = $error;
        }

        self::assertNotNull($caught, 'A popup-group duplicate targeting a block id must be rejected.');
    }

    /* ── Task 5.6: one completed-write notification, metadata authoritative ── */

    public function testPopupCreateFiresExactlyOneLibraryChangedNotificationAfterAllMetadataIsAuthoritative(): void
    {
        $seen = [];
        add_action(Hooks::LIBRARY_CHANGED_ACTION, static function (LibraryChanged $event) use (&$seen) {
            $seen[] = $event;
        });

        $popups = Container::instance()->make(PopupService::class);
        $created = $popups->create(CreatePopupDTO::from_array([
            'title' => 'Notify create', 'type' => 'popup', 'status' => 'draft',
            'triggers' => [['id' => 't1', 'type' => 'page_load', 'params' => []]],
        ]));
        $this->own($created->id);

        self::assertCount(1, $seen, 'Exactly one notification must fire for a single create() call.');
        self::assertSame($created->id, $seen[0]->id);
        self::assertSame('popup', $seen[0]->group);
        self::assertSame('popup', $seen[0]->type);
        self::assertSame(LibraryChanged::CREATED, $seen[0]->action);

        // Authoritative metadata (including the popup-specific trigger, saved
        // after the shared write boundary's own work) must already be
        // persisted by the time the notification fires — a listener reading
        // it back right away must see the final state, not a partial one.
        self::assertSame(
            'elemacy_popup',
            get_post_meta($created->id, '_elementor_template_type', true),
            'The Elementor document type must already be set when the notification fires.'
        );
        $fresh = $popups->get($created->id);
        self::assertCount(1, $fresh->triggers, 'The popup-specific trigger must already be persisted when the notification fires.');
    }

    public function testBlockTemplateUpdateAndDuplicateEachFireExactlyOneLibraryChangedNotification(): void
    {
        $seen = [];
        add_action(Hooks::LIBRARY_CHANGED_ACTION, static function (LibraryChanged $event) use (&$seen) {
            $seen[] = $event;
        });

        $service = Container::instance()->make(BlockTemplateService::class);
        $created = $service->create(CreateBlockTemplateDTO::from_array([
            'title' => 'Notify update/duplicate', 'type' => 'section', 'status' => 'publish',
        ]));
        $this->own($created->id);
        self::assertCount(1, $seen, 'create() itself must fire exactly one notification.');

        $seen = [];
        $service->update($created->id, UpdateBlockTemplateDTO::from_array(['id' => $created->id, 'type' => 'loop']));
        self::assertCount(1, $seen, 'update() must fire exactly one notification.');
        self::assertSame(LibraryChanged::UPDATED, $seen[0]->action);
        self::assertSame('loop', $seen[0]->type, 'The notification must carry the type as it is after the update, not before.');

        $seen = [];
        $duplicate = $service->duplicate($created->id);
        $this->own($duplicate->id);
        self::assertCount(1, $seen, 'duplicate() must fire exactly one notification.');
        self::assertSame($duplicate->id, $seen[0]->id);
        self::assertSame(LibraryChanged::DUPLICATED, $seen[0]->action);

        $seen = [];
        $service->delete($duplicate->id);
        $this->posts = array_values(array_diff($this->posts, [$duplicate->id]));
        self::assertCount(1, $seen, 'delete() must fire exactly one notification.');
        self::assertSame(LibraryChanged::DELETED, $seen[0]->action);
    }

    /* ── Task 5.7: injected failures compensate; no orphan left behind ── */

    public function testDocumentWriterCreateCompensatesByDeletingThePartiallyAssembledItemWhenARequiredWriteFails(): void
    {
        $failing = new FailingLibraryRepository(0); // The very first set_meta() call fails.
        $writer = new DocumentWriter($failing, TypeRegistry::instance());

        $caught = null;

        try {
            $writer->create('block', ['post_title' => 'Should not survive', 'post_status' => 'publish'], 'section');
        } catch (\RuntimeException $error) {
            $caught = $error;
        }

        self::assertNotNull($caught, 'The injected failure must propagate to the caller — the operation reports failure.');
        self::assertSame('Injected required-write failure.', $caught->getMessage());
        self::assertCount(1, $failing->deleted, 'Compensating cleanup must delete exactly the one partially assembled post.');
        self::assertNull(get_post($failing->deleted[0]), 'No partially assembled item may remain visible after the failure.');
    }

    public function testDocumentDuplicatorCompensatesByDeletingTheDraftCopyWhenARequiredWriteFails(): void
    {
        // The source is created with an ordinary, non-failing repository —
        // only the duplicate() call under test below uses the failing one,
        // so its call counter starts fresh at zero for that call alone.
        $source_id = (Container::instance()->make(DocumentWriter::class))->create('block', ['post_title' => 'DD failure source', 'post_status' => 'publish'], 'section');
        $this->own($source_id);

        // copy_meta() itself writes via add_post_meta() directly, not
        // through the repository, so the only injectable point here is the
        // type re-sync's own set_meta() calls — failing on the first proves
        // the same compensating-cleanup path DocumentWriter uses.
        $failing = new FailingLibraryRepository(0);
        $duplicator = new DocumentDuplicator($failing, new DocumentWriter($failing, TypeRegistry::instance()));

        $caught = null;

        try {
            $duplicator->duplicate($source_id, 'block');
        } catch (\RuntimeException $error) {
            $caught = $error;
        }

        self::assertNotNull($caught, 'The injected failure must propagate to the caller — the operation reports failure.');
        self::assertNotEmpty($failing->deleted, 'Compensating cleanup must run.');
        $draft_id = end($failing->deleted);
        self::assertNull(get_post($draft_id), 'No partially assembled draft copy may remain visible after the failure.');
        self::assertNotSame($source_id, $draft_id, 'Only the draft copy is removed — never the source.');
    }

    public function testDocumentDuplicatorCreatesTheCopyAsADraftUntilAllRequiredWritesComplete(): void
    {
        // A publish-status source, duplicated successfully (no injected
        // failure): the copy must still start as a draft — design.md D6's
        // "create copies as drafts until all required writes complete" — not
        // inherit the source's own publish status.
        $writer = Container::instance()->make(DocumentWriter::class);
        $source_id = $writer->create('block', ['post_title' => 'Draft-first source', 'post_status' => 'publish'], 'section');
        $this->own($source_id);

        $duplicator = Container::instance()->make(DocumentDuplicator::class);
        $duplicate_id = $duplicator->duplicate($source_id, 'block');
        $this->own($duplicate_id);

        self::assertSame('draft', get_post($duplicate_id)->post_status);
    }

    /* ── Task 5.7: full real-database CRUD/duplicate for every builder ── */

    public function testFullCrudAndDuplicateCycleForTheThemeBuilder(): void
    {
        $service = Container::instance()->make(TemplateService::class);

        $created = $service->create(CreateTemplateDTO::from_array(['title' => 'Theme CRUD', 'type' => 'single', 'status' => 'publish']));
        $this->own($created->id);
        self::assertSame('single', get_post_meta($created->id, MetaKeys::TEMPLATE_TYPE, true));
        self::assertSame('elemacy_single', get_post_meta($created->id, '_elementor_template_type', true));

        self::assertNotNull($service->get($created->id), 'A freshly created theme template must be readable back.');

        $updated = $service->update($created->id, UpdateTemplateDTO::from_array(['id' => $created->id, 'title' => 'Theme CRUD renamed', 'type' => 'archive']));
        self::assertSame('Theme CRUD renamed', $updated->title);
        self::assertSame('archive', get_post_meta($created->id, MetaKeys::TEMPLATE_TYPE, true));
        self::assertSame('elemacy_archive', get_post_meta($created->id, '_elementor_template_type', true), 'Updating a theme template\'s type must keep both metadata values synchronized too.');

        $duplicate = $service->duplicate($created->id);
        $this->own($duplicate->id);
        self::assertSame('archive', get_post_meta($duplicate->id, MetaKeys::TEMPLATE_TYPE, true));
        self::assertSame('draft', get_post($duplicate->id)->post_status);

        self::assertTrue($service->delete($duplicate->id));
        $this->posts = array_values(array_diff($this->posts, [$duplicate->id]));
        self::assertNull($service->get($duplicate->id), 'A deleted theme template must no longer be readable.');
    }

    /**
     * This process's REST server with Elemacy's routes on it. Elementor's
     * internal Data API (Data\V2\Manager::run_server()) replaces the server
     * with one built after remove_all_actions('rest_api_init') whenever it
     * generates CSS that reads Kit globals, as an earlier test in this
     * long-lived process can. A real REST request is its own process and
     * never meets that server, so the routes are registered here as
     * HttpBootstrap does on rest_api_init.
     */
    private function elemacy_rest(): \WP_REST_Server
    {
        $server = rest_get_server();

        if (!isset($server->get_routes()['/elemacy/v1/theme-builder/templates'])) {
            foreach (Route::get_routes() as $route) {
                $route->register();
            }
        }

        return $server;
    }

    private function put_template(int $id, array $body): \WP_REST_Response
    {
        $request = new \WP_REST_Request('PUT', '/elemacy/v1/theme-builder/templates/' . $id);
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode($body));

        return $this->elemacy_rest()->dispatch($request);
    }

    /** @return array<int, array<string, mixed>> Listed templates by id. */
    private function list_templates(): array
    {
        $request = new \WP_REST_Request('GET', '/elemacy/v1/theme-builder/templates');
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_query_params(['per_page' => 100]);
        $response = $this->elemacy_rest()->dispatch($request);
        self::assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));

        $listed = [];
        foreach ((array) ($response->get_data()['data']['results'] ?? []) as $item) {
            if (is_array($item) && isset($item['id'])) {
                $listed[(int) $item['id']] = $item;
            }
        }

        return $listed;
    }

    /** A header created through the admin service with $rules ([type, operator?, value?]), published. */
    private function listed_header(string $title, array $rules): int
    {
        $created = Container::instance()->make(TemplateService::class)->create(CreateTemplateDTO::from_array([
            'title' => $title,
            'type' => 'header',
            'status' => 'publish',
            'conditions' => array_map(static fn(array $rule): array => [
                'id' => 'r-' . wp_generate_uuid4(), 'type' => $rule[0], 'operator' => $rule[1] ?? 'include', 'value' => $rule[2] ?? '',
            ], $rules),
        ]));

        return $this->own($created->id);
    }

    public function testTheTemplateListSaysWhereEachTemplateDisplays(): void
    {
        $older = $this->listed_header('Display older site-wide', [['general/entire_site']]);
        $none = $this->listed_header('Display nowhere', []);
        $suspended = $this->listed_header('Display suspended', [['general/entire_site'], ['fixture/not-registered', 'exclude']]);
        $newer = $this->listed_header('Display newer site-wide', [['general/entire_site']]);
        (new ResolverCache())->invalidate();

        $listed = $this->list_templates();

        self::assertSame('displayed', $listed[$newer]['display']['state']);
        self::assertSame(['Entire Site'], $listed[$newer]['display']['summary']);
        self::assertNull($listed[$newer]['display']['overridden_by']);
        self::assertSame(['id' => $newer, 'title' => 'Display newer site-wide'], $listed[$older]['display']['overridden_by'], 'The older Entire Site header is overridden by the newer one.');
        self::assertSame('not_displayed', $listed[$none]['display']['state']);
        self::assertSame('suspended', $listed[$suspended]['display']['state']);
        self::assertSame(['fixture/not-registered'], $listed[$suspended]['display']['unavailable']);
    }

    public function testListingTemplatesWithTheirDisplayStateCostsTheSameQueriesWhateverTheirNumber(): void
    {
        global $wpdb;

        $count = function () use ($wpdb): int {
            (new ResolverCache())->invalidate();
            $this->list_templates();
            $before = $wpdb->num_queries;
            TemplateResolver::reset();
            $this->list_templates();

            return $wpdb->num_queries - $before;
        };

        for ($i = 0; $i < 3; $i++) {
            $this->listed_header('Display cost ' . $i, [['general/entire_site']]);
        }
        $few = $count();

        for ($i = 3; $i < 9; $i++) {
            $this->listed_header('Display cost ' . $i, [['general/entire_site']]);
        }

        self::assertSame($few, $count(), 'Describing each listed template adds no per-template query.');
    }

    public function testAThemeTemplateCreatedWithoutAStatusIsADraft(): void
    {
        $created = Container::instance()->make(TemplateService::class)->create(CreateTemplateDTO::from_array(['title' => 'Theme default status', 'type' => 'header']));
        $this->own($created->id);

        self::assertSame('draft', get_post_status($created->id), 'A new template is never live until it is published.');
    }

    public function testTheTemplateEndpointsSetOnlyPublishOrDraftAndKeepAStatusTheyWereNotGiven(): void
    {
        $created = Container::instance()->make(TemplateService::class)->create(CreateTemplateDTO::from_array(['title' => 'Theme scheduled', 'type' => 'header', 'status' => 'draft']));
        $this->own($created->id);
        wp_update_post(['ID' => $created->id, 'post_status' => 'future', 'post_date' => gmdate('Y-m-d H:i:s', time() + WEEK_IN_SECONDS), 'edit_date' => true]);
        self::assertSame('future', get_post_status($created->id), 'Sanity: the template is scheduled.');

        // As the admin form sends it: title and conditions, no type and no status.
        $renamed = $this->put_template($created->id, ['id' => $created->id, 'title' => 'Theme scheduled renamed', 'conditions' => []]);
        self::assertSame(200, $renamed->get_status(), 'An update without type or status is accepted: ' . wp_json_encode($renamed->get_data()));
        self::assertSame('Theme scheduled renamed', get_the_title($created->id));
        self::assertSame('future', get_post_status($created->id), 'A status the request did not set is kept.');

        $trashed = $this->put_template($created->id, ['id' => $created->id, 'title' => 'Theme scheduled renamed', 'conditions' => [], 'status' => 'trash']);
        self::assertSame(422, $trashed->get_status(), 'Trash is not a status the template form sets.');
        self::assertSame('future', get_post_status($created->id), 'A rejected update changes nothing.');
    }

    public function testSavingTemplateDetailsKeepsThePageLayoutTheAuthorChose(): void
    {
        $service = Container::instance()->make(TemplateService::class);
        $created = $service->create(CreateTemplateDTO::from_array(['title' => 'Theme layout', 'type' => 'single', 'status' => 'draft']));
        $this->own($created->id);
        self::assertSame('elementor_header_footer', get_post_meta($created->id, '_wp_page_template', true), 'Sanity: a content location starts at its default layout.');

        update_post_meta($created->id, '_wp_page_template', 'elementor_canvas');
        $service->update($created->id, UpdateTemplateDTO::from_array(['id' => $created->id, 'title' => 'Theme layout renamed', 'type' => 'single']));
        self::assertSame('elementor_canvas', get_post_meta($created->id, '_wp_page_template', true), 'Renaming keeps Canvas.');

        update_post_meta($created->id, '_wp_page_template', 'default');
        $service->update($created->id, UpdateTemplateDTO::from_array(['id' => $created->id, 'title' => 'Theme layout renamed', 'type' => 'archive']));
        self::assertSame('default', get_post_meta($created->id, '_wp_page_template', true), 'Single to Archive keeps the chosen layout.');

        $service->update($created->id, UpdateTemplateDTO::from_array(['id' => $created->id, 'title' => 'Theme layout renamed', 'type' => 'header']));
        self::assertSame('elementor_canvas', get_post_meta($created->id, '_wp_page_template', true), 'A content location turned into a header gets the chrome default.');

        $service->update($created->id, UpdateTemplateDTO::from_array(['id' => $created->id, 'title' => 'Theme layout renamed', 'type' => 'single']));
        self::assertSame('elementor_header_footer', get_post_meta($created->id, '_wp_page_template', true), 'A header turned into a content location gets the content default.');
    }

    public function testFullCrudAndDuplicateCycleForPopups(): void
    {
        $service = Container::instance()->make(PopupService::class);

        $created = $service->create(CreatePopupDTO::from_array(['title' => 'Popup CRUD', 'type' => 'popup', 'status' => 'draft']));
        $this->own($created->id);
        self::assertSame('popup', get_post_meta($created->id, MetaKeys::TEMPLATE_TYPE, true));
        self::assertSame('elemacy_popup', get_post_meta($created->id, '_elementor_template_type', true));

        self::assertNotNull($service->get($created->id), 'A freshly created popup must be readable back.');

        $updated = $service->update($created->id, UpdatePopupDTO::from_array(['id' => $created->id, 'title' => 'Popup CRUD renamed', 'type' => 'topbar']));
        self::assertSame('Popup CRUD renamed', $updated->title);
        self::assertSame('topbar', get_post_meta($created->id, MetaKeys::TEMPLATE_TYPE, true));
        self::assertSame('elemacy_popup', get_post_meta($created->id, '_elementor_template_type', true), 'Every popup type keeps the same Elementor document after an update too.');

        $duplicate = $service->duplicate($created->id);
        $this->own($duplicate->id);
        self::assertSame('topbar', get_post_meta($duplicate->id, MetaKeys::TEMPLATE_TYPE, true));
        self::assertSame('draft', get_post($duplicate->id)->post_status);

        self::assertTrue($service->delete($duplicate->id));
        $this->posts = array_values(array_diff($this->posts, [$duplicate->id]));
        self::assertNull($service->get($duplicate->id), 'A deleted popup must no longer be readable.');
    }

    /* ── Task 5.7: the saved document/meta contract is unchanged ────── */

    public function testStoredLibraryTypesStillMapToTheDocumentedElementorDocumentTypes(): void
    {
        // docs/ARCHITECTURE-CONTRACTS.md's "Library types and Elementor
        // document names" table, re-verified after the whole of phase 5:
        // these are the stored values every builder's write path commits to
        // — only how they get resolved/synchronized changed, never what
        // they resolve to.
        self::assertSame('wp-page', (Container::instance()->make(DocumentWriter::class))->document_type('section'));
        self::assertSame('elemacy_loop', (Container::instance()->make(DocumentWriter::class))->document_type('loop'));
        self::assertSame('elemacy_header', (Container::instance()->make(DocumentWriter::class))->document_type('header'));
        self::assertSame('elemacy_footer', (Container::instance()->make(DocumentWriter::class))->document_type('footer'));
        self::assertSame('elemacy_single', (Container::instance()->make(DocumentWriter::class))->document_type('single'));
        self::assertSame('elemacy_archive', (Container::instance()->make(DocumentWriter::class))->document_type('archive'));
        self::assertSame('elemacy_404', (Container::instance()->make(DocumentWriter::class))->document_type('404'));
        self::assertSame('elemacy_search', (Container::instance()->make(DocumentWriter::class))->document_type('search'));
        self::assertSame(PopupService::DOCUMENT_TYPE, 'elemacy_popup');
    }
}
