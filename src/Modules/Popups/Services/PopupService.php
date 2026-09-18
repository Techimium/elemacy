<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionRepository;
use Elemacy\Core\Constants\PostStatus;
use Elemacy\Core\DTO\PaginatedResultDTO;
use Elemacy\Core\Exceptions\NotFoundException;
use Elemacy\Core\Hooks;
use Elemacy\Modules\Popups\Constants\MetaKeys;
use Elemacy\Modules\Popups\DTO\CreatePopupDTO;
use Elemacy\Modules\Popups\DTO\PopupDTO;
use Elemacy\Modules\Popups\DTO\PopupListFilterDTO;
use Elemacy\Modules\Popups\DTO\RuleDTO;
use Elemacy\Modules\Popups\DTO\TriggerDTO;
use Elemacy\Modules\Popups\DTO\UpdatePopupDTO;
use Elemacy\Support\PostDates;
use Elemacy\TemplateLibrary\Constants\MetaKeys as LibraryMetaKeys;
use Elemacy\TemplateLibrary\DocumentDuplicator;
use Elemacy\TemplateLibrary\DocumentWriter;
use Elemacy\TemplateLibrary\LibraryChanged;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\LibraryRepository;
use Elemacy\TemplateLibrary\TypeRegistry;
use WP_Query;

/**
 * Native persistence, group/type ownership and library/Elementor document
 * type synchronization are owned by the shared {@see LibraryRepository}/
 * {@see DocumentWriter}/{@see DocumentDuplicator} primitives (design.md D6);
 * this service keeps only what is specific to popups — the fixed Canvas
 * editor defaults, triggers/rules, and conditions — plus its own public
 * DTO-shaped responses. Every popup type shares one Elementor document
 * (`elemacy_popup`) regardless of which type is selected, so
 * DocumentWriter's per-type registry resolution is overridden with that
 * constant rather than relied on (see DocumentWriter::create()'s own doc).
 */
class PopupService
{
    const GROUP = 'popup';
    const DOCUMENT_TYPE = 'elemacy_popup';

    /**
     * Default page size when the request omits (or under/over-shoots) per_page.
     *
     * @var int
     */
    const DEFAULT_PER_PAGE = 20;

    protected ConditionRepository $conditions;
    protected LibraryRepository $repository;
    protected DocumentWriter $writer;
    protected DocumentDuplicator $duplicator;
    protected TypeRegistry $types;

    public function __construct(
        ?ConditionRepository $conditions = null,
        ?LibraryRepository $repository = null,
        ?DocumentWriter $writer = null,
        ?DocumentDuplicator $duplicator = null,
        ?TypeRegistry $types = null
    ) {
        $this->conditions = $conditions ?? new ConditionRepository();
        $this->repository = $repository ?? new LibraryRepository();
        $this->writer = $writer ?? new DocumentWriter($this->repository);
        $this->duplicator = $duplicator ?? new DocumentDuplicator($this->repository, $this->writer);
        $this->types = $types ?? TypeRegistry::instance();
    }

    /**
     * A page of popups (PopupDTO items) matching the filter, plus pagination totals.
     */
    public function get_all(PopupListFilterDTO $filter_dto): PaginatedResultDTO
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

        // The CPT is shared with theme templates, so always scope to popup-group
        // types: either the requested type (when it really is a popup type — a
        // foreign type must not leak other groups' items through this endpoint)
        // or every popup type.
        if (!empty($filter_dto->type) && in_array($filter_dto->type, $this->popup_types(), true)) {
            $query_args['meta_query'] = [
                [
                    'key' => LibraryMetaKeys::TEMPLATE_TYPE,
                    'value' => $filter_dto->type,
                ],
            ];
        } else {
            $query_args['meta_query'] = [
                [
                    'key' => LibraryMetaKeys::TEMPLATE_TYPE,
                    'value' => $this->popup_types(),
                    'compare' => 'IN',
                ],
            ];
        }

        if (!empty($filter_dto->status)) {
            $query_args['post_status'] = $filter_dto->status;
        }

        $query = new WP_Query($query_args);

