<?php

namespace Elemacy\Modules\LoopBuilder\PostCard;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Access\PublicLoopLimits;
use Elemacy\Loop\Contracts\ItemPresenterInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\NativePostItemInterface;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\Rendering\ItemPlacement;
use Elemacy\Loop\Rendering\LoopItemScope;
use Elemacy\Loop\Rendering\LoopRenderIdentity;
use WP_Post;

/**
 * Renders each post as a card built from the widget's saved parts.
 *
 * @internal
 */
final class PostCardPresenter implements ItemPresenterInterface
{
    private const IDENTITY = 'post-card';

    private LoopItemScope $scope;
    private ?array $parsed_settings = null;
    private ?PostCardDefinition $definition = null;

    public function __construct(LoopItemScope $scope)
    {
        $this->scope = $scope;
    }

    /** {@inheritDoc} */
    public function assert_public(array $settings): void
    {
        if (isset($settings[PostCardDefinition::PARTS]) && !is_array($settings[PostCardDefinition::PARTS])) {
            throw PublicLoopLimits::invalid_settings();
        }
    }

    /** {@inheritDoc} */
    public function describe_unavailable(array $settings): ?string
    {
        try {
            $this->assert_public($settings);
        } catch (ValidationException $error) {
            return __('This widget\'s card settings could not be read.', 'elemacy');
        }

        return null;
    }

    /** {@inheritDoc} */
    public function render_item(LoopItemInterface $item, array $settings, ItemPlacement $placement): bool
    {
        if (!$item instanceof NativePostItemInterface) {
            return false;
        }

        $parent = LoopContext::current_frame();
        $identity = new LoopRenderIdentity(
            $placement->placement(),
            self::IDENTITY,
            $placement->source(),
            $item->get_identity(),
            $placement->occurrence(),
            $placement->batch(),
            $parent ? $parent->render_key() : ''
        );
        $definition = $this->definition_for($settings);

        return $this->scope->render(
            $item,
            $identity,
            $placement->source(),
            0,
            $placement->occurrence(),
            function () use ($item, $definition, $placement): bool {
                $card = $this->card($item->get_post(), $definition, $placement);
                echo $card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every part returns escaped markup.

                return '' !== $card;
            }
        );
    }

    /** One list reads the same settings for every item, so they are parsed once. */
    private function definition_for(array $settings): PostCardDefinition
    {
        if (null === $this->definition || $this->parsed_settings !== $settings) {
            $this->definition = new PostCardDefinition($settings);
            $this->parsed_settings = $settings;
        }

        return $this->definition;
    }

    private function card(WP_Post $post, PostCardDefinition $definition, ItemPlacement $placement): string
    {
        $rows = $definition->rows();
        $image_row = $this->first_image_row($rows);
        $context = new PostCardContext(
            $definition->whole_card_link(),
            $this->has_linked_title($rows, $post),
            $definition->new_tab(),
            $placement->occurrence(),
            $placement->page()
        );

        $image = '';
        if (null !== $image_row) {
            $image = $this->row_html($post, $rows[$image_row], $context);
            unset($rows[$image_row]);
        }
        $by_zone = $this->rendered_zones($post, $rows, $context, '' !== $image);

        $media = '';
        if ('' !== $image) {
            $media = '<div class="elemacy-post-card__media">' . $image
                . $this->zone(PostCardDefinition::ZONE_MEDIA_TOP, $by_zone)
                . $this->zone(PostCardDefinition::ZONE_MEDIA_BOTTOM, $by_zone)
                . '</div>';
        }

        $content = $this->zone(PostCardDefinition::ZONE_BODY, $by_zone) . $this->zone(PostCardDefinition::ZONE_FOOTER, $by_zone);

        if ('' === $media && '' === $content) {
            return '';
        }

        $classes = ['elemacy-post-card', 'elemacy-post-card--type-' . sanitize_html_class($post->post_type)];
        if ('' !== $image) {
            $classes[] = 'elemacy-post-card--has-image';
        }
        if ($definition->whole_card_link()) {
            $classes[] = 'elemacy-post-card--linked';
        }

        return '<article class="' . esc_attr(implode(' ', $classes)) . '">' . $media
            . ('' !== $content ? '<div class="elemacy-post-card__content">' . $content . '</div>' : '')
            . ($definition->whole_card_link() ? $this->overlay_link($post, $context) : '')
            . '</article>';
    }

