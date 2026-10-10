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
