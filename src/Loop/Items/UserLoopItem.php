<?php

namespace Elemacy\Loop\Items;

defined('ABSPATH') || exit;

use Elemacy\Integrations\Acf\AcfCursor;
use Elemacy\Loop\Address\AddressStep;
use Elemacy\Loop\Contracts\AddressableItemInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\NativeUserItemInterface;
use Elemacy\Loop\Support\LoopItemIdentity;
use WP_User;

/**
 * A loop item backed by a WP_User. enter/exit set no WordPress global (they
 * only hide an enclosing ACF row) — there is no native
 * "current user in a content loop" WordPress global comparable to
 * setup_postdata()/$post that any existing dynamic tag or third-party widget
 * could already depend on, and wp_set_current_user() is explicitly ruled out
 * here since it would change current_user_can() results for the rest of the
 * request.
 */
class UserLoopItem implements LoopItemInterface, NativeUserItemInterface, AddressableItemInterface
{
    private ?AcfCursor $acf_rows = null;

    protected WP_User $user;

    public function __construct(WP_User $user)
    {
        $this->user = $user;
    }

    public function get_identity(): string
    {
        return LoopItemIdentity::sanitize((string) $this->user->ID);
    }

    /**
     * Direct access to the wrapped user, for same-plugin code that already
     * knows it's dealing with a user-backed item (e.g. the user dynamic
     * tags reading LoopContext::current()) — a narrower, more precise seam
     * than routing everything through the generic get_field() key contract.
     *
     * @return WP_User
     */
    public function get_user(): WP_User
    {
        return $this->user;
    }

    public function get_address_step(): AddressStep
    {
        return new AddressStep('user', (string) $this->user->ID);
    }

    /** Hides any enclosing ACF row while entered, so a same-named sub-field never answers for this user. */
    public function enter(): void
    {
        $this->acf_rows()->isolate();
    }

    public function exit(): void
    {
        $this->acf_rows()->exit();
    }

    private function acf_rows(): AcfCursor
    {
        if (null === $this->acf_rows) {
            $this->acf_rows = new AcfCursor();
        }

        return $this->acf_rows;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get_field(string $key)
    {
        switch ($key) {
            case 'display_name':
                return $this->user->display_name;
            case 'description':
                return get_user_meta($this->user->ID, 'description', true);
            case 'avatar':
                return get_avatar_url($this->user->ID);
            case 'url':
                return get_author_posts_url($this->user->ID);
            case 'user_login':
                return $this->user->user_login;
            case 'user_email':
                return $this->user->user_email;
            case 'post_count':
                return (int) count_user_posts($this->user->ID);
        }

        return null;
    }
}
