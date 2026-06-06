<?php

defined('ABSPATH') || exit;

use Elemacy\Conditions\Archive\PostType as ArchivePostType;
use Elemacy\Conditions\General\EntireSite;
use Elemacy\Conditions\Mock\AllArchives as MockAllArchives;
use Elemacy\Conditions\Mock\AuthorArchive as MockAuthorArchive;
use Elemacy\Conditions\Mock\Blog as MockBlog;
use Elemacy\Conditions\Mock\ByAuthor as MockByAuthor;
use Elemacy\Conditions\Mock\ChildOf as MockChildOf;
use Elemacy\Conditions\Mock\DateArchive as MockDateArchive;
use Elemacy\Conditions\Mock\FrontPage as MockFrontPage;
use Elemacy\Conditions\Mock\InTaxonomy as MockInTaxonomy;
use Elemacy\Conditions\Mock\NotFound as MockNotFound;
use Elemacy\Conditions\Mock\SearchResults as MockSearchResults;
use Elemacy\Conditions\Mock\SpecificPost as MockSpecificPost;
use Elemacy\Conditions\Mock\TaxonomyArchive as MockTaxonomyArchive;
use Elemacy\Conditions\Singular\PostType as SingularPostType;

/**
 * Conditions free ships, as class-strings or ready-made instances. Mock
 * conditions are non-functional placeholders shown (and locked) in the UI so
 * free users can see what elemacy-pro adds; pro overrides each by name with a
 * real implementation when active.
 *
 * @see \Elemacy\Core\ConditionsBootstrap::register()
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
