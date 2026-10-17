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
    'declaration' => "/(new TypeDefinition\\(\\s*|register\\(\\s*|=\\s*)'" . preg_quote($id, '/') . "'|'" . preg_quote($id, '/') . "'\\s*=>\\s*__\\(/",
];
$returned = static fn(string $id, string $file): array => [
    'id' => $id,
    'file' => $file,
    'declaration' => "/return\\s+'" . preg_quote($id, '/') . "';/",
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
    $returned('elemacy_popup', 'src/Modules/Popups/Documents/PopupDocument.php'),

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
