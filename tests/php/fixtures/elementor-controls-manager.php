<?php

/**
 * The Controls_Manager control-type constants the forms setting mapper reads,
 * with Elementor's real values. Kept out of elementor-stubs.php, whose
 * ambient-state check pins exactly which Elementor classes exist; tests/php/forms.php
 * loads it after every other check has run.
 */

namespace Elementor;

if (!class_exists(Controls_Manager::class, false)) {
    class Controls_Manager
    {
        public const TEXT     = 'text';
        public const TEXTAREA = 'textarea';
        public const NUMBER   = 'number';
        public const SWITCHER = 'switcher';
        public const SELECT   = 'select';
    }
}
