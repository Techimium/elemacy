<?php

/**
 * Identifiers stored in sites' databases and saved Elementor data, with the
 * source file that declares each. See docs/CONTRACTS.md § Permanent
 * identifiers. check.php fails when an identifier is no longer declared in
 * its file and no migration names it.
 *
 * 'declaration' narrows the match to the declaring statement where the bare
 * quoted value also appears in that file for unrelated reasons.
 *
 * @return array<int, array{id: string, file: string, declaration?: string}>
 */

$type = static fn(string $id, string $file): array => [
    'id' => $id,
    'file' => $file,
    'declaration' => "/(new TypeDefinition\\(\\s*|register\\(\\s*|=\\s*)'" . preg_quote($id, '/') . "'|'" . preg_quote($id, '/') . "'\\s*=>\\s*\\[?\\s*__\\(/",
];
$returned = static fn(string $id, string $file): array => [
    'id' => $id,
    'file' => $file,
    'declaration' => "/return\\s+'" . preg_quote($id, '/') . "';/",
];

$param = static fn(string $key, string $file): array => [
    'id' => $key,
    'file' => $file,
    'declaration' => "/'key'\\s*=>\\s*'" . preg_quote($key, '/') . "'/",
];
$display_key = static fn(string $id, string $constant): array => [
    'id' => $id,
    'file' => 'src/Modules/Popups/Support/DisplayKeys.php',
    'declaration' => '/const\\s+' . $constant . "\\s*=\\s*'" . preg_quote($id, '/') . "'/",
];
$option = static fn(string $value): array => [
    'id' => $value,
    'file' => 'src/Modules/Popups/Documents/PopupDocument.php',
    'declaration' => "/'" . preg_quote($value, '/') . "'\\s*=>\\s*esc_html__\\(/",
];

