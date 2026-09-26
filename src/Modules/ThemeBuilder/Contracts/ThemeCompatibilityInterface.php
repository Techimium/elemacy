<?php

namespace Elemacy\Modules\ThemeBuilder\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

interface ThemeCompatibilityInterface
{
    /** Register theme-specific header/footer overrides. */
    public function register(ThemeBuilderManager $manager): void;
}
