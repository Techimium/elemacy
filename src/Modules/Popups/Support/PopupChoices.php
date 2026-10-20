<?php

namespace Elemacy\Modules\Popups\Support;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TypeRegistry;

/**
 * The site's popups as select options, for a trigger or rule parameter that
 * names another popup. Free's placeholder and Pro's implementation of the same
 * trigger both build their option list here, so the two can never differ.
 */
final class PopupChoices
{
    /** The option value that means "no popup chosen". */
    public const NONE = '0';

    /** More than this many popups are not offered; the list is for a settings form. */
    private const LIMIT = 200;

    /**
     * @param string $none_label The label of the "no popup chosen" option.
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(string $none_label): array
    {
        $options = [
            [
                'value' => self::NONE,
                'label' => $none_label,
            ],
        ];

        $query = new \WP_Query([
            'post_type'              => LibraryPostType::POST_TYPE,
            'post_status'            => PostStatus::ANY,
            'posts_per_page'         => self::LIMIT,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'meta_query'             => [
                [
                    'key'     => MetaKeys::TEMPLATE_TYPE,
                    'value'   => TypeRegistry::instance()->names_in_group('popup'),
                    'compare' => 'IN',
                ],
            ],
        ]);

        foreach ($query->posts as $id) {
            $title = get_the_title((int) $id);

            $options[] = [
                'value' => (string) (int) $id,
                'label' => sprintf('%s (#%d)', '' !== $title ? $title : __('(no title)', 'elemacy'), (int) $id),
            ];
        }

        return $options;
    }
}
