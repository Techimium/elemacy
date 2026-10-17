<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Conditions\ConditionRepository;
use Elemacy\Modules\Popups\Support\DocumentDisplay;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\TemplateLibrary\ResolverCache;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\Tests\Integration\Fixtures\LibraryDocument;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Task 11.1/11.2/11.3: the candidate index (ResolverCache, persistent) and
 * the per-request evaluation memo built on it (TemplateResolver) are
 * separate layers; a relevant write must refresh both, a site switch must
 * reset both without touching the persisted data of the site that never
 * changed, and unrelated metadata must not force a rebuild.
 */
final class CacheLifecycleTest extends IntegrationTestCase
{
    private array $owned = [];

    protected function tearDown(): void
    {
        try {
            foreach (array_reverse($this->owned) as $id) {
                wp_delete_post($id, true);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function popup(string $title, string $status = 'publish'): int
    {
        $id = $this->documents->create($title, 'popup', $status, LibraryDocument::heading($title));
        $this->owned[] = $id;

        return $id;
    }

    public function testResolveThenEditRefreshesBothThePersistentIndexAndTheSameRequestResult(): void
    {
        $id = $this->popup('Cache lifecycle: resolve-then-edit');

        self::assertContains($id, TemplateResolver::instance()->resolve_group('popup'), 'Must match while published with no conditions.');

        // A direct, non-Elementor-flow status change via the same DocumentWriter
        // path an editor uses: wp_update_post() fires save_post, which must
        // invalidate the persisted index AND the same-request memo so the very
        // next call in this same request reflects it, not just the next request.
        wp_update_post(['ID' => $id, 'post_status' => 'draft']);

        self::assertNotContains($id, TemplateResolver::instance()->resolve_group('popup'), 'A same-request re-resolve must see the draft status, not a stale cached match.');
    }

    public function testDirectMetaWriteBypassingWpUpdatePostStillInvalidates(): void
    {
        $id = $this->popup('Cache lifecycle: direct meta write');

        self::assertContains($id, TemplateResolver::instance()->resolve_group('popup'));

        // A bare update_post_meta() call (an import/migration/direct API write)
        // fires neither save_post nor LibraryChanged — only updated_post_meta.
        update_post_meta($id, ConditionRepository::META_KEY, [
            ['id' => 'r1', 'type' => 'elemacy_fixture/never_matches', 'operator' => 'include', 'value' => ''],
        ]);

        self::assertNotContains(
            $id,
            TemplateResolver::instance()->resolve_group('popup'),
            'A bare postmeta write to the conditions key must still invalidate the candidate index.'
        );
    }

    /** Stores $value as the persisted index and drops every in-request layer, as a fresh request would see it. */
    private function persistIndexAsIfFromAnotherRelease($value): void
    {
        update_option(ResolverCache::OPTION, $value, false);
        ResolverCache::forget_memo();
        TemplateResolver::reset();
    }

    public function testABareIndexWithoutAShapeIsTreatedAsAbsentAndReplaced(): void
    {
        $id = $this->popup('Cache lifecycle: bare legacy index');
        $phantom = PHP_INT_MAX;

        $this->persistIndexAsIfFromAnotherRelease(['popup' => [['id' => $phantom, 'conditions' => []]]]);

        $resolved = TemplateResolver::instance()->resolve_group('popup');
        $stored = get_option(ResolverCache::OPTION);

        self::assertContains($id, $resolved, 'The same request must resolve from a freshly built index.');
        self::assertNotContains($phantom, $resolved, 'A stored value without a shape must never be read as an index.');
        self::assertSame(ResolverCache::SHAPE, $stored['shape'] ?? null, 'The stored value is replaced with the current shape.');
    }

    public function testAnIndexInAnotherShapeIsTreatedAsAbsentAndReplaced(): void
    {
        $id = $this->popup('Cache lifecycle: other-shape index');
        $phantom = PHP_INT_MAX;

        // A newer release's shape on older code (a downgrade), or an older one's after an upgrade.
        $this->persistIndexAsIfFromAnotherRelease([
            'shape' => ResolverCache::SHAPE + 1,
            'index' => ['popup' => [['id' => $phantom, 'conditions' => []]]],
        ]);

        $resolved = TemplateResolver::instance()->resolve_group('popup');
        $stored = get_option(ResolverCache::OPTION);

        self::assertContains($id, $resolved, 'The same request must resolve from a freshly built index.');
        self::assertNotContains($phantom, $resolved, 'An index in another shape must never be interpreted.');
        self::assertSame(ResolverCache::SHAPE, $stored['shape'] ?? null, 'The stored value is replaced with the current shape.');
    }

    public function testAnIndexInTheCurrentShapeIsUsedAsStored(): void
    {
        $listed = $this->popup('Cache lifecycle: current-shape listed');
        $unlisted = $this->popup('Cache lifecycle: current-shape unlisted');
        $envelope = [
            'shape' => ResolverCache::SHAPE,
            'index' => ['popup' => [['id' => $listed, 'conditions' => []]]],
        ];

        $this->persistIndexAsIfFromAnotherRelease($envelope);

        $resolved = TemplateResolver::instance()->resolve_group('popup');

        self::assertContains($listed, $resolved, 'A current-shape index is read as the candidate source.');
        self::assertNotContains($unlisted, $resolved, 'Nothing is rebuilt while the stored index has the current shape.');
        self::assertSame($envelope, get_option(ResolverCache::OPTION), 'A current-shape index is left exactly as stored.');
    }

    public function testUnrelatedMetadataDoesNotForceARebuild(): void
    {
        $id = $this->popup('Cache lifecycle: unrelated metadata');
        TemplateResolver::instance()->resolve_group('popup'); // warm both caches.

        $option_before = get_option(ResolverCache::OPTION);

        // _elementor_data changes on every content edit; it must not be treated
        // as a candidate-index-relevant field.
        update_post_meta($id, '_elementor_data', wp_json_encode(LibraryDocument::heading('changed')));
        update_post_meta($id, 'elemacy_fixture_unrelated_meta', 'anything');

        self::assertSame($option_before, get_option(ResolverCache::OPTION), 'Unrelated metadata must not delete/rebuild the persisted candidate index.');
    }

    public function testStatusAndDeleteChangesInvalidate(): void
    {
        $id = $this->popup('Cache lifecycle: status and delete', 'draft');
        self::assertNotContains($id, TemplateResolver::instance()->resolve_group('popup'), 'A draft popup must not match.');

        wp_publish_post($id);
        self::assertContains($id, TemplateResolver::instance()->resolve_group('popup'), 'Publishing must invalidate the stale draft-era index.');

        wp_delete_post($id, true);
        $this->owned = array_values(array_diff($this->owned, [$id]));
        self::assertNotContains($id, TemplateResolver::instance()->resolve_group('popup'), 'Deletion must invalidate the index so the removed id never reappears.');
    }

    public function testEqualDatesResolveDeterministicallyByHighestId(): void
    {
        // A shared past date, not future: WordPress unconditionally downgrades
        // an otherwise-published post to 'future' status whenever post_date is
        // ahead of now, regardless of an explicitly passed post_status.
        $shared_date = gmdate('Y-m-d H:i:s', strtotime('-1 hour'));

        $lower_id = $this->popup('Cache lifecycle: equal-date A');
        wp_update_post(['ID' => $lower_id, 'post_date' => $shared_date, 'post_date_gmt' => $shared_date, 'post_status' => 'publish']);

        $higher_id = $this->popup('Cache lifecycle: equal-date B');
        wp_update_post(['ID' => $higher_id, 'post_date' => $shared_date, 'post_date_gmt' => $shared_date, 'post_status' => 'publish']);

        self::assertGreaterThan($lower_id, $higher_id, 'Sanity: the second-created fixture must have the higher id.');

        // Compares the two fixtures' *relative* order within the real,
        // possibly-shared-environment index rather than asserting resolve()'s
        // global winner (which unrelated pre-existing candidates could also
        // legitimately claim) — isolates exactly what task 11.2 changed: the
        // ID DESC tiebreaker for entries sharing one date.
        $ids_in_order = array_column((new ResolverCache())->get_index()['popup'] ?? [], 'id');
        $lower_position = array_search($lower_id, $ids_in_order, true);
        $higher_position = array_search($higher_id, $ids_in_order, true);

        self::assertNotFalse($lower_position, 'Sanity: the lower-id fixture must appear in the popup candidate index.');
        self::assertNotFalse($higher_position, 'Sanity: the higher-id fixture must appear in the popup candidate index.');
        self::assertLessThan(
            $lower_position,
            $higher_position,
            'Two same-date candidates must order deterministically by id descending, not depend on incidental SQL tie-order.'
        );
    }

    public function testSwitchBlogResetsTheInMemoryMemoWithoutTouchingThePersistedIndex(): void
    {
        $id = $this->popup('Cache lifecycle: switch_blog reset');
        TemplateResolver::instance()->resolve('popup'); // warm TemplateResolver's static memo (resolve(), not resolve_group(), populates it).
        PopupManagerAccessor::warm_matched();
        DocumentDisplay::for_popup($id); // warm DocumentDisplay's static memo.

        $resolver_reflection = new \ReflectionProperty(TemplateResolver::class, 'resolved');
        $resolver_reflection->setAccessible(true);
        self::assertNotEmpty($resolver_reflection->getValue(), 'Sanity: TemplateResolver memo must be warm before the simulated switch.');

        $display_reflection = new \ReflectionProperty(DocumentDisplay::class, 'cache');
        $display_reflection->setAccessible(true);
        self::assertArrayHasKey($id, $display_reflection->getValue(), 'Sanity: DocumentDisplay memo must be warm before the simulated switch.');

        $popup_manager = \Elemacy\Modules\Popups\Services\PopupManager::instance();
        $matched_reflection = new \ReflectionProperty($popup_manager, 'matched');
        $matched_reflection->setAccessible(true);
        self::assertNotNull($matched_reflection->getValue($popup_manager), 'Sanity: PopupManager matched-list memo must be warm before the simulated switch.');

        $option_before = get_option(ResolverCache::OPTION);

        // Simulates the site-identity change switch_to_blog()/restore_current_blog()
        // signal without needing a real second site's tables — this tests this
        // plugin's own listener wiring, not WordPress's own multisite switching.
        do_action('switch_blog', 999999, get_current_blog_id());

        self::assertSame([], $resolver_reflection->getValue(), 'TemplateResolver must reset its evaluation memo on switch_blog.');
        self::assertSame([], $display_reflection->getValue(), 'DocumentDisplay must reset its memo on switch_blog.');
        self::assertNull($matched_reflection->getValue($popup_manager), 'PopupManager must reset its matched-list memo on switch_blog.');
        self::assertSame(
            $option_before,
            get_option(ResolverCache::OPTION),
            'switch_blog must reset only the in-memory memo, never delete/rebuild the persisted index of a site that did not change.'
        );

        do_action('switch_blog', get_current_blog_id(), 999999);
    }

    /**
     * Task 11.4: a rebuild started under an older generation (e.g. delayed
     * behind a slow query while a second write landed and started its own,
     * faster rebuild) must not clobber the newer rebuild's already-persisted
     * result with stale data.
     */
    public function testADelayedOlderGenerationRebuildCannotOverwriteANewerOne(): void
    {
        $id = $this->popup('Cache lifecycle: generation guard');
        TemplateResolver::instance()->resolve_group('popup'); // warm + persist the index.

        $cache = new ResolverCache();
        $rebuild = new \ReflectionMethod(ResolverCache::class, 'rebuild_if_still_current');
        $rebuild->setAccessible(true);

        $stale_generation = (int) get_option(ResolverCache::GENERATION_OPTION, 0);

        // A second, "faster" write: bumps the generation and completes its
        // own rebuild immediately (simulating it finishing before the
        // delayed one below gets a chance to).
        wp_delete_post($id, true);
        $this->owned = array_values(array_diff($this->owned, [$id]));
        $current_generation = (int) get_option(ResolverCache::GENERATION_OPTION, 0);
        self::assertGreaterThan($stale_generation, $current_generation, 'Sanity: deleting the fixture must bump the generation.');
        $rebuild->invoke($cache, $current_generation);
        $authoritative_index = get_option(ResolverCache::OPTION);

        // The delayed rebuild from the *original* (now stale) generation
        // arrives last and must be a no-op.
        $rebuild->invoke($cache, $stale_generation);

        self::assertSame(
            $authoritative_index,
            get_option(ResolverCache::OPTION),
            'A delayed rebuild carrying an older generation number must not overwrite the newer, already-persisted index.'
        );
    }

    /**
     * Task 11.4: the rebuild lock is a bounded WordPress transient — if a
     * prior holder never releases it (e.g. a fatal error mid-rebuild), it
     * must still recover once its TTL elapses rather than blocking every
     * future rebuild attempt forever.
     */
    public function testAnExpiredRebuildLockRecovers(): void
    {
        $cache = new ResolverCache();
        $acquire = new \ReflectionMethod(ResolverCache::class, 'acquire_lock');
        $acquire->setAccessible(true);
        $release = new \ReflectionMethod(ResolverCache::class, 'release_lock');
        $release->setAccessible(true);

        self::assertTrue($acquire->invoke($cache), 'Sanity: the lock must be free to begin with.');
        self::assertFalse($acquire->invoke($cache), 'A second acquire while still held must fail.');

        // Simulate the held lock's TTL having already elapsed, without
        // waiting LOCK_TTL seconds in the test itself.
        set_transient(ResolverCache::LOCK_KEY, 1, -1);

        self::assertTrue($acquire->invoke($cache), 'An expired lock must recover and allow a new acquire.');

        $release->invoke($cache);
    }
}

/**
 * Exposes PopupManager's protected get_matched()/reset() for the switch_blog
 * test above without widening PopupManager's own public contract.
 */
final class PopupManagerAccessor
{
    public static function warm_matched(): void
    {
        $manager = \Elemacy\Modules\Popups\Services\PopupManager::instance();
        $ref = new \ReflectionMethod($manager, 'get_matched');
        $ref->setAccessible(true);
        $ref->invoke($manager);
    }
}
