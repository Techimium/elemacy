<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\Contracts\ConditionRegistrarInterface;
use Elemacy\Core\Admin\AdminMenu;
use Elemacy\Core\Admin\SubMenuDTO;
use Elemacy\Core\Container;
use Elemacy\Core\Contracts\AdminMenuRegistrarInterface;
use Elemacy\Core\Contracts\ContainerInterface;
use Elemacy\Core\Contracts\ModuleRegistrarInterface;
use Elemacy\Core\Contracts\ModuleStatusInterface;
use Elemacy\Core\Contracts\ProviderRegistrarInterface;
use Elemacy\Core\Elemacy;
use Elemacy\Elementor\Contracts\QueryResolverRegistrarInterface;
use Elemacy\Elementor\Contracts\TemplateAssetsRegistrarInterface;
use Elemacy\Elementor\Controls\Query\QueryResolverRegistry;
use Elemacy\Elementor\Rendering\TemplateAssetsRegistrar;
use Elemacy\Forms\Contracts\FieldTypeRegistrarInterface;
use Elemacy\Forms\Contracts\FormActionRegistrarInterface;
use Elemacy\Forms\Contracts\FormSourceRegistrarInterface;
use Elemacy\Forms\FieldTypeRegistry;
use Elemacy\Forms\FormActionRegistry;
use Elemacy\Forms\FormSourceRegistry;
use Elemacy\Integrations\Acf\AcfCursor;
use Elemacy\Integrations\Acf\Contracts\AcfRowCursorInterface;
use Elemacy\Loop\Address\AddressStep;
use Elemacy\Loop\Address\AddressStepResolverRegistry;
use Elemacy\Loop\Contracts\AddressStepResolverInterface;
use Elemacy\Loop\Contracts\AddressStepResolverRegistrarInterface;
use Elemacy\Loop\Contracts\LoopDataSourceRegistrarInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\PlacementResolverInterface;
use Elemacy\Loop\Contracts\PlacementResolverRegistrarInterface;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\Modules\Popups\Contracts\PopupEligibilityInterface;
use Elemacy\Modules\Popups\Contracts\PopupRepositoryInterface;
use Elemacy\Modules\Popups\Contracts\RuleRegistrarInterface;
use Elemacy\Modules\Popups\Contracts\TriggerRegistrarInterface;
use Elemacy\Modules\Popups\Services\PopupDisplayResolver;
use Elemacy\Modules\Popups\Services\PopupService;
use Elemacy\Modules\Popups\Services\RuleManager;
use Elemacy\Modules\Popups\Services\TriggerManager;
use Elemacy\Modules\ThemeBuilder\Compatibility\CompatibilityManager;
use Elemacy\Modules\ThemeBuilder\Contracts\ThemeChromeInterface;
use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityRegistrarInterface;
use Elemacy\Modules\ThemeBuilder\Contracts\ThemeLocationRegistrarInterface;
use Elemacy\Modules\ThemeBuilder\Services\LocationRegistry;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;
use Elemacy\TemplateLibrary\Contracts\LibraryTypeRegistrarInterface;
use Elemacy\TemplateLibrary\TypeRegistry;
use Exception;
use ReflectionProperty;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Every published service contract (docs/CONTRACTS.md § PHP extension
 * contract) resolves, on a fully booted request, to the very instance Free
 * itself uses, so whatever an add-on registers or reads through a contract is
 * what Free sees. A binding that built a second copy would pass a type check
 * and fail here.
 */
