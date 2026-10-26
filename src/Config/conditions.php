<?php

defined('ABSPATH') || exit;

use Elemacy\Conditions\Archive\PostType as ArchivePostType;
use Elemacy\Conditions\General\EntireSite;
use Elemacy\Conditions\Search\AllResults as SearchAllResults;
use Elemacy\Conditions\Mock\AuthorArchive as MockAuthorArchive;
use Elemacy\Conditions\Mock\Blog as MockBlog;
use Elemacy\Conditions\Mock\ByAuthor as MockByAuthor;
use Elemacy\Conditions\Mock\ChildOf as MockChildOf;
use Elemacy\Conditions\Mock\DateArchive as MockDateArchive;
use Elemacy\Conditions\Mock\FrontPage as MockFrontPage;
use Elemacy\Conditions\Mock\InTaxonomy as MockInTaxonomy;
use Elemacy\Conditions\Mock\NotFound as MockNotFound;
use Elemacy\Conditions\Mock\SearchPostType as MockSearchPostType;
use Elemacy\Conditions\Mock\SpecificPost as MockSpecificPost;
use Elemacy\Conditions\Mock\TaxonomyArchive as MockTaxonomyArchive;
use Elemacy\Conditions\Singular\PostType as SingularPostType;

/**
 * Conditions free ships, as class-strings or ready-made instances. Mock
 * conditions are non-functional placeholders.
 *
 * @see \Elemacy\Core\ConditionsBootstrap
 */
return [
    EntireSite::class,
    SingularPostType::class,
    ArchivePostType::class,
    SearchAllResults::class,

    // Mock conditions — overridden by elemacy-pro under the same name when active.
    MockFrontPage::class,
    MockNotFound::class,
    MockSearchPostType::class,
    MockBlog::class,
    MockByAuthor::class,
    MockSpecificPost::class,
    MockChildOf::class,
    MockDateArchive::class,
    MockAuthorArchive::class,

    // Per-taxonomy mocks: "In {Tax}" (singular) and "{Tax} Archive".
    ...MockInTaxonomy::make_all(),
    ...MockTaxonomyArchive::make_all(),
];
