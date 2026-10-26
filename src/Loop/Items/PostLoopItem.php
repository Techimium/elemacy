<?php

namespace Elemacy\Loop\Items;

defined('ABSPATH') || exit;

use Elemacy\Integrations\Acf\AcfCursor;
use Elemacy\Integrations\Acf\AcfFieldReader;
use Elemacy\Integrations\Acf\Contracts\FieldReaderInterface;
use Elemacy\Loop\Address\AddressStep;
use Elemacy\Loop\Contracts\AddressableItemInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\NativePostItemInterface;
use Elemacy\Loop\Support\LoopItemIdentity;
use WP_Post;

/**
 * A loop item backed by a real WP_Post — the reference implementation of
 * LoopItemInterface. enter/exit acquire/release the same native "current post"
 * context WP_Query::the_post() already sets up, so every existing dynamic tag
 * and any third-party widget in the loop item template keeps resolving
 * correctly with no changes to any of them. While entered, any enclosing ACF
 * row is hidden, so the post's own fields never resolve to a same-named
 * sub-field of that row.
 */
class PostLoopItem implements LoopItemInterface, NativePostItemInterface, AddressableItemInterface
{
    protected WP_Post $post;
    protected FieldReaderInterface $acf;

    /** @var WordPressPostScope[] One per active enter(), so an ancestor read can enter an item that is already entered. */
    private array $scopes = [];

    private AcfCursor $acf_rows;

    public function __construct(WP_Post $post, ?FieldReaderInterface $acf = null)
    {
        $this->post = $post;
        $this->acf = $acf ?? new AcfFieldReader();
        $this->acf_rows = new AcfCursor();
    }

    public function get_identity(): string
    {
        return LoopItemIdentity::sanitize((string) $this->post->ID);
    }

    public function get_post(): WP_Post
    {
        return $this->post;
    }

    public function get_address_step(): AddressStep
    {
        return new AddressStep('post', (string) $this->post->ID);
    }

    public function enter(): void
    {
        $this->acf_rows->isolate();
        $scope = new WordPressPostScope();
        $scope->enter($this->post);
        $this->scopes[] = $scope;
    }

    public function exit(): void
    {
        $scope = array_pop($this->scopes);

        if (null !== $scope) {
            $scope->exit();
            $this->acf_rows->exit();
        }
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get_field(string $key)
    {
        switch ($key) {
            case 'ID':
                return $this->post->ID;
            case 'title':
                return get_the_title($this->post);
            case 'content':
                return apply_filters('the_content', $this->post->post_content);
            case 'excerpt':
                return get_the_excerpt($this->post);
            case 'date':
                return get_the_date('', $this->post);
            case 'url':
                return get_permalink($this->post);
        }

        // AcfFieldReader::get_field_value() already falls back to
        // get_post_meta() when ACF isn't active or the key isn't an ACF
        // field, so this covers both ACF fields and plain post meta in one call.
        return $this->acf->get_field_value($key, $this->post->ID);
    }
}
