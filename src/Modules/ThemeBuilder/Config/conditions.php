<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Conditions\Archive\PostType as ArchivePostType;
use Elemacy\Modules\ThemeBuilder\Conditions\General\EntireSite;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\AllArchives as MockAllArchives;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\AuthorArchive as MockAuthorArchive;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\Blog as MockBlog;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\ByAuthor as MockByAuthor;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\ChildOf as MockChildOf;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\DateArchive as MockDateArchive;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\FrontPage as MockFrontPage;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\InTaxonomy as MockInTaxonomy;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\NotFound as MockNotFound;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\SearchResults as MockSearchResults;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\SpecificPost as MockSpecificPost;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\TaxonomyArchive as MockTaxonomyArchive;
use Elemacy\Modules\ThemeBuilder\Conditions\Singular\PostType as SingularPostType;

/**
 * Conditions free ships, as class-strings or ready-made instances. Mock
 * conditions are non-functional placeholders shown (and locked) in the UI so
 * free users can see what elemacy-pro adds; pro overrides each by name with a
 * real implementation when active.
 *
 * @see \Elemacy\Modules\ThemeBuilder\ThemeBuilder::register_conditions()
 */
return [
    EntireSite::class,
    SingularPostType::class,
    ArchivePostType::class,

    // Mock conditions — overridden by elemacy-pro under the same name when active.
    MockFrontPage::class,
    MockNotFound::class,
    MockSearchResults::class,
    MockBlog::class,
    MockByAuthor::class,
    MockSpecificPost::class,
    MockChildOf::class,
    MockAllArchives::class,
    MockDateArchive::class,
    MockAuthorArchive::class,

    // Per-taxonomy mocks: "In {Tax}" (singular) and "{Tax} Archive".
    ...MockInTaxonomy::make_all(),
    ...MockTaxonomyArchive::make_all(),
];
