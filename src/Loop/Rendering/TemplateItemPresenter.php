<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Access\PublicRenderPolicy;
use Elemacy\Loop\Contracts\ItemPresenterInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TypeRegistry;
use WP_Post;

/**
 * Renders each item through the Loop Item template saved in `template_id`.
 *
 * @internal
 */
final class TemplateItemPresenter implements ItemPresenterInterface
{
    private TypeRegistry $types;
    private PublicRenderPolicy $policy;
    private LoopRenderer $renderer;

    public function __construct(TypeRegistry $types, PublicRenderPolicy $policy, LoopRenderer $renderer)
    {
        $this->types = $types;
        $this->policy = $policy;
        $this->renderer = $renderer;
    }

    /** {@inheritDoc} */
    public function assert_public(array $settings): void
    {
        $this->assert_template($this->template_id($settings['template_id'] ?? null));
    }

    /** {@inheritDoc} */
    public function describe_unavailable(array $settings): ?string
    {
        $template = get_post((int) ($settings['template_id'] ?? 0));

        if (!$template instanceof WP_Post) {
            return __('The selected Loop Item template no longer exists.', 'elemacy');
        }
        if ('publish' !== $template->post_status) {
            /* translators: %s: template title */
            return sprintf(__('The Loop Item template "%s" is not published, so visitors cannot see this loop.', 'elemacy'), $template->post_title);
        }
        if (post_password_required($template)) {
            return __('The Loop Item template is password protected, so visitors cannot see this loop.', 'elemacy');
        }

        try {
            $this->assert_template($template->ID);
        } catch (ValidationException $error) {
            return __('The selected template is not a Loop Item template.', 'elemacy');
        }

        return null;
    }

    /** {@inheritDoc} */
    public function render_item(LoopItemInterface $item, array $settings, ItemPlacement $placement): bool
    {
        return $this->renderer->render_item(
            (int) ($settings['template_id'] ?? 0),
            $item,
            $placement->placement(),
            $placement->source(),
            $placement->occurrence(),
            $placement->batch()
        );
    }

    /**
     * @param int $id Referenced loop template ID.
     * @return void
     * @throws ValidationException If the template cannot render publicly.
     */
    private function assert_template(int $id): void
    {
        $template = $this->policy->public_post($id);
        $loop_type = $this->types->get('loop');
        if (
            !$loop_type || 'block' !== $loop_type->group
            || LibraryPostType::POST_TYPE !== $template->post_type
            || 'loop' !== get_post_meta($id, MetaKeys::TEMPLATE_TYPE, true)
            || 'elemacy_loop' !== get_post_meta($id, '_elementor_template_type', true)
            || 'builder' !== get_post_meta($id, '_elementor_edit_mode', true)
        ) {
            throw PublicRenderPolicy::unavailable();
        }
    }

    /**
     * @param mixed $value Persisted Elementor SELECT2 value (integer or digits).
     * @return int
     * @throws ValidationException On malformed, absent or overflowing IDs.
     */
    private function template_id($value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (
            is_string($value) && preg_match('/\A[1-9][0-9]*\z/', $value)
            && (string) (int) $value === $value
        ) {
            return (int) $value;
        }
        throw PublicRenderPolicy::unavailable();
    }
}
