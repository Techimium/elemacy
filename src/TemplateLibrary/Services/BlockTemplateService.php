<?php

namespace Elemacy\TemplateLibrary\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\Core\DTO\PaginatedResultDTO;
use Elemacy\Core\Exceptions\NotFoundException;
use Elemacy\Core\Hooks;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\TemplateLibrary\DocumentDuplicator;
use Elemacy\TemplateLibrary\DocumentWriter;
use Elemacy\TemplateLibrary\DTO\BlockTemplateDTO;
use Elemacy\TemplateLibrary\DTO\BlockTemplateListFilterDTO;
use Elemacy\TemplateLibrary\DTO\CreateBlockTemplateDTO;
use Elemacy\TemplateLibrary\DTO\UpdateBlockTemplateDTO;
use Elemacy\TemplateLibrary\LibraryChanged;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\LibraryRepository;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Support\PostDates;
use WP_Post;
use WP_Query;

/**
 * CRUD for "block" library items — referenced-by-ID building blocks (Loop today).
 * Lives in Core because these items sit on the always-registered library CPT and
 * are not owned by any toggleable module. Deliberately self-contained: no
 * conditions, since block items are neither auto-resolved nor rendered as
 * standalone pages. They only carry editor defaults — a bare Canvas (no theme
 * header/footer) and a pinned Elementor document type.
 *
 * Native persistence, group/type ownership and library/Elementor document
 * type synchronization are owned by the shared {@see LibraryRepository}/
 * {@see DocumentWriter}/{@see DocumentDuplicator} primitives (design.md D6);
 * this service keeps only what is specific to blocks — the fixed Canvas
 * editor defaults — plus its own public DTO-shaped responses.
 */
class BlockTemplateService
{
    const GROUP = 'block';

    /**
     * Default page size when the request omits (or under/over-shoots) per_page.
     *
     * @var int
     */
    const DEFAULT_PER_PAGE = 20;

    protected LibraryRepository $repository;
    protected DocumentWriter $writer;
    protected DocumentDuplicator $duplicator;
    protected TypeRegistry $types;

    public function __construct(
        ?LibraryRepository $repository = null,
        ?DocumentWriter $writer = null,
        ?DocumentDuplicator $duplicator = null,
        ?TypeRegistry $types = null
    ) {
        $this->repository = $repository ?? new LibraryRepository();
        $this->writer = $writer ?? new DocumentWriter($this->repository);
        $this->duplicator = $duplicator ?? new DocumentDuplicator($this->repository, $this->writer);
        $this->types = $types ?? TypeRegistry::instance();
    }

    /**
     * A page of block templates (BlockTemplateDTO items) matching the filter, plus pagination totals.
     */
    public function get_all(BlockTemplateListFilterDTO $filter_dto): PaginatedResultDTO
    {
        $page = max(1, (int) $filter_dto->page);
        $per_page = (int) $filter_dto->per_page;
        $per_page = $per_page > 0 ? min(100, $per_page) : self::DEFAULT_PER_PAGE;

        $query = $this->query($filter_dto, $per_page, $page);
        $templates = $this->hydrate($query);

        return new PaginatedResultDTO($templates, (int) $query->found_posts, (int) $query->max_num_pages, $page, $per_page);
    }

    /**
     * Every block template matching the filter, unbounded — for internal callers
     * (e.g. an Elementor control's option list) that need the full set rather
     * than one REST page. Never expose this to a REST/query-string caller: an
     * unbounded fetch on a large library is expensive and defeats pagination.
     *
     * @return BlockTemplateDTO[]
     */
    public function get_all_unbounded(BlockTemplateListFilterDTO $filter_dto): array
    {
        return $this->hydrate($this->query($filter_dto, -1, 1));
    }

