/**
 * Editor preview bridge for previewable documents (Loop Item, Single, Archive,
 * Search).
 *
 * The document's "Apply & Preview" button dispatches `elemacy:preview:apply` on
 * Elementor's editor channel. We fully save the document so the chosen preview
 * context lands in the main post's page settings (the editor's dynamic-tag AJAX
 * reads settings from the main post, not the autosave), then reload the preview
 * iframe — which re-renders the dynamic widgets/tags against that context.
 *
 * Reloading alone is not enough: Elementor caches `render_tags` results in the
 * parent window keyed only by tag name + settings, and that cache survives a
 * preview reload. Without clearing it the tags re-render from the stale value and
 * never re-hit the server (where PreviewManager swaps the context), so we clean
 * the cache before reloading to force a fresh render against the chosen content.
 */
( function () {
	'use strict';

	function reloadPreview() {
		if ( elementor.dynamicTags && elementor.dynamicTags.cleanCache ) {
			elementor.dynamicTags.cleanCache();
		}

		elementor.reloadPreview();
	}

	function applyPreview() {
		if ( typeof $e === 'undefined' ) {
			reloadPreview();
			return;
		}

		// Reload on both resolve and reject: an unchanged document rejects with
		// "Document is not changed", which still means the saved value is current.
		$e.run( 'document/save/default' ).then( reloadPreview, reloadPreview );
	}

	function bind() {
		if ( typeof elementor === 'undefined' || ! elementor.channels || ! elementor.channels.editor ) {
			return;
		}

		elementor.channels.editor.on( 'elemacy:preview:apply', applyPreview );
	}

	if ( window.elementor ) {
		bind();
	} else {
		jQuery( window ).on( 'elementor:init', bind );
	}
}() );
