<?php

namespace Elemacy\Modules\FormBuilder;

defined('ABSPATH') || exit;

use Elemacy\Core\Container;
use Elemacy\Core\Contracts\ContainerInterface;
use Elemacy\Core\Hooks;
use Elemacy\Core\Module;
use Elemacy\Modules\FormBuilder\Contracts\FieldTypeRegistrarInterface;
use Elemacy\Modules\FormBuilder\Contracts\FormActionRegistrarInterface;
use Elemacy\Modules\FormBuilder\Contracts\FormSourceRegistrarInterface;
use Elemacy\Modules\FormBuilder\Elementor\ElementorFormSource;
use Elemacy\Modules\FormBuilder\Services\FieldTypeRegistry;
use Elemacy\Modules\FormBuilder\Services\FormActionRegistry;
use Elemacy\Modules\FormBuilder\Services\FormsBootstrap;
use Elemacy\Modules\FormBuilder\Services\FormSourceRegistry;
use Elemacy\Support\Utils;

/**
 * Form Builder: the forms engine (field types, actions, sources, the
 * submission pipeline and the browser runtime), the Form widget and its
 * Elementor integration. The engine stays builder-neutral: only Widgets/ and
 * Elementor/ in this module reference Elementor. While the module is off, no
 * form registry is built, no form registration hook fires, the submission
 * endpoint is not registered and the form registrar contracts do not resolve.
 *
 * @internal
 */
class FormBuilder extends Module
{
    public function get_name(): string
    {
        return 'form-builder';
    }

    public function is_default_active(): bool
    {
        return true;
    }

    public function get_title(): string
    {
        return __('Form Builder', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'rows-3';
    }

    public function get_description(): string
    {
        return __('Build forms in Elementor that validate, send email and work inside popups and loops.', 'elemacy');
    }

    public function register(ContainerInterface $container): void
    {
        // Each registry loads on its first lookup: a render, edit or submission.
        $container->bind(FieldTypeRegistry::class, static fn (Container $container) => FieldTypeRegistry::from_config($container));
        $container->bind(FormActionRegistry::class, static fn (Container $container) => FormActionRegistry::from_config($container));
        $container->bind(FormSourceRegistry::class, static fn () => FormSourceRegistry::from_hook());

        // Published contracts (docs/CONTRACTS.md § PHP extension contract),
        // bound only while this module is active.
        $container->bind(FieldTypeRegistrarInterface::class, static fn (Container $container) => $container->singleton(FieldTypeRegistry::class));
        $container->bind(FormActionRegistrarInterface::class, static fn (Container $container) => $container->singleton(FormActionRegistry::class));
        $container->bind(FormSourceRegistrarInterface::class, static fn (Container $container) => $container->singleton(FormSourceRegistry::class));
    }

    public function boot(): void
    {
        (new FormsBootstrap())->register_hooks();

        add_action(Hooks::FORM_SOURCES_REGISTER_ACTION, static function (FormSourceRegistrarInterface $registry): void {
            $registry->register(new ElementorFormSource());
        });
    }

    public function get_widgets(): array
    {
        return require Utils::get_plugin_path('src/Modules/FormBuilder/Config/widgets.php');
    }
}
