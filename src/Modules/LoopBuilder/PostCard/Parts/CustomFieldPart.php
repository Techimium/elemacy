<?php

namespace Elemacy\Modules\LoopBuilder\PostCard\Parts;

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\PostCard\PostCardContext;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardFields;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use WP_Post;

/**
 * A post meta value or ACF field.
 *
 * @internal
 */
final class CustomFieldPart extends AbstractPostCardPart
{
    public function key(): string
    {
        return 'custom_field';
    }

    public function label(): string
    {
        return esc_html__('Custom Field', 'elemacy');
    }

    public function defaults(): array
    {
        return [
            'custom_field_key' => '',
            'custom_field_before' => '',
            'custom_field_after' => '',
            'custom_field_fallback' => '',
            'custom_field_link' => '',
        ];
    }

    public function register_row_controls(Repeater $rows, array $condition): void
    {
        foreach (
            [
                'custom_field_key' => esc_html__('Field Name', 'elemacy'),
                'custom_field_before' => esc_html__('Text Before', 'elemacy'),
                'custom_field_after' => esc_html__('Text After', 'elemacy'),
                'custom_field_fallback' => esc_html__('Fallback', 'elemacy'),
            ] as $id => $label
        ) {
            $rows->add_control(
                $id,
                [
                    'label' => $label,
                    'type' => Controls_Manager::TEXT,
                    'default' => '',
                    'condition' => $condition,
                ]
            );
        }

        $rows->add_control(
            'custom_field_link',
            [
                'label' => esc_html__('Link When the Value Is a URL', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => '',
                'condition' => $condition,
            ]
        );
    }

    public function render(WP_Post $post, array $row, PostCardContext $card): string
    {
        $value = PostCardFields::value(trim((string) $row['custom_field_key']), $post);

        if ('' === $value) {
            $fallback = (string) $row['custom_field_fallback'];

            return '' === $fallback || post_password_required($post) ? '' : esc_html($fallback);
        }

        $text = esc_html($value);

        if ('yes' === $row['custom_field_link'] && $this->is_web_address($value)) {
            $text = '<a href="' . esc_url($value) . '"' . $card->link_attributes() . '>' . $text . '</a>';
        }

        return esc_html((string) $row['custom_field_before']) . $text . esc_html((string) $row['custom_field_after']);
    }

    /**
     * An absolute http or https address. Read from the value alone, never by
     * resolving its host, which would look it up on every render.
     */
    private function is_web_address(string $value): bool
    {
        $parts = wp_parse_url($value);

        return is_array($parts) && !empty($parts['host'])
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true);
    }
}