final class PhpExtensionContractTest extends IntegrationTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = Container::instance();
        $modules = Elemacy::get_instance()->get_module_manager();

        foreach (['popups', 'theme-builder', 'widgets'] as $module) {
            self::assertTrue($modules->is_active($module), "The $module module must be active: its contracts are bound by it.");
        }
    }

    /** @return array<string, array{0: class-string, 1: callable(): object}> */
    public function liveInstances(): array
    {
        $shared = static fn (string $class): callable => static fn () => Container::instance()->singleton($class);

        return [
            'container' => [ContainerInterface::class, static fn () => Container::instance()],
            'providers' => [ProviderRegistrarInterface::class, static fn () => Elemacy::get_instance()],
            'module registrar' => [ModuleRegistrarInterface::class, static fn () => Elemacy::get_instance()->get_module_manager()],
            'module status' => [ModuleStatusInterface::class, static fn () => Elemacy::get_instance()->get_module_manager()],
            'conditions' => [ConditionRegistrarInterface::class, static fn () => ConditionManager::instance()],
            'popup rules' => [RuleRegistrarInterface::class, static fn () => RuleManager::instance()],
            'popup triggers' => [TriggerRegistrarInterface::class, static fn () => TriggerManager::instance()],
            'popup repository' => [PopupRepositoryInterface::class, $shared(PopupService::class)],
            'popup eligibility' => [PopupEligibilityInterface::class, static fn () => PopupDisplayResolver::instance()],
            'loop data sources' => [LoopDataSourceRegistrarInterface::class, static fn () => LoopDataSourceRegistry::instance()],
            'address step resolvers' => [AddressStepResolverRegistrarInterface::class, static fn () => AddressStepResolverRegistry::instance()],
            'placement resolvers' => [PlacementResolverRegistrarInterface::class, static fn () => PlacementResolverRegistry::instance()],
            'library types' => [LibraryTypeRegistrarInterface::class, static fn () => TypeRegistry::instance()],
            'theme locations' => [ThemeLocationRegistrarInterface::class, static fn () => LocationRegistry::instance()],
            'theme compatibility' => [ThemeCompatibilityRegistrarInterface::class, static fn () => CompatibilityManager::instance()],
            'theme chrome' => [ThemeChromeInterface::class, static fn () => ThemeBuilderManager::instance()],
            'query resolvers' => [QueryResolverRegistrarInterface::class, $shared(QueryResolverRegistry::class)],
            'template assets' => [TemplateAssetsRegistrarInterface::class, $shared(TemplateAssetsRegistrar::class)],
            'form field types' => [FieldTypeRegistrarInterface::class, $shared(FieldTypeRegistry::class)],
            'form actions' => [FormActionRegistrarInterface::class, $shared(FormActionRegistry::class)],
            'form sources' => [FormSourceRegistrarInterface::class, $shared(FormSourceRegistry::class)],
        ];
    }

    /**
     * @dataProvider liveInstances
     * @param class-string $contract
     */
    public function testContractResolvesToTheInstanceFreeUses(string $contract, callable $live): void
    {
        self::assertTrue($this->container->bound($contract), "$contract has no binding.");
        self::assertSame($live(), $this->container->make($contract), "$contract resolved to a different instance than the one Free uses.");
    }

    public function testTemplateAssetsContractIsTheHookedInstance(): void
    {
        $registrar = $this->container->make(TemplateAssetsRegistrarInterface::class);

        self::assertNotFalse(
            has_filter('elementor/document/related_posts', [$registrar, 'get_related_posts']),
            'The contract must resolve to the registrar whose hooks are attached, or registrations through it are never printed.'
        );
    }

    public function testAdminMenuContractAddsToFreesMenu(): void
    {
        $submenus = new ReflectionProperty(AdminMenu::class, 'submenus');
        $submenus->setAccessible(true);
        $before = $submenus->getValue();

        $page = new SubMenuDTO();
        $page->menu_slug = 'php-extension-contract-probe';

        try {
            $this->container->make(AdminMenuRegistrarInterface::class)->add_submenu($page);
            self::assertContains($page, $submenus->getValue(), 'A page added through the contract must be in Free\'s own submenu list.');
        } finally {
            $submenus->setValue(null, $before);
        }
    }

    public function testAcfRowCursorIsFreshPerResolve(): void
    {
        $first = $this->container->make(AcfRowCursorInterface::class);
        $second = $this->container->make(AcfRowCursorInterface::class);

        self::assertInstanceOf(AcfCursor::class, $first);
        self::assertNotSame($first, $second, 'Each row item needs its own cursor: one keeps its own snapshot stack.');
    }

    public function testRegistrationThroughAContractIsSeenByFree(): void
    {
        $placement = new class implements PlacementResolverInterface {
            public function get_key(): string
            {
                return 'php-extension-contract-probe';
            }

            public function resolve(int $owner_id): ?array
            {
                return null;
            }
        };
        $step = new class implements AddressStepResolverInterface {
            public function get_kind(): string
            {
                return 'php-extension-contract-probe';
            }

            public function resolve(AddressStep $step, ?LoopItemInterface $parent): ?LoopItemInterface
            {
                return null;
            }

            public function fingerprint(LoopItemInterface $item): ?string
            {
                return null;
            }
        };

        $placements = new ReflectionProperty(PlacementResolverRegistry::class, 'resolvers');
        $placements->setAccessible(true);
        $placements_before = $placements->getValue(PlacementResolverRegistry::instance());
        $steps = new ReflectionProperty(AddressStepResolverRegistry::class, 'resolvers');
        $steps->setAccessible(true);
        $steps_before = $steps->getValue(AddressStepResolverRegistry::instance());

        try {
            $this->container->make(PlacementResolverRegistrarInterface::class)->register($placement);
            $this->container->make(AddressStepResolverRegistrarInterface::class)->register($step);

            self::assertSame($placement, PlacementResolverRegistry::instance()->all()['php-extension-contract-probe'] ?? null);
            self::assertSame($step, AddressStepResolverRegistry::instance()->get('php-extension-contract-probe'));
        } finally {
            $placements->setValue(PlacementResolverRegistry::instance(), $placements_before);
            $steps->setValue(AddressStepResolverRegistry::instance(), $steps_before);
        }
    }

    public function testAnUnboundContractFailsWithItsName(): void
    {
        try {
            $this->container->make(LoopItemInterface::class);
        } catch (Exception $error) {
            self::assertStringContainsString(LoopItemInterface::class, $error->getMessage());

            return;
        }

        self::fail('Resolving an unbound contract must fail, never build a substitute.');
    }
}
