<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use WP_Post;

/**
 * Duplicates a library document through the same verified native write
 * boundary {@see LibraryRepository}/{@see DocumentWriter} use, instead of
 * each of the three application services running its own copy loop.
 *
 * Preserves authoring content and its multiplicity byte-equivalently
 * (including quotes/backslashes/Unicode inside `_elementor_data`'s JSON —
 * see copy_meta()'s own note on slashing) while excluding generated/runtime
 * metadata a new document must produce for itself: WordPress's edit-lock
 * bookkeeping and Elementor's own generated CSS/asset/element caches.
 * Extensions declare their own additional exclusions (e.g. analytics
 * counters) through {@see Hooks::LIBRARY_DUPLICATE_EXCLUDED_META_FILTER}.
 */
class DocumentDuplicator
{
    /**
     * Prefixes never copied regardless of group: WordPress's own per-editor
     * edit-lock bookkeeping, which belongs to whoever is editing the source,
     * never the duplicate.
     */
    protected const EXCLUDED_PREFIXES = ['_edit_lock', '_edit_last'];

    /**
     * Elementor's own generated, per-document caches — CSS, registered
     * front-end assets, and the element-output cache — none of which is
     * authoring data; a new document regenerates all three for itself.
     */
    protected const EXCLUDED_KEYS = ['_elementor_css', '_elementor_page_assets', '_elementor_element_cache'];

    protected LibraryRepository $repository;
    protected DocumentWriter $writer;

    public function __construct(?LibraryRepository $repository = null, ?DocumentWriter $writer = null)
    {
        $this->repository = $repository ?? new LibraryRepository();
        $this->writer = $writer ?? new DocumentWriter($this->repository);
    }

    /**
     * @param string[] $extra_excluded_meta Additional keys this specific caller already knows to
     *   exclude on top of this class's own base exclusions and the shared filter below — e.g.
     *   PopupService merging in the pre-existing Hooks::POPUP_DUPLICATE_EXCLUDED_META_FILTER result.
     * @param string|null $document_type See {@see DocumentWriter::create()} — an explicit override
     *   for a group whose Elementor document type is a group-wide constant rather than per type.
     * @throws \Elemacy\Core\Exceptions\NotFoundException
     */
    public function duplicate(int $id, string $group, array $extra_excluded_meta = [], ?string $document_type = null): int
    {
        $source = $this->writer->owned_or_fail($id, $group);

        // A published copy is never exposed before every required write
        // below completes — it starts, and until then stays, a draft.
        $new_id = $this->repository->insert([
            /* translators: %s: original item title. */
            'post_title'   => sprintf(__('%s (Copy)', 'elemacy'), $source->post_title),
            'post_content' => $source->post_content,
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
        ]);

        try {
            $this->copy_meta($source, $new_id, $this->excluded_meta($group, $source, $extra_excluded_meta));

            // Re-derive the document type fresh rather than trust whatever the
            // source's own _elementor_template_type happened to contain — a
            // duplicate of a document saved before a type/document-type sync fix
            // existed must not carry that same staleness forward.
            $type = (string) $this->repository->get_meta($id, MetaKeys::TEMPLATE_TYPE);

            if ($type !== '') {
                $this->writer->update($new_id, $group, [], $type, $document_type);
            }
        } catch (\Throwable $error) {
            // A required write failed after the draft copy was created:
            // compensating cleanup removes it rather than leaving a
            // partially assembled item behind for something else to find.
            $this->writer->compensate($new_id);

            throw $error;
        }

        return $new_id;
    }

    /**
     * @return string[]
     */
    protected function excluded_meta(string $group, WP_Post $source, array $extra_excluded_meta): array
    {
        return array_values(array_unique(array_merge(
            self::EXCLUDED_KEYS,
            $extra_excluded_meta,
            (array) apply_filters(Hooks::LIBRARY_DUPLICATE_EXCLUDED_META_FILTER, [], $group, $source)
        )));
    }

    /**
     * Copies every non-excluded meta key from the source post, preserving
     * multiplicity (a key stored as three separate rows stays three separate
     * rows — add_post_meta() with $unique=false, never update_post_meta(),
     * which would collapse repeated writes for the same key into one) and
     * re-slashing each value before writing it. update_post_meta()/
     * add_post_meta() both call wp_unslash() on the value they are given
     * (WordPress's own convention: callers are expected to pass slashed
     * input, as $_POST already is). A value fetched raw from postmeta is
     * already in its final, unslashed form, so writing it again unchanged
     * would have that second wp_unslash() strip backslashes that are
     * genuinely part of the content — e.g. _elementor_data's own JSON
     * escaping. wp_slash() here exists specifically to cancel that out, the
     * same way Elementor's own Document::save() already wp_slash()es before
     * its own update_post_meta() call, for the same reason.
     */
    protected function copy_meta(WP_Post $source, int $new_id, array $excluded): void
    {
        $meta = get_post_meta($source->ID);

        if (!is_array($meta)) {
            return;
        }

        foreach ($meta as $meta_key => $meta_values) {
            if ($this->is_excluded($meta_key, $excluded)) {
                continue;
            }

            foreach ($meta_values as $meta_value) {
                add_post_meta($new_id, $meta_key, wp_slash(maybe_unserialize($meta_value)), false);
            }
        }
    }

    protected function is_excluded(string $meta_key, array $excluded): bool
    {
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (strpos($meta_key, $prefix) === 0) {
                return true;
            }
        }

        return in_array($meta_key, $excluded, true);
    }
}
