<?php

namespace Elemacy\Loop\LoopItems;

defined('ABSPATH') || exit;

use Elemacy\Integrations\Acf\AcfFieldReader;
use Elemacy\Integrations\Acf\Contracts\FieldReaderInterface;
use Elemacy\Loop\Context\WordPressPostScope;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Support\LoopItemIdentity;
use WP_Post;

/**
 * A loop item backed by a real WP_Post — the reference implementation of
 * LoopItemInterface. enter()/exit() acquire/release the same native "current
 * post" context WP_Query::the_post() already sets up, so every existing
 * dynamic tag and any third-party widget in the loop item template keeps
 * resolving correctly with no changes to any of them (design.md D2).
 * Reversibility is delegated to WordPressPostScope (design.md D8): exit()
 * restores exactly the state enter() found — including an enclosing loop
 * item's own post — rather than wp_reset_postdata()'s main-query reset.
 *
 * Consumes the optional FieldReaderInterface contract (design.md D7) rather
 * than importing a Dynamic Tags class directly — Widgets/Loop no longer has
 * a lateral dependency on another module's internals.
 */
class PostLoopItem implements LoopItemInterface
{
    protected WP_Post $post;
    protected FieldReaderInterface $acf;
    private ?WordPressPostScope $scope = null;

    public function __construct(WP_Post $post, ?FieldReaderInterface $acf = null)
    {
        $this->post = $post;
        $this->acf = $acf ?? new AcfFieldReader();
    }

    public function get_identity(): string
    {
        return LoopItemIdentity::sanitize((string) $this->post->ID);
    }

    public function enter(): void
    {
        $this->scope = new WordPressPostScope();
        $this->scope->enter($this->post);
    }

    public function exit(): void
    {
        if (null === $this->scope) {
            return;
        }

        $this->scope->exit();
        $this->scope = null;
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
