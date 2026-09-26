<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionRepository;
use Elemacy\Core\Constants\PostStatus;
use Elemacy\Http\DTO\PaginatedResultDTO;
use Elemacy\Http\Exceptions\NotFoundException;
use Elemacy\Core\Hooks;
use Elemacy\Modules\ThemeBuilder\DTO\CreateTemplateDTO;
use Elemacy\Modules\ThemeBuilder\DTO\TemplateDTO;
use Elemacy\Modules\ThemeBuilder\DTO\TemplateListFilterDTO;
use Elemacy\Modules\ThemeBuilder\DTO\UpdateTemplateDTO;
use Elemacy\Support\PostDates;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\TemplateLibrary\DocumentDuplicator;
use Elemacy\TemplateLibrary\DocumentWriter;
use Elemacy\TemplateLibrary\LibraryChanged;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\LibraryRepository;
use Elemacy\TemplateLibrary\TypeRegistry;
use WP_Post;
use WP_Query;

/**
 * Native persistence, group/type ownership and library/Elementor document
 * type synchronization are owned by the shared {@see LibraryRepository}/
 * {@see DocumentWriter}/{@see DocumentDuplicator} primitives;
 * this service keeps only what is specific to theme templates — the
 * per-type page template and conditions — plus its own public DTO-shaped
 * responses.
 */
class TemplateService
{
    public const GROUP = 'theme';

    /**
     * Default page size when the request omits (or under/over-shoots) per_page.
     *
     * @var int
     */
    public const DEFAULT_PER_PAGE = 20;

    protected ConditionRepository $conditions;
    protected LibraryRepository $repository;
    protected DocumentWriter $writer;
    protected DocumentDuplicator $duplicator;
    protected TypeRegistry $types;

    public function __construct(
        ConditionRepository $conditions,
        LibraryRepository $repository,
        DocumentWriter $writer,
        DocumentDuplicator $duplicator,
        TypeRegistry $types
    ) {
        $this->conditions = $conditions;
        $this->repository = $repository;
        $this->writer = $writer;
        $this->duplicator = $duplicator;
        $this->types = $types;
    }

    /**
     * A page of theme templates (TemplateDTO items) matching the filter, plus pagination totals.
     */
    public function get_all(TemplateListFilterDTO $filter_dto): PaginatedResultDTO
    {
        $page = max(1, (int) $filter_dto->page);
        $per_page = (int) $filter_dto->per_page;
        $per_page = $per_page > 0 ? min(100, $per_page) : self::DEFAULT_PER_PAGE;

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

        // The CPT is shared with popups, so always scope to theme-group types:
        // either the requested type (when it really is a theme type — a foreign
        // type must not leak other groups' items through this endpoint) or every
        // theme type.
        if (!empty($filter_dto->type) && in_array($filter_dto->type, $this->theme_types(), true)) {
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
                    'value' => $this->theme_types(),
                    'compare' => 'IN',
                ],
            ];
        }

        if (!empty($filter_dto->status)) {
            $query_args['post_status'] = $filter_dto->status;
        }

        $query = new WP_Query($query_args);

        $templates = [];

        // Not the_post(): it leaves a global post that wp_reset_postdata() cannot clear
        // when the main query has none.
        foreach ($query->posts as $post) {
            $templates[] = $this->create_dto($post);
        }

        return new PaginatedResultDTO($templates, (int) $query->found_posts, (int) $query->max_num_pages, $page, $per_page);
    }

    public function get(int $id)
    {
        $post = $this->repository->find($id);

        if (!$this->repository->owns($post, self::GROUP)) {
            return null;
        }

        return $this->create_dto($post);
    }

    public function get_or_fail(int $id): TemplateDTO
    {
        $template = $this->get($id);

        if (!$template) {
            throw new NotFoundException(esc_html__('Template not found.', 'elemacy'));
        }

        return $template;
    }

    public function create(CreateTemplateDTO $dto)
    {
        $post_id = $this->writer->create(self::GROUP, [
            'post_title' => $dto->title ?? '',
            'post_status' => $dto->status ?? PostStatus::PUBLISH,
        ], isset($dto->type) ? (string) $dto->type : '');

        if (isset($dto->type)) {
            $this->repository->set_meta($post_id, '_wp_page_template', $this->page_template_for_type((string) $dto->type));
        }

        $this->repository->set_meta($post_id, '_elementor_edit_mode', 'builder');

        if (isset($dto->conditions)) {
            $this->conditions->save($post_id, (array) $dto->conditions);
        }

        $this->notify($post_id, LibraryChanged::CREATED);

        return $this->create_dto(get_post($post_id));
    }

    public function update(int $id, UpdateTemplateDTO $dto)
    {
        $post_fields = [];

        if (isset($dto->title)) {
            $post_fields['post_title'] = $dto->title;
        }

        if (isset($dto->status)) {
            $post_fields['post_status'] = $dto->status;
        }

        $this->writer->update($id, self::GROUP, $post_fields, isset($dto->type) ? (string) $dto->type : null);

        if (isset($dto->type)) {
            $this->repository->set_meta($id, '_wp_page_template', $this->page_template_for_type((string) $dto->type));
        }

        if (isset($dto->conditions)) {
            $this->conditions->save($id, (array) $dto->conditions);
        }

        $this->notify($id, LibraryChanged::UPDATED);

        return $this->create_dto(get_post($id));
    }

    public function duplicate(int $id)
    {
        $new_id = $this->duplicator->duplicate($id, self::GROUP);

        // Matches the pre-existing behavior this replaces: only edit mode is
        // reasserted here — _wp_page_template is left as whatever the copy
        // carried over, correct already since duplication never changes type.
        $this->repository->set_meta($new_id, '_elementor_edit_mode', 'builder');

        $this->notify($new_id, LibraryChanged::DUPLICATED);

        return $this->create_dto(get_post($new_id));
    }

    public function delete(int $id)
    {
        $this->writer->owned_or_fail($id, self::GROUP);
        $this->repository->delete($id);
        $this->notify($id, LibraryChanged::DELETED);

        return true;
    }

    /**
     * @param int                                                                                                $id
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
    protected function theme_types(): array
    {
        return $this->types->names_in_group(self::GROUP);
    }

    /**
     * Elementor page template the editor (and direct preview) renders the type
     * in.
     */
    protected function page_template_for_type(string $type): string
    {
        return in_array($type, ['header', 'footer'], true)
            ? 'elementor_canvas'
            : 'elementor_header_footer';
    }

    protected function create_dto($post): TemplateDTO
    {
        $dto = new TemplateDTO();
        $dto->id = $post->ID;
        $dto->title = $post->post_title;
        $dto->status = $post->post_status;
        $dto->type = get_post_meta($post->ID, MetaKeys::TEMPLATE_TYPE, true);
        $dto->author = (int) $post->post_author;
        $dto->date = PostDates::gmt_datetime($post);
        $dto->conditions = $this->conditions->get($post->ID);

        return $dto;
    }
}
