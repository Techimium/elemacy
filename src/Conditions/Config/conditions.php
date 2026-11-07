<?php

defined('ABSPATH') || exit;

use Elemacy\Conditions\Archive\PostType as ArchivePostType;
use Elemacy\Conditions\General\EntireSite;
use Elemacy\Conditions\Search\AllResults as SearchAllResults;
use Elemacy\Conditions\Placeholders\AuthorArchive as PlaceholderAuthorArchive;
use Elemacy\Conditions\Placeholders\Blog as PlaceholderBlog;
use Elemacy\Conditions\Placeholders\ByAuthor as PlaceholderByAuthor;
use Elemacy\Conditions\Placeholders\ChildOf as PlaceholderChildOf;
use Elemacy\Conditions\Placeholders\DateArchive as PlaceholderDateArchive;
use Elemacy\Conditions\Placeholders\FrontPage as PlaceholderFrontPage;
use Elemacy\Conditions\Placeholders\InTaxonomy as PlaceholderInTaxonomy;
use Elemacy\Conditions\Placeholders\NotFound as PlaceholderNotFound;
use Elemacy\Conditions\Placeholders\SearchPostType as PlaceholderSearchPostType;
use Elemacy\Conditions\Placeholders\SpecificPost as PlaceholderSpecificPost;
use Elemacy\Conditions\Placeholders\TaxonomyArchive as PlaceholderTaxonomyArchive;
use Elemacy\Conditions\Singular\PostType as SingularPostType;

/**
 * Conditions free ships, as class-strings or ready-made instances. Placeholder
 * conditions are non-functional placeholders.
 *
 * @see \Elemacy\Core\ConditionsBootstrap
 */
return [
    EntireSite::class,
    SingularPostType::class,
    ArchivePostType::class,
    SearchAllResults::class,

    // Placeholder conditions — overridden by elemacy-pro under the same name when active.
    PlaceholderFrontPage::class,
    PlaceholderNotFound::class,
    PlaceholderSearchPostType::class,
    PlaceholderBlog::class,
    PlaceholderByAuthor::class,
    PlaceholderSpecificPost::class,
    PlaceholderChildOf::class,
    PlaceholderDateArchive::class,
    PlaceholderAuthorArchive::class,

    // Per-taxonomy placeholders: "In {Tax}" (singular) and "{Tax} Archive".
    ...PlaceholderInTaxonomy::make_all(),
    ...PlaceholderTaxonomyArchive::make_all(),
];
