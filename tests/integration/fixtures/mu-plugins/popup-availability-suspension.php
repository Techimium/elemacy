<?php

/**
 * A uniquely-named mock rule and mock trigger, registered the same way
 * elemacy-pro overrides Free's own Mock\* rules/triggers by name — except
 * these names are never overridden by anything, so they stay unavailable
 * ("inactive extension") regardless of whether the sibling Pro plugin is
 * active in this environment. Proves D5's suspend-on-unavailable behavior
 * for the "registered mock" reason distinctly from "unknown identifier"
 * (an entirely unregistered type string), which needs no fixture at all.
 */
defined('ELEMACY_INTEGRATION_TOKEN') || exit;

// Literal hook name, not \Elemacy\Core\Hooks::POPUP_RULES_REGISTER_ACTION:
// mu-plugins load before regular plugins register their autoloader, so a
// class-constant reference evaluated at this top level would fatal on every
// request, unlike a callback body (deferred until the hook actually fires).
add_action('elemacy/action/popups/rules/register', static function ($manager): void {
    if (!class_exists('Elemacy_Fixture_Mock_Rule')) {
        class Elemacy_Fixture_Mock_Rule extends \Elemacy\Modules\Popups\Rules\MockRule
        {
            public function get_name(): string
            {
                return 'elemacy_fixture/mock_rule';
            }

            public function get_label(): string
            {
                return 'Fixture Mock Rule';
            }
        }
    }

    $manager->register(new Elemacy_Fixture_Mock_Rule());
});

// Literal hook name; see the rules registration above for why.
add_action('elemacy/action/popups/triggers/register', static function ($manager): void {
    if (!class_exists('Elemacy_Fixture_Mock_Trigger')) {
        class Elemacy_Fixture_Mock_Trigger extends \Elemacy\Modules\Popups\Triggers\MockTrigger
        {
            public function get_name(): string
            {
                return 'elemacy_fixture/mock_trigger';
            }

            public function get_label(): string
            {
                return 'Fixture Mock Trigger';
            }
        }
    }

    $manager->register(new Elemacy_Fixture_Mock_Trigger());
});
