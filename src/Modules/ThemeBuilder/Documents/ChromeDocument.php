<?php

namespace Elemacy\Modules\ThemeBuilder\Documents;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;
use Elementor\Controls_Manager;
use Elementor\Core\DocumentTypes\PageBase;
use Elementor\Utils;

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
    /** Document setting holding the element the template renders in. */
    public const HTML_TAG = 'elemacy_html_tag';

    /** Container elements a header or footer may render in. */
    private const HTML_TAGS = ['div', 'header', 'footer', 'main', 'section', 'aside', 'nav', 'article'];

    /**
     * The side of the page this chrome fills, 'header' or 'footer' — also the
     * landmark element it renders in until an author picks another.
     */
    abstract public static function get_side(): string;

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
        $this->register_html_tag_control();

        // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Elementor owns this public hook name.
        do_action('elementor/documents/register_controls', $this);
    }

    private function register_html_tag_control(): void
    {
        $this->start_injection([
            'of' => 'post_status',
            'fallback' => ['of' => 'post_title'],
        ]);

        $this->add_control(self::HTML_TAG, [
            'label'   => esc_html__('HTML Tag', 'elemacy'),
            'type'    => Controls_Manager::SELECT,
            'default' => static::get_side(),
            'options' => array_combine(self::HTML_TAGS, self::HTML_TAGS),
        ]);

        $this->end_injection();
    }

    /**
     * The element this template renders in: the author's choice, else its
     * landmark default. Where the active theme keeps its own header or
     * footer element around the template, a landmark tag becomes `div`, as
     * a header nested in a header is invalid and announced twice.
     */
    public function get_html_tag(): string
    {
        $tag = (string) $this->get_settings_for_display(self::HTML_TAG);
        $tag = in_array($tag, self::HTML_TAGS, true) ? $tag : static::get_side();

        if (in_array($tag, ['header', 'footer'], true) && ThemeBuilderManager::instance()->keeps_theme_landmark(static::get_side())) {
            return 'div';
        }

        return $tag;
    }

    /**
     * Document::print_elements_with_wrapper() with the chosen element in place
     * of its fixed `div`, so a header is a banner landmark and a footer a
     * contentinfo one. Used by the front end and the editor alike.
     *
     * @param array|null $elements_data
     */
    public function print_elements_with_wrapper($elements_data = null)
    {
        if (!$elements_data) {
            $elements_data = $this->get_elements_data();
        }

        $tag = Utils::validate_html_tag($this->get_html_tag());
        ?>
        <<?php Utils::print_validated_html_tag($tag); ?> <?php Utils::print_html_attributes($this->get_container_attributes()); ?>>
            <?php $this->print_elements($elements_data); ?>
        </<?php Utils::print_validated_html_tag($tag); ?>>
        <?php
    }
}
