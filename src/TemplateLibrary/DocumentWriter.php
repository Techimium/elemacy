<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\NotFoundException;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use WP_Post;

/**
 * Coordinates validated builder/type ownership, post status and the library
 * type + Elementor document type metadata that must change together — the
 * defect exists to fix: a block template whose type changed without its
 * Elementor document type following.
 */
class DocumentWriter
{
    protected LibraryRepository $repository;
    protected TypeRegistry $types;

    public function __construct(LibraryRepository $repository, TypeRegistry $types)
    {
        $this->repository = $repository;
        $this->types = $types;
    }

    /**
     * @param string      $group The library group being written.
     * @param array       $post_fields wp_insert_post()'s own $postarr (post_type is pinned by the repository).
     * @param string      $type The template type within that group.
     * @param string|null $document_type Overrides the registry-resolved Elementor document type —
     *   for a group whose document type is a group-wide constant regardless of which registered
     *   type is selected (e.g. popups: every type shares 'elemacy_popup') rather than resolved
     *   per type (blocks/theme templates, via each type's own registered document class).
     * @throws ValidationException When $type is not registered for $group.
     */
    public function create(string $group, array $post_fields, string $type = '', ?string $document_type = null): int
    {
        if ($type !== '') {
            $this->assert_valid_type($group, $type);
        }

        $post_id = $this->repository->insert($post_fields);

        if ($type !== '') {
            try {
                $this->sync_type($post_id, $type, $document_type);
            } catch (\Throwable $error) {
                $this->compensate($post_id);

                throw $error;
            }
        }

        return $post_id;
    }

    /**
     * Removes a partially assembled item after one of its required writes
     * failed. A failure here must never mask the original error — it is the
     * one the caller needs to see — so it is swallowed rather than thrown.
     */
    public function compensate(int $post_id): void
    {
        try {
            $this->repository->delete($post_id);
        } catch (\Throwable $cleanup_error) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Deliberate, see below.
            // Deliberately ignored: see this method's own doc comment.
        }
    }

    /**
     * @param int         $id The library item to update.
     * @param string      $group The library group it must belong to.
     * @param array       $post_fields wp_update_post()'s own $postarr (ID is pinned by the repository).
     * @param string|null $type A new template type, or null to keep the current one.
     * @param string|null $document_type See {@see create()}.
     * @throws NotFoundException|ValidationException When $id is not an item of $group, or $type is not registered for it.
     */
    public function update(int $id, string $group, array $post_fields, ?string $type = null, ?string $document_type = null): WP_Post
    {
        $post = $this->owned_or_fail($id, $group);

        if ($type !== null) {
            $this->assert_valid_type($group, $type);
        }

        $this->repository->update($id, $post_fields);

        if ($type !== null) {
            $this->sync_type($id, $type, $document_type);
        }

        return $post;
    }

    /**
     * @throws NotFoundException When $id is not an item of $group.
     */
    public function owned_or_fail(int $id, string $group): WP_Post
    {
        $post = $this->repository->find($id);

        if (!$this->repository->owns($post, $group)) {
            throw new NotFoundException(esc_html__('Item not found.', 'elemacy'));
        }

        return $post;
    }

    /**
     * The Elementor document type for a registered type, resolved through
     * the shared TypeRegistry — a type with no document of its
     * own, or one this registry does not recognize at all, keeps Elementor's
     * built-in page document.
     */
    public function document_type(string $type): string
    {
        return $this->types->get($type)?->get_document_type() ?? 'wp-page';
    }

    /**
     * Keeps the library type and Elementor document type meta synchronized
     * on every write that sets a type — create() and any update() that
     * changes it — so neither can go stale relative to the other.
     */
    protected function sync_type(int $id, string $type, ?string $document_type): void
    {
        $this->repository->set_meta($id, MetaKeys::TEMPLATE_TYPE, $type);
        $this->repository->set_meta($id, '_elementor_template_type', $document_type ?? $this->document_type($type));
    }

    /**
     * @throws ValidationException When $type is not registered for $group.
     */
    protected function assert_valid_type(string $group, string $type): void
    {
        if (!in_array($type, $this->types->names_in_group($group), true)) {
            throw ValidationException::with_errors([
                'type' => [esc_html__('Invalid template type.', 'elemacy')],
            ]);
        }
    }
}