    private function query(BlockTemplateListFilterDTO $filter_dto, int $per_page, int $page): WP_Query
    {
        $query_args = [
            'post_type' => LibraryPostType::POST_TYPE,
            'post_status' => PostStatus::ANY,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if (is_string($filter_dto->search) && $filter_dto->search !== '') {
            $query_args['s'] = $filter_dto->search;
        }

        // The CPT is shared with theme templates and popups, so always scope to
        // block-group types: either the requested type (when it really is a
        // block type — a foreign type must not leak other groups' items through
        // this endpoint) or every block type.
        if (!empty($filter_dto->type) && in_array($filter_dto->type, $this->block_types(), true)) {
            $query_args['meta_query'] = [
                [
                    'key' => MetaKeys::TEMPLATE_TYPE,
                    'value' => $filter_dto->type,
                ],
            ];
        } else {
            $query_args['meta_query'] = [
                [
                    'key' => MetaKeys::TEMPLATE_TYPE,
                    'value' => $this->block_types(),
                    'compare' => 'IN',
                ],
            ];
        }

        if (!empty($filter_dto->status)) {
            $query_args['post_status'] = $filter_dto->status;
        }

        return new WP_Query($query_args);
    }

    /**
     * @return BlockTemplateDTO[]
     */
    private function hydrate(WP_Query $query): array
    {
        $templates = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $templates[] = $this->create_dto(get_post());
            }
            wp_reset_postdata();
        }

        return $templates;
    }

    public function get(int $id): ?BlockTemplateDTO
    {
        $post = $this->repository->find($id);

        if (!$this->repository->owns($post, self::GROUP)) {
            return null;
        }

        return $this->create_dto($post);
    }

    public function get_or_fail(int $id): BlockTemplateDTO
    {
        $template = $this->get($id);

        if (!$template) {
            throw new NotFoundException(esc_html__('Template not found.', 'elemacy'));
        }

        return $template;
    }

    public function create(CreateBlockTemplateDTO $dto): BlockTemplateDTO
    {
        $post_id = $this->writer->create(self::GROUP, [
            'post_title' => $dto->title ?? '',
            'post_status' => $dto->status ?? PostStatus::PUBLISH,
        ], isset($dto->type) ? (string) $dto->type : '');

        $this->apply_editor_defaults($post_id);
        $this->notify($post_id, LibraryChanged::CREATED);

        return $this->create_dto(get_post($post_id));
    }

    public function update(int $id, UpdateBlockTemplateDTO $dto): BlockTemplateDTO
    {
        $post_fields = [];

        if (isset($dto->title)) {
            $post_fields['post_title'] = $dto->title;
        }

        if (isset($dto->status)) {
            $post_fields['post_status'] = $dto->status;
        }

        $this->writer->update($id, self::GROUP, $post_fields, isset($dto->type) ? (string) $dto->type : null);
        $this->notify($id, LibraryChanged::UPDATED);

        return $this->create_dto(get_post($id));
    }

    public function duplicate(int $id): BlockTemplateDTO
    {
        $new_id = $this->duplicator->duplicate($id, self::GROUP);

        $this->apply_editor_defaults($new_id);
        $this->notify($new_id, LibraryChanged::DUPLICATED);

        return $this->create_dto(get_post($new_id));
    }

    public function delete(int $id): bool
    {
        $this->writer->owned_or_fail($id, self::GROUP);
        $this->repository->delete($id);
        $this->notify($id, LibraryChanged::DELETED);

        return true;
    }

    /**
     * Editor defaults every block item needs regardless of type — a bare
     * Canvas (no theme header/footer) and builder edit mode — reasserted
     * unconditionally so a duplicate whose source predates these defaults
     * still gets them.
     */
    protected function apply_editor_defaults(int $id): void
    {
        $this->repository->set_meta($id, '_wp_page_template', 'elementor_canvas');
        $this->repository->set_meta($id, '_elementor_edit_mode', 'builder');
    }

    /**
     * @param LibraryChanged::CREATED|LibraryChanged::UPDATED|LibraryChanged::DUPLICATED|LibraryChanged::DELETED $action
     */
    protected function notify(int $id, string $action): void
    {
        $type = (string) $this->repository->get_meta($id, MetaKeys::TEMPLATE_TYPE);

        do_action(Hooks::LIBRARY_CHANGED_ACTION, new LibraryChanged($id, self::GROUP, $type, $action));
    }

    /**
     * The library item types this service is responsible for.
     *
     * @return string[]
     */
    protected function block_types(): array
    {
        return $this->types->names_in_group(self::GROUP);
    }

    protected function create_dto(WP_Post $post): BlockTemplateDTO
    {
        $dto = new BlockTemplateDTO();
        $dto->id = $post->ID;
        $dto->title = $post->post_title;
        $dto->status = $post->post_status;
        $dto->type = get_post_meta($post->ID, MetaKeys::TEMPLATE_TYPE, true);
        $dto->author = (int) $post->post_author;
        $dto->date = PostDates::gmt_datetime($post);

        return $dto;
    }
}
