<?php

namespace Elemacy\Forms\Services;

use Elemacy\Forms\FormUnavailableException;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TypeRegistry;

defined('ABSPATH') || exit;

/**
 * Whether the current visitor may submit a form stored in a post. Guests need
 * a published, unlocked owner; a private owner keeps WordPress's own
 * read_post check. A library template (popup, header, loop item, ...) only
 * needs to be published and of a registered type: where it is shown is the
 * template's business, and its form can only reach its own authored
 * recipients either way.
 */
final class OwnerAccessPolicy
{
    private TypeRegistry $types;

    public function __construct(TypeRegistry $types)
    {
        $this->types = $types;
    }

    /**
     * @throws FormUnavailableException When the visitor may not use the owner.
     */
    public function assert_accessible(int $owner): void
    {
        $post = $owner > 0 ? get_post($owner) : null;

        if (
            !$post || post_password_required($post)
            || ('publish' !== $post->post_status && !('private' === $post->post_status && current_user_can('read_post', $owner)))
        ) {
            throw FormUnavailableException::create();
        }

        if (LibraryPostType::POST_TYPE === $post->post_type) {
            if (!$this->types->get((string) get_post_meta($owner, MetaKeys::TEMPLATE_TYPE, true))) {
                throw FormUnavailableException::create();
            }

            return;
        }

        if ('publish' === $post->post_status && !is_post_publicly_viewable($post)) {
            throw FormUnavailableException::create();
        }
    }
}
