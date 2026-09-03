<?php

namespace Elemacy\Core\Rendering\Support;

defined('ABSPATH') || exit;

use Elementor\Core\DynamicTags\Dynamic_CSS;
use Elementor\Core\Files\CSS\Post as Post_CSS;

/**
 * Renders a template's classic Elementor CSS as it should resolve for a
 * different "current" post — the escape hatch Elementor's classic CSS
 * pipeline already uses to bypass its cached file whenever a control uses a
 * dynamic tag, borrowed here so each rendered loop item gets its own pass.
 *
 * Dynamic_CSS's own constructor sets its data source to $item_post_id unless
 * handed a Post_Preview file, which would resolve dynamic tags against the
 * wrong post for this use case. Overriding get_post_id_for_data() is how
 * Elementor Pro's own loop widget (Loop_Dynamic_CSS) fixes the same mismatch.
 */
class LoopDynamicCss extends Dynamic_CSS
{
    protected int $template_id;

    public function __construct(int $item_post_id, int $template_id)
    {
        $this->template_id = $template_id;

        parent::__construct($item_post_id, Post_CSS::create($template_id));
    }

    protected function get_post_id_for_data()
    {
        return $this->template_id;
    }
}
