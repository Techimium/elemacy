<?php

namespace Elemacy\Loop\Items;

defined('ABSPATH') || exit;

use Elemacy\Integrations\Acf\AcfCursor;
use Elemacy\Integrations\Acf\AcfFieldReader;
use Elemacy\Integrations\Acf\Contracts\FieldReaderInterface;
use Elemacy\Loop\Address\AddressStep;
use Elemacy\Loop\Contracts\AddressableItemInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\NativeTermItemInterface;
use Elemacy\Loop\Support\LoopItemIdentity;
use WP_Term;

/**
 * A loop item backed by a WP_Term. enter/exit set no WordPress global — there
 * is no native "current term in a content loop" comparable to
 * setup_postdata()/$post that any existing dynamic tag or third-party widget
 * could already depend on. They only hide an enclosing ACF row.
 */
class TermLoopItem implements LoopItemInterface, NativeTermItemInterface, AddressableItemInterface
{
    private ?AcfCursor $acf_rows = null;

    protected WP_Term $term;
    protected FieldReaderInterface $acf;

    public function __construct(WP_Term $term, ?FieldReaderInterface $acf = null)
    {
        $this->term = $term;
        $this->acf = $acf ?? new AcfFieldReader();
    }

    public function get_identity(): string
    {
        return LoopItemIdentity::sanitize((string) $this->term->term_id);
    }

    /**
     * Direct access to the wrapped term, for same-plugin code that already
     * knows it's dealing with a term-backed item (e.g. the taxonomy dynamic
     * tags reading LoopContext::current()) — a narrower, more precise seam
     * than routing everything through the generic get_field() key contract.
     *
     * @return WP_Term
     */
    public function get_term(): WP_Term
    {
        return $this->term;
    }

    public function get_address_step(): AddressStep
    {
        return new AddressStep('term', (string) $this->term->term_id);
    }

    /** Hides any enclosing ACF row while entered, so a same-named sub-field never answers for this term. */
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
            case 'term_id':
                return $this->term->term_id;
            case 'name':
                return $this->term->name;
            case 'slug':
                return $this->term->slug;
            case 'description':
                return term_description($this->term->term_id);
            case 'count':
                return $this->term->count;
            case 'url':
                $link = get_term_link($this->term);
                return is_wp_error($link) ? '' : $link;
        }

        // ACF's get_field()/get_field_object() accept a "term_{id}"-shaped
        // post_id for term-level fields — get_field_value()
        // already treats its second parameter as an opaque ACF post-id
        // string, so no change to that shared service is needed. Its
        // ACF-inactive fallback (get_post_meta()) isn't meaningful for a
        // "term_{id}" key, so an unknown key resolves to no value when ACF
        // isn't active — a known, accepted limitation, not a
        // fatal error.
        return $this->acf->get_field_value($key, 'term_' . $this->term->term_id);
    }
}
