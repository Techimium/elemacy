<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Conditions\Archive\PostType as ArchivePostType;
use Elemacy\Modules\ThemeBuilder\Conditions\General\EntireSite;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\FrontPage as MockFrontPage;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\NotFound as MockNotFound;
use Elemacy\Modules\ThemeBuilder\Conditions\Mock\SearchResults as MockSearchResults;
use Elemacy\Modules\ThemeBuilder\Conditions\Singular\PostType as SingularPostType;

return [
    EntireSite::class,
    SingularPostType::class,
    ArchivePostType::class,

    // Mock conditions — overridden by elemacy-pro under the same name when active.
    MockFrontPage::class,
    MockNotFound::class,
    MockSearchResults::class,
];