return [
    // Module slugs (elemacy_active_modules).
    $returned('theme-builder', 'src/Modules/ThemeBuilder/ThemeBuilder.php'),
    $returned('widgets', 'src/Modules/Widgets/Widgets.php'),
    $returned('custom-css', 'src/Modules/CustomCss/CustomCss.php'),
    $returned('dynamic-tags', 'src/Modules/DynamicTags/DynamicTags.php'),
    $returned('popups', 'src/Modules/Popups/Popups.php'),

    // Template type names (_elemacy_template_type).
    $type('section', 'src/TemplateLibrary/LibraryBootstrap.php'),
    $type('loop', 'src/Modules/Widgets/Widgets.php'),
    $type('header', 'src/Modules/ThemeBuilder/ThemeBuilder.php'),
    $type('footer', 'src/Modules/ThemeBuilder/ThemeBuilder.php'),
    $type('single', 'src/Modules/ThemeBuilder/ThemeBuilder.php'),
    $type('archive', 'src/Modules/ThemeBuilder/ThemeBuilder.php'),
    $type('404', 'src/Modules/ThemeBuilder/ThemeBuilder.php'),
    $type('search', 'src/Modules/ThemeBuilder/ThemeBuilder.php'),
    $type('popup', 'src/Modules/Popups/Support/PopupTypes.php'),
    $type('topbar', 'src/Modules/Popups/Support/PopupTypes.php'),
    $type('banner', 'src/Modules/Popups/Support/PopupTypes.php'),
    $type('floating', 'src/Modules/Popups/Support/PopupTypes.php'),

    // Elementor document type names (_elementor_template_type).
    $returned('elemacy_loop', 'src/Modules/Widgets/Documents/LoopDocument.php'),
    $returned('elemacy_header', 'src/Modules/ThemeBuilder/Documents/HeaderDocument.php'),
    $returned('elemacy_footer', 'src/Modules/ThemeBuilder/Documents/FooterDocument.php'),
    $returned('elemacy_single', 'src/Modules/ThemeBuilder/Documents/SingleDocument.php'),
    $returned('elemacy_archive', 'src/Modules/ThemeBuilder/Documents/ArchiveDocument.php'),
    $returned('elemacy_search', 'src/Modules/ThemeBuilder/Documents/SearchDocument.php'),
    $returned('elemacy_404', 'src/Modules/ThemeBuilder/Documents/NotFoundDocument.php'),
    $returned('elemacy_popup', 'src/Modules/Popups/Documents/PopupDocument.php'),

    // Display condition identifiers (the `type` of every rule in
    // _elemacy_conditions). Free declares the working ones and the
    // placeholders Pro replaces under the same identifier; a rename would
    // orphan saved rules and suspend the templates and popups holding them.
    $returned('general/entire_site', 'src/Conditions/General/EntireSite.php'),
    $returned('singular/post_type', 'src/Conditions/Singular/PostType.php'),
    $returned('archive/post_type', 'src/Conditions/Archive/PostType.php'),
    $returned('search/all', 'src/Conditions/Search/AllResults.php'),
    $returned('archive/all', 'src/Conditions/Mock/AllArchives.php'),
    $returned('archive/author', 'src/Conditions/Mock/AuthorArchive.php'),
    $returned('archive/date', 'src/Conditions/Mock/DateArchive.php'),
    $returned('general/blog', 'src/Conditions/Mock/Blog.php'),
    $returned('general/front_page', 'src/Conditions/Mock/FrontPage.php'),
    $returned('general/not_found', 'src/Conditions/Mock/NotFound.php'),
    $returned('general/search_results', 'src/Conditions/Mock/SearchResults.php'),
    $returned('search/post_type', 'src/Conditions/Mock/SearchPostType.php'),
    $returned('singular/by_author', 'src/Conditions/Mock/ByAuthor.php'),
    $returned('singular/child_of', 'src/Conditions/Mock/ChildOf.php'),
    $returned('singular/specific_post', 'src/Conditions/Mock/SpecificPost.php'),
    // Per-taxonomy families: the stored identifier is this prefix plus a taxonomy name.
    ['id' => 'singular/in_tax/', 'file' => 'src/Conditions/Mock/InTaxonomy.php'],
    ['id' => 'archive/tax/', 'file' => 'src/Conditions/Mock/TaxonomyArchive.php'],

    // Elementor widget names (widgetType in saved _elementor_data).
    $returned('elemacy-site-logo', 'src/Modules/Widgets/Widgets/SiteLogo.php'),
    $returned('elemacy_nav_menu', 'src/Modules/Widgets/Widgets/NavMenu.php'),
    $returned('elemacy-loop-grid', 'src/Modules/Widgets/Widgets/LoopGrid.php'),
    $returned('elemacy-loop-carousel', 'src/Modules/Widgets/Widgets/LoopCarousel.php'),
    $returned('elemacy-form', 'src/Modules/Widgets/Widgets/Form.php'),
    $returned('elemacy-search', 'src/Modules/Widgets/Widgets/Search.php'),
    $returned('elemacy-acf-accordion', 'src/Modules/Widgets/Widgets/Acf/AcfAccordion.php'),
    $returned('elemacy-acf-icon-list', 'src/Modules/Widgets/Widgets/Acf/AcfIconList.php'),
    $returned('elemacy-acf-gallery', 'src/Modules/Widgets/Widgets/Acf/AcfGallery.php'),

    // Loop data-source keys (data_source / preview_data_source in saved widget settings).
    $returned('posts', 'src/Modules/Widgets/DataSources/PostsDataSource.php'),
    $returned('current_query', 'src/Modules/Widgets/DataSources/CurrentQueryDataSource.php'),
    $returned('terms', 'src/Modules/Widgets/DataSources/TaxonomyDataSource.php'),
    $returned('users', 'src/Modules/Widgets/DataSources/UsersDataSource.php'),

    // Dynamic tag names (name="..." in saved [elementor-tag] references).
    $returned('elemacy-acf-color', 'src/Modules/DynamicTags/Tags/Acf/AcfColor.php'),
    $returned('elemacy-acf-date-time', 'src/Modules/DynamicTags/Tags/Acf/AcfDateTime.php'),
    $returned('elemacy-acf-field', 'src/Modules/DynamicTags/Tags/Acf/AcfField.php'),
    $returned('elemacy-acf-gallery', 'src/Modules/DynamicTags/Tags/Acf/AcfGallery.php'),
    $returned('elemacy-acf-image', 'src/Modules/DynamicTags/Tags/Acf/AcfImage.php'),
    $returned('elemacy-acf-number', 'src/Modules/DynamicTags/Tags/Acf/AcfNumber.php'),
    $returned('elemacy-acf-url', 'src/Modules/DynamicTags/Tags/Acf/AcfUrl.php'),
    $returned('elemacy-archive-description', 'src/Modules/DynamicTags/Tags/Archive/ArchiveDescription.php'),
    $returned('elemacy-archive-title', 'src/Modules/DynamicTags/Tags/Archive/ArchiveTitle.php'),
    $returned('elemacy-archive-url', 'src/Modules/DynamicTags/Tags/Archive/ArchiveUrl.php'),
    $returned('elemacy-author-info', 'src/Modules/DynamicTags/Tags/Author/AuthorInfo.php'),
    $returned('elemacy-author-name', 'src/Modules/DynamicTags/Tags/Author/AuthorName.php'),
    $returned('elemacy-author-profile-picture', 'src/Modules/DynamicTags/Tags/Author/AuthorProfilePicture.php'),
    $returned('elemacy-author-url', 'src/Modules/DynamicTags/Tags/Author/AuthorUrl.php'),
    $returned('elemacy-comments-number', 'src/Modules/DynamicTags/Tags/Base/CommentsNumber.php'),
    $returned('elemacy-page-title', 'src/Modules/DynamicTags/Tags/Base/PageTitle.php'),
    $returned('elemacy-post-content', 'src/Modules/DynamicTags/Tags/Base/PostContent.php'),
    $returned('elemacy-post-custom-field', 'src/Modules/DynamicTags/Tags/Base/PostCustomField.php'),
    $returned('elemacy-post-date', 'src/Modules/DynamicTags/Tags/Base/PostDate.php'),
    $returned('elemacy-post-excerpt', 'src/Modules/DynamicTags/Tags/Base/PostExcerpt.php'),
    $returned('elemacy-post-featured-image', 'src/Modules/DynamicTags/Tags/Base/PostFeaturedImage.php'),
    $returned('elemacy-post-terms', 'src/Modules/DynamicTags/Tags/Base/PostTerms.php'),
    $returned('elemacy-post-title', 'src/Modules/DynamicTags/Tags/Base/PostTitle.php'),
    $returned('elemacy-post-url', 'src/Modules/DynamicTags/Tags/Base/PostUrl.php'),
    $returned('elemacy-site-logo', 'src/Modules/DynamicTags/Tags/Base/SiteLogo.php'),
    $returned('elemacy-site-tagline', 'src/Modules/DynamicTags/Tags/Base/SiteTagline.php'),
    $returned('elemacy-site-title', 'src/Modules/DynamicTags/Tags/Base/SiteTitle.php'),
    $returned('elemacy-site-url', 'src/Modules/DynamicTags/Tags/Base/SiteUrl.php'),
    $returned('elemacy-term-count', 'src/Modules/DynamicTags/Tags/Taxonomy/TermCount.php'),
    $returned('elemacy-term-custom-field', 'src/Modules/DynamicTags/Tags/Taxonomy/TermCustomField.php'),
    $returned('elemacy-term-description', 'src/Modules/DynamicTags/Tags/Taxonomy/TermDescription.php'),
    $returned('elemacy-term-name', 'src/Modules/DynamicTags/Tags/Taxonomy/TermName.php'),
    $returned('elemacy-term-url', 'src/Modules/DynamicTags/Tags/Taxonomy/TermUrl.php'),
    $returned('elemacy-user-avatar', 'src/Modules/DynamicTags/Tags/User/UserAvatar.php'),
    $returned('elemacy-user-description', 'src/Modules/DynamicTags/Tags/User/UserDescription.php'),
    $returned('elemacy-user-email', 'src/Modules/DynamicTags/Tags/User/UserEmail.php'),
    $returned('elemacy-user-login', 'src/Modules/DynamicTags/Tags/User/UserLogin.php'),
    $returned('elemacy-user-name', 'src/Modules/DynamicTags/Tags/User/UserName.php'),
    $returned('elemacy-user-post-count', 'src/Modules/DynamicTags/Tags/User/UserPostCount.php'),
    $returned('elemacy-user-url', 'src/Modules/DynamicTags/Tags/User/UserUrl.php'),
    $returned('elemacy-contact-url', 'src/Modules/DynamicTags/Tags/Utility/ContactUrl.php'),
    $returned('elemacy-current-date-time', 'src/Modules/DynamicTags/Tags/Utility/CurrentDateTime.php'),
    $returned('elemacy-request-parameter', 'src/Modules/DynamicTags/Tags/Utility/RequestParameter.php'),
    $returned('elemacy-shortcode', 'src/Modules/DynamicTags/Tags/Utility/Shortcode.php'),
    $returned('elemacy-product-add-to-cart-url', 'src/Modules/DynamicTags/Tags/WooCommerce/ProductAddToCartUrl.php'),
    $returned('elemacy-product-gallery', 'src/Modules/DynamicTags/Tags/WooCommerce/ProductGallery.php'),
    $returned('elemacy-product-price', 'src/Modules/DynamicTags/Tags/WooCommerce/ProductPrice.php'),
    $returned('elemacy-product-rating', 'src/Modules/DynamicTags/Tags/WooCommerce/ProductRating.php'),
    $returned('elemacy-product-short-description', 'src/Modules/DynamicTags/Tags/WooCommerce/ProductShortDescription.php'),
    $returned('elemacy-product-sku', 'src/Modules/DynamicTags/Tags/WooCommerce/ProductSku.php'),
    $returned('elemacy-product-stock', 'src/Modules/DynamicTags/Tags/WooCommerce/ProductStock.php'),

    // Popup trigger and rule type ids (the `type` of every entry in
    // _elemacy_popup_triggers / _elemacy_popup_rules). Free declares the working
    // ones and the placeholders Pro replaces under the same id.
    $returned('page_load', 'src/Modules/Popups/Triggers/PageLoad.php'),
    $returned('click', 'src/Modules/Popups/Triggers/Click.php'),
    $returned('scroll', 'src/Modules/Popups/Triggers/Mock/Scroll.php'),
    $returned('exit_intent', 'src/Modules/Popups/Triggers/Mock/ExitIntent.php'),
    $returned('inactivity', 'src/Modules/Popups/Triggers/Mock/Inactivity.php'),
    $returned('scroll_to_element', 'src/Modules/Popups/Triggers/Mock/ScrollToElement.php'),
    $returned('on_popup_close', 'src/Modules/Popups/Triggers/Mock/OnPopupClose.php'),
    $returned('frequency_cap', 'src/Modules/Popups/Rules/FrequencyCap.php'),
    $returned('logged_in', 'src/Modules/Popups/Rules/LoggedInUsers.php'),
    $returned('once_per', 'src/Modules/Popups/Rules/Mock/OncePer.php'),
    $returned('devices', 'src/Modules/Popups/Rules/Mock/Devices.php'),
    $returned('schedule', 'src/Modules/Popups/Rules/Mock/Schedule.php'),
    $returned('page_views', 'src/Modules/Popups/Rules/Mock/PageViews.php'),
    $returned('sessions', 'src/Modules/Popups/Rules/Mock/Sessions.php'),
    $returned('referrer', 'src/Modules/Popups/Rules/Mock/Referrer.php'),
    $returned('browser', 'src/Modules/Popups/Rules/Mock/Browser.php'),
    $returned('roles', 'src/Modules/Popups/Rules/Mock/Roles.php'),

    // Their parameter keys (the keys of `params` in the same entries).
    $param('delay', 'src/Modules/Popups/Triggers/PageLoad.php'),
    $param('selector', 'src/Modules/Popups/Triggers/Click.php'),
    $param('percentage', 'src/Modules/Popups/Triggers/Mock/Scroll.php'),
    $param('delay', 'src/Modules/Popups/Triggers/Mock/Inactivity.php'),
    $param('selector', 'src/Modules/Popups/Triggers/Mock/ScrollToElement.php'),
    $param('popup_id', 'src/Modules/Popups/Triggers/Mock/OnPopupClose.php'),
    $param('max', 'src/Modules/Popups/Rules/FrequencyCap.php'),
    $param('period', 'src/Modules/Popups/Rules/Mock/OncePer.php'),
    $param('allow', 'src/Modules/Popups/Rules/Mock/Devices.php'),
    $param('start', 'src/Modules/Popups/Rules/Mock/Schedule.php'),
    $param('end', 'src/Modules/Popups/Rules/Mock/Schedule.php'),
    $param('count', 'src/Modules/Popups/Rules/Mock/PageViews.php'),
    $param('count', 'src/Modules/Popups/Rules/Mock/Sessions.php'),
    $param('url', 'src/Modules/Popups/Rules/Mock/Referrer.php'),
    $param('browsers', 'src/Modules/Popups/Rules/Mock/Browser.php'),
    $param('roles', 'src/Modules/Popups/Rules/Mock/Roles.php'),

    // Popup document settings (Elementor stores these in the popup's page
    // settings, without the defaults of controls an author never touched).
    $display_key('elemacy_position', 'POSITION'),
    $display_key('elemacy_sticky', 'STICKY'),
    $display_key('elemacy_width', 'WIDTH'),
    $display_key('elemacy_z_index', 'Z_INDEX'),
    $display_key('elemacy_offset_x', 'OFFSET_X'),
    $display_key('elemacy_offset_y', 'OFFSET_Y'),
    $display_key('elemacy_overlay_color', 'OVERLAY_COLOR'),
    $display_key('elemacy_overlay_opacity', 'OVERLAY_OPACITY'),
    $display_key('elemacy_overlay_blur', 'OVERLAY_BLUR'),
    $display_key('elemacy_animation_in', 'ANIMATION_IN'),
    $display_key('elemacy_animation_out', 'ANIMATION_OUT'),
    $display_key('elemacy_close_button', 'CLOSE_BUTTON'),
    $display_key('elemacy_auto_close', 'AUTO_CLOSE'),
    $display_key('elemacy_close_on_overlay', 'CLOSE_ON_OVERLAY'),
    $display_key('elemacy_close_on_esc', 'CLOSE_ON_ESC'),
    $display_key('elemacy_prevent_scroll', 'PREVENT_SCROLL'),
    // Close button style controls, declared by name in PopupDocument.
    ['id' => 'elemacy_close_size', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_font', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_offset', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_radius', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_border', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_box_shadow', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_color', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_bg', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_color_hover', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_bg_hover', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_border_color_hover', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_color_active', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_bg_active', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],
    ['id' => 'elemacy_close_border_color_active', 'file' => 'src/Modules/Popups/Documents/PopupDocument.php'],

    // Option values of those settings.
    $option('center'), $option('top'), $option('bottom'), $option('top-left'), $option('top-right'),
    $option('bottom-left'), $option('bottom-right'), $option('custom'),
    $option('none'), $option('fade'), $option('slide-down'), $option('slide-up'),

    // Browser storage keys the popup runtime writes in visitors' browsers.
    // Free's engine builds each popup's key from this prefix (the popup id
    // follows). Pro's visit-counter keys (elemacy_pageviews, elemacy_sessions,
    // elemacy_session_counted) are pinned by Pro's own tests/php/run.php.
    ['id' => 'elemacy_popup_', 'file' => 'src/Modules/Popups/Resources/PopupConfigResource.php'],

    // Stored post type, meta and option keys.
    ['id' => 'elemacy_library', 'file' => 'src/TemplateLibrary/LibraryPostType.php'],
    ['id' => '_elemacy_template_type', 'file' => 'src/TemplateLibrary/Constants/MetaKeys.php'],
    ['id' => '_elemacy_conditions', 'file' => 'src/Conditions/ConditionRepository.php'],
    ['id' => '_elemacy_popup_triggers', 'file' => 'src/Modules/Popups/Constants/MetaKeys.php'],
    ['id' => '_elemacy_popup_rules', 'file' => 'src/Modules/Popups/Constants/MetaKeys.php'],
    ['id' => 'elemacy_active_modules', 'file' => 'src/Core/Constants/OptionKeys.php'],
    ['id' => 'elemacy_db_version', 'file' => 'src/Core/Constants/OptionKeys.php'],
    ['id' => 'elemacy_schema_version', 'file' => 'src/Core/Constants/OptionKeys.php'],
    ['id' => 'elemacy_library_index', 'file' => 'src/TemplateLibrary/ResolverCache.php'],
    // Stored as Utils::with_prefix('widgets'), i.e. elemacy_widgets.
    ['id' => 'elemacy_widgets', 'file' => 'src/Modules/Widgets/Services/WidgetService.php', 'declaration' => "/with_prefix\\('widgets'\\)/"],
];
