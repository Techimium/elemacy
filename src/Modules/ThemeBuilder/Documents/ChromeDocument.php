<?php

namespace Elemacy\Modules\ThemeBuilder\Documents;

defined('ABSPATH') || exit;

use Elementor\Core\DocumentTypes\PageBase;

/**
 * Base for the site-chrome templates — Header and Footer — that render inside
 * another document's page rather than being a page themselves.
 *
 * A header is not a page, so none of PageBase's page-level settings apply to
 * it: it has no page layout to choose, no featured image, no excerpt, no
 * comments, no page title to hide, and no page body to style. Offering them
 * would present settings that silently do nothing. See
 * docs/ELEMENTOR-COMPATIBILITY.md § Documents for the exact Elementor surface
 * this narrowing depends on.
 */
abstract class ChromeDocument extends PageBase
{
    public static function get_properties()
    {
        $properties = parent::get_properties();

        $properties['support_kit']     = true;
        $properties['show_in_library'] = false;
        $properties['admin_tab_group'] = '';

        // Suppresses Elementor's own page-template ("Page Layout") control,
        // which the Page Templates module injects only for a document that
        // declares this. Chrome renders inside a page whose layout the page
        // itself owns.
        $properties['support_page_layout'] = false;

        return $properties;
    }

    /**
     * Scope document settings CSS to this template's own root element, not
     * PageBase's `body.elementor-page-{id}`: a header renders inside another
     * document's page, so that body class is never present and any rule
     * written against it would never apply.
     */
    public function get_css_wrapper_selector()
    {
        return '.elementor-' . $this->get_main_id();
    }

    /**
     * Deliberately not `parent::register_controls()`. PageBase adds the Hide
     * Title switcher, the post fields (excerpt, featured image, menu order,
     * comments) and the Body Style section, none of which affect a header or
     * footer. What does apply is the document's own identity and publication
     * state, which is exactly what Document's own general-settings section
     * registers, plus the public extension point so a third party can still
     * contribute controls to this document.
     */
    protected function register_controls()
    {
        $this->register_document_controls();

        // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Elementor owns this public hook name.
        do_action('elementor/documents/register_controls', $this);
    }
}
