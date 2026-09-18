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
 * Dynamic_CSS's own constructor sets its data source to $item_identity
 * unless handed a Post_Preview file, which would resolve dynamic tags
 * against the wrong post for this use case. Overriding get_post_id_for_data()
 * is how Elementor Pro's own loop widget (Loop_Dynamic_CSS) fixes the same
 * mismatch — and, confirmed by tracing Post_Local_Cache::get_data(), the
 * only thing that method's return value is used for is loading the
 * *template's* element/control data, never a post lookup keyed by
 * $item_identity. That value only ever reappears as a literal token inside
 * the generated CSS text, rewritten by ClassicCssRenderer::render_dynamic()
 * — so it never needs to be a real WordPress post ID (design.md D4).
 *
 * $item_identity here is always the item's own raw domain identity
 * (LoopItemInterface::get_identity()) — deliberately NOT the render-scoped
 * LoopRenderIdentity string design.md D8 introduces for the actual output
 * selector. This class needs no such distinction of its own: every use of
 * the value it's constructed with — the base Post's `$post_id`, both
 * inherited and via get_post_id_for_data() — either never runs for this
 * subclass (Post_Local_Cache's own load/update/delete_meta() and get_data()
 * overrides bypass every raw-`$post_id`-keyed lookup in the parent chain,
 * traced through Post::get_data()/enqueue(), neither of which this call
 * path reaches) or is scoped by $template_id instead (get_file_handle_id(),
 * via the get_post_id_for_data() override below); the raw value only ever
 * reappears as the literal `.elementor-{item_identity}` token
 * render_dynamic() rewrites. Nothing here is cached beyond one instance's
 * own lifetime (Base::$content, Post_Local_Cache::$meta_cache), and a fresh
 * instance is constructed on every call — so two items sharing the same raw
 * identity (a post and a term with equal numeric IDs, or the same post in
 * two different templates) can never collide through this class; the actual
 * collision this design.md D8 risk names is entirely in what selector the
 * *caller* rewrites the generated CSS to, which LoopItemStyles now builds
 * from LoopRenderIdentity rather than from $item_identity directly.
 */
class LoopDynamicCss extends Dynamic_CSS
{
    protected int $template_id;

    public function __construct(string $item_identity, int $template_id)
    {
        $this->template_id = $template_id;

        parent::__construct($item_identity, Post_CSS::create($template_id));
    }

    protected function get_post_id_for_data()
    {
        return $this->template_id;
    }
}