        $popups = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $popups[] = $this->create_dto(get_post());
            }
            wp_reset_postdata();
        }

        return new PaginatedResultDTO($popups, (int) $query->found_posts, (int) $query->max_num_pages, $page, $per_page);
    }

    public function get(int $id)
    {
        $post = $this->repository->find($id);

        if (!$this->repository->owns($post, self::GROUP)) {
            return null;
        }

        return $this->create_dto($post);
    }

    public function get_or_fail(int $id): PopupDTO
    {
        $popup = $this->get($id);

        if (!$popup) {
            throw new NotFoundException(esc_html__('Popup not found.', 'elemacy'));
        }

        return $popup;
    }

    public function create(CreatePopupDTO $dto)
    {
        $post_id = $this->writer->create(self::GROUP, [
            'post_title' => $dto->title ?? '',
            'post_status' => $dto->status ?? PostStatus::DRAFT,
        ], isset($dto->type) ? (string) $dto->type : '', self::DOCUMENT_TYPE);

        $this->apply_editor_defaults($post_id);

        if (isset($dto->conditions)) {
            $this->conditions->save($post_id, (array) $dto->conditions);
        }

        if (isset($dto->triggers)) {
            $this->save_triggers($post_id, (array) $dto->triggers);
        }

        if (isset($dto->rules)) {
            $this->save_rules($post_id, (array) $dto->rules);
        }

        $this->notify($post_id, LibraryChanged::CREATED);

        return $this->create_dto(get_post($post_id));
    }

    public function update(int $id, UpdatePopupDTO $dto)
    {
        $post_fields = [];

        if (isset($dto->title)) {
            $post_fields['post_title'] = $dto->title;
        }

        if (isset($dto->status)) {
            $post_fields['post_status'] = $dto->status;
        }

        $this->writer->update($id, self::GROUP, $post_fields, isset($dto->type) ? (string) $dto->type : null, self::DOCUMENT_TYPE);

        if (isset($dto->conditions)) {
            $this->conditions->save($id, (array) $dto->conditions);
        }

        if (isset($dto->triggers)) {
            $this->save_triggers($id, (array) $dto->triggers);
        }

        if (isset($dto->rules)) {
            $this->save_rules($id, (array) $dto->rules);
        }

        $this->notify($id, LibraryChanged::UPDATED);

        return $this->create_dto(get_post($id));
    }

    public function duplicate(int $id)
    {
        // Meta keys add-ons own and should not carry over to the copy (e.g. pro
        // resets analytics counters for the new popup) — merged with the
        // shared LIBRARY_DUPLICATE_EXCLUDED_META_FILTER inside DocumentDuplicator.
        $extra_excluded_meta = (array) apply_filters(Hooks::POPUP_DUPLICATE_EXCLUDED_META_FILTER, []);

        $new_id = $this->duplicator->duplicate($id, self::GROUP, $extra_excluded_meta, self::DOCUMENT_TYPE);

        $this->apply_editor_defaults($new_id);
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
     * Popups render in an isolated Elementor canvas (no theme header/footer),
     * reasserted unconditionally so a duplicate whose source predates these
     * defaults still gets them.
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
        $type = (string) $this->repository->get_meta($id, LibraryMetaKeys::TEMPLATE_TYPE);

        do_action(Hooks::LIBRARY_CHANGED_ACTION, new LibraryChanged($id, self::GROUP, $type, $action));
    }

    protected function get_triggers(int $popup_id): array
    {
        $raw = get_post_meta($popup_id, MetaKeys::TRIGGERS, true);

        return TriggerDTO::collection(is_array($raw) ? $raw : []);
    }

    protected function save_triggers(int $popup_id, array $triggers): void
    {
        update_post_meta($popup_id, MetaKeys::TRIGGERS, $this->normalize_items(TriggerDTO::collection_or_fail($triggers)));
    }

    protected function get_rules(int $popup_id): array
    {
        $raw = get_post_meta($popup_id, MetaKeys::RULES, true);

        return RuleDTO::collection(is_array($raw) ? $raw : []);
    }

    protected function save_rules(int $popup_id, array $rules): void
    {
        update_post_meta($popup_id, MetaKeys::RULES, $this->normalize_items(RuleDTO::collection_or_fail($rules)));
    }

    /**
     * Ensures every trigger/rule DTO has an id, then serializes back.
     *
     * @param (TriggerDTO|RuleDTO)[] $items
     * @return array
     */
    protected function normalize_items(array $items): array
    {
        return array_map(function ($dto): array {
            $dto->id = $dto->id !== '' ? $dto->id : wp_generate_uuid4();

            return $dto->to_array();
        }, $items);
    }

    /**
     * The library item types this service is responsible for.
     *
     * @return string[]
     */
    protected function popup_types(): array
    {
        return $this->types->names_in_group(self::GROUP);
    }

    protected function create_dto($post): PopupDTO
    {
        $dto = new PopupDTO();
        $dto->id = $post->ID;
        $dto->title = $post->post_title;
        $dto->status = $post->post_status;
        $dto->type = get_post_meta($post->ID, LibraryMetaKeys::TEMPLATE_TYPE, true);
        $dto->author = (int) $post->post_author;
        $dto->date = PostDates::gmt_datetime($post);
        $dto->conditions = $this->conditions->get($post->ID);
        $dto->triggers = $this->get_triggers($post->ID);
        $dto->rules = $this->get_rules($post->ID);

        return $dto;
    }
}
