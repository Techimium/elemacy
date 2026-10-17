<?php

namespace Elemacy\Modules\ThemeBuilder\Services\Placement;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Services\LocationRegistry;
use Elemacy\Loop\Contracts\PlacementResolverInterface;
use Elemacy\TemplateLibrary\TemplateResolver;

/** Proves a Theme Builder document is the one the server actually resolved for this request. */
final class ThemeBuilderPlacementResolver implements PlacementResolverInterface
{
    public function get_key(): string
    {
        return 'theme_builder';
    }

    public function resolve(int $owner_id): ?array
    {
        $resolver = TemplateResolver::instance();

        if ($resolver->resolve('header') === $owner_id) {
            return ['location' => 'header'];
        }
        if ($resolver->resolve('footer') === $owner_id) {
            return ['location' => 'footer'];
        }

        $type = LocationRegistry::instance()->current_type();
        if (null !== $type && $resolver->resolve($type) === $owner_id) {
            return ['location' => $type];
        }

        return null;
    }
}
