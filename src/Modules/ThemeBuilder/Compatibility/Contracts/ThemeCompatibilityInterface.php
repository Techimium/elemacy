<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

interface ThemeCompatibilityInterface
{
    /**
     * Register theme-specific header/footer overrides.
     *
     * Implementations should only register hooks when a corresponding template exists.
     */
    public function register(ThemeBuilderManager $manager): void;
}