    /** The row whose image fills the card's media area. */
    private function first_image_row(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            if ('image' === $row['part']) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Whether a Title row links this post and shows on every device, so it is
     * the card's keyboard stop. A post with no title renders no Title row.
     */
    private function has_linked_title(array $rows, WP_Post $post): bool
    {
        if ('' === get_the_title($post)) {
            return false;
        }

        foreach ($rows as $row) {
            if ('title' === $row['part'] && 'yes' === $row['title_link'] && empty($row['row_hide_on'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Each zone's finished markup. Without an image there is nothing to place
     * an overlay on, so overlay parts open the card body instead.
     *
     * @return array<string, string>
     */
    private function rendered_zones(WP_Post $post, array $rows, PostCardContext $context, bool $has_image): array
    {
        $grouped = [];
        $overlays = [];

        foreach ($rows as $row) {
            $zone = $row['zone'];
            if (!$has_image && in_array($zone, [PostCardDefinition::ZONE_MEDIA_TOP, PostCardDefinition::ZONE_MEDIA_BOTTOM], true)) {
                $overlays[] = $row;
                continue;
            }
            $grouped[$zone][] = $row;
        }

        if ($overlays) {
            $grouped[PostCardDefinition::ZONE_BODY] = array_merge($overlays, $grouped[PostCardDefinition::ZONE_BODY] ?? []);
        }

        $zones = [];

        foreach ($grouped as $zone => $zone_rows) {
            $zones[$zone] = $this->zone_html($post, $zone_rows, $context);
        }

        return $zones;
    }

    private function zone_html(WP_Post $post, array $rows, PostCardContext $context): string
    {
        $html = '';
        $previous = null;
        $open = false;

        foreach ($rows as $row) {
            $part = PostCardParts::get($row['part']);
            $markup = $part->render($post, $row, $context);

            if ('' === $markup) {
                continue;
            }

            if ($part->groups_adjacent_rows() && $previous === $row['part']) {
                $html .= $part->join($row) . $this->wrap($row, $markup, 'span');
                continue;
            }

            if ($open) {
                $html .= '</div>';
                $open = false;
            }

            if ($part->groups_adjacent_rows()) {
                $html .= '<div class="elemacy-post-card__group elemacy-post-card__group--' . esc_attr($row['part']) . '">' . $this->wrap($row, $markup, 'span');
                $open = true;
            } else {
                $html .= $this->wrap($row, $markup, 'div');
            }

            $previous = $row['part'];
        }

        return $open ? $html . '</div>' : $html;
    }

    private function row_html(WP_Post $post, array $row, PostCardContext $context): string
    {
        $markup = PostCardParts::get($row['part'])->render($post, $row, $context);

        return '' === $markup ? '' : $this->wrap($row, $markup, 'div');
    }

    private function wrap(array $row, string $markup, string $tag): string
    {
        $classes = ['elemacy-post-card__part', 'elemacy-post-card__part--' . $row['part']];

        if (!empty($row['_id'])) {
            $classes[] = 'elementor-repeater-item-' . $row['_id'];
        }

        foreach ((array) ($row['row_hide_on'] ?? []) as $device) {
            $classes[] = 'elementor-hidden-' . sanitize_html_class((string) $device);
        }

        return '<' . $tag . ' class="' . esc_attr(implode(' ', $classes)) . '">' . $markup . '</' . $tag . '>';
    }

    private function zone(string $zone, array $by_zone): string
    {
        $html = $by_zone[$zone] ?? '';

        return '' === $html ? '' : '<div class="elemacy-post-card__zone elemacy-post-card__zone--' . esc_attr(str_replace('_', '-', $zone)) . '">' . $html . '</div>';
    }

    /**
     * The click area over the whole card. A linked Title is the keyboard stop,
     * so the overlay is pointer-only; otherwise it is the card's own link.
     */
    private function overlay_link(WP_Post $post, PostCardContext $context): string
    {
        $href = '<a class="elemacy-post-card__overlay" href="' . esc_url(get_permalink($post)) . '"' . $context->link_attributes();

        if ($context->title_is_linked()) {
            return $href . ' tabindex="-1" aria-hidden="true"></a>';
        }

        return $href . '><span class="elemacy-post-card__sr">' . esc_html(PostCardText::link_name($post)) . '</span></a>';
    }
}
