<?php

/**
 * Executes the pure-logic unit tests for release-critical server code:
 * condition evaluation semantics, the migration runner, and the request
 * sanitizer. Run with:  php tests/php/run.php
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/dynamic-tag-stubs.php';

use Elemacy\Conditions\BaseCondition;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\ConditionEvaluator;
use Elemacy\Conditions\ConditionsBootstrap;
use Elemacy\Conditions\MockCondition;
use Elemacy\Conditions\Specificity;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Core\Admin\AdminScripts;
use Elemacy\Core\Capabilities\FeatureAvailability;
use Elemacy\Core\Capabilities\RegistryIdentifierGuard;
use Elemacy\Core\CompatibilityChecker;
use Elemacy\Core\Container;
use Elemacy\Core\Contracts\ServiceProviderInterface;
use Elemacy\Elementor\Controls\Query\QueryControlManager;
use Elemacy\Core\Elemacy;
use Elemacy\Core\FrontendScripts;
use Elemacy\Core\Module;
use Elemacy\Core\ModuleCatalog;
use Elemacy\Core\ModuleManager;
use Elemacy\Core\SiteInstaller;
use Elemacy\Core\Admin\PluginLinks;
use Elemacy\Elementor\Preview\PreviewAssets;
use Elemacy\Elementor\Preview\PreviewManager;
use Elemacy\Core\Providers\CoreServiceProvider;
use Elemacy\TemplateLibrary\LibraryBootstrap;
use Elemacy\TemplateLibrary\TypeDefinition;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\Hooks;
use Elemacy\Elementor\Compatibility\AtomicElementsAdapter;
use Elemacy\Elementor\Compatibility\Capabilities;
use Elemacy\Elementor\Compatibility\ElementTreeWalker;
use Elemacy\Elementor\Compatibility\StylesRendererAdapter;
use Elemacy\Core\Migrations\BatchCursor;
use Elemacy\Core\Migrator;
use Elemacy\Core\Multisite\SiteBatchIterator;
use Elemacy\Elementor\Rendering\AtomicStylesRenderer;
use Elemacy\Elementor\Rendering\ClassicCssRenderer;
use Elemacy\Elementor\Rendering\TemplateAssetsRegistrar;
use Elemacy\Elementor\Rendering\TemplateRenderer;
use Elemacy\Http\Sanitizer;
use Elemacy\Modules\DynamicTags\Tags\Base\PostContent;
use Elemacy\Modules\DynamicTags\Tags\Base\PostExcerpt;
use Elemacy\Modules\Popups\DTO\RuleDTO;
use Elemacy\Modules\Popups\DTO\TriggerDTO;
use Elemacy\Modules\Popups\Rules\BaseRule;
use Elemacy\Modules\Popups\Rules\MockRule;
use Elemacy\Modules\Popups\Services\EditorPreview;
use Elemacy\Modules\Popups\Services\RuleManager;
use Elemacy\Modules\Popups\Services\TriggerManager;
use Elemacy\Modules\Popups\Support\DisplayDefaults;
use Elemacy\Modules\Popups\Support\PopupTypes;
use Elemacy\Modules\Popups\Support\RuleTriggerParams;
use Elemacy\Modules\Popups\DTO\PopupDTO;
use Elemacy\Modules\Popups\Resources\PopupConfigResource;
use Elemacy\Modules\Popups\Resources\PopupResource;
use Elemacy\Modules\Popups\Resources\RuleTypeResource;
use Elemacy\Modules\Popups\Resources\TriggerTypeResource;
use Elemacy\Conditions\Resources\ConditionTypeResource;
use Elemacy\Modules\Popups\Triggers\BaseTrigger;
use Elemacy\Modules\Popups\Triggers\MockTrigger;
use Elemacy\Loop\Context\WordPressPostScope;
use Elemacy\Loop\Rendering\LoopRenderContext;
use Elemacy\Loop\Rendering\LoopRenderIdentity;
use Elemacy\Loop\Rendering\TemplateRecursionException;
use Elemacy\Loop\Rendering\TemplateRecursionGuard;
use Elemacy\Loop\Sources\PostsQuery;
use Elemacy\Loop\Sources\TermsQuery;
use Elemacy\Loop\Sources\UsersQuery;
use Elemacy\Loop\Pagination\Policies\PostsPolicy;
use Elemacy\Loop\Pagination\Policies\TermsPolicy;
use Elemacy\Loop\Pagination\Policies\UsersPolicy;
use Elemacy\Modules\DynamicTags\Services\Taxonomy;
use Elemacy\Modules\DynamicTags\Services\User;
use Elemacy\Modules\Widgets\DataSources\PostsDataSource;
use Elemacy\Modules\Widgets\DataSources\TaxonomyDataSource;
use Elemacy\Modules\Widgets\DataSources\UsersDataSource;
use Elemacy\Modules\ThemeBuilder\ThemeBuilder;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\ClassicThemeCompatibility;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\Items\TermLoopItem;
use Elemacy\Loop\Items\UserLoopItem;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Support\LoopItemIdentity;
use Elemacy\Loop\Rendering\LoopItemStyles;

/* ── Test doubles ───────────────────────────────────────────────── */

/**
 * Condition whose match result is fixed at construction.
 */
final class FixedCondition extends BaseCondition
{
    private string $name;
    private bool $matches;

    public function __construct(string $name, bool $matches)
    {
        $this->name    = $name;
        $this->matches = $matches;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_type(): string
    {
        return 'general';
    }

    public function get_label(): string
    {
        return $this->name;
    }

    public function check(ConditionRuleDTO $rule): bool
    {
        return $this->matches;
    }
}

final class FixedMockCondition extends MockCondition
{
    public function get_name(): string
    {
        return 'mock/locked';
    }

    public function get_type(): string
    {
        return 'general';
    }

    public function get_label(): string
    {
        return 'Locked';
    }
}

final class FixedRule extends BaseRule
{
    public function get_name(): string
    {
        return 'test/rule';
    }

    public function get_label(): string
    {
        return 'Test rule';
    }
}

final class FixedMockRule extends MockRule
{
    public function get_name(): string
    {
        return 'mock/rule';
    }

    public function get_label(): string
    {
        return 'Locked rule';
    }
}

final class FixedTrigger extends BaseTrigger
{
    public function get_name(): string
    {
        return 'test/trigger';
    }

    public function get_label(): string
    {
        return 'Test trigger';
    }
}

final class FixedMockTrigger extends MockTrigger
{
    public function get_name(): string
    {
        return 'mock/trigger';
    }

    public function get_label(): string
    {
        return 'Locked trigger';
    }
}

/**
 * Pairs a boolean predicate with an explicit availability status, standing
 * in for what a condition/rule/trigger reports once
 * {@see FeatureAvailability} is wired into a real registry (tasks 4.2/4.3).
 */
final class FixtureCapability
{
    /** @var callable */
    private $truth;
    private FeatureAvailability $availability;

    public function __construct(callable $truth, FeatureAvailability $availability)
    {
        $this->truth       = $truth;
        $this->availability = $availability;
    }

    public function check(): bool
    {
        return ($this->truth)();
    }

    public function availability(): FeatureAvailability
    {
        return $this->availability;
    }
}

/**
 * Migrator with an injectable, deliberately unsorted migration map that logs
 * each execution.
 */
// These fast fixtures isolate schema sequencing. Database lease races are tested
// with independent native WordPress processes in integration/migration-leases.php.
class SchemaFlowMigrator extends Migrator
{
    protected function acquire_lock(): bool { return true; }
    protected function release_lock(): void {}
    protected function current_schema_version(): string
    {
        return (string) get_option($this->option_key(), self::BASELINE_VERSION);
    }
}

class SchemaFlowCursor extends BatchCursor
{
    public function acquire_lock(): bool { return true; }
    public function release_lock(): void {}
}

final class RecordingMigrator extends SchemaFlowMigrator
{
    /** @var array<int, array{string, string, string}> */
    public array $log = [];

    protected function migrations(): array
    {
        // Deliberately out of order: run() must sort by version itself.
        return [
            '1.2.0'  => function ($from, $to) {
                $this->log[] = ['1.2.0', $from, $to];
            },
            '1.0.5'  => function ($from, $to) {
                $this->log[] = ['1.0.5', $from, $to];
            },
            '1.10.0' => function ($from, $to) {
                $this->log[] = ['1.10.0', $from, $to];
            },
        ];
    }
}

final class TestableEditorPreview extends EditorPreview
{
    public function get_empty_document_state_css(): string
    {
        return $this->empty_document_state_css();
    }

    public function get_frame_css(string $wrapper): string
    {
        return $this->frame_css($wrapper);
    }

    public function get_topbar_css(string $wrapper): string
    {
        return $this->topbar_css($wrapper);
    }

    public function get_overlay_css(): string
    {
        return $this->overlay_css();
    }

    public function get_close_base_css(): string
    {
        return $this->close_base_css();
    }
}

/* ── Core\Container fixtures ─────────────────────────────────────── */

interface FixtureContainerContract
{
}

final class FixtureContainerImplA implements FixtureContainerContract
{
    public string $id;

    public function __construct()
    {
        $this->id = uniqid('a', true);
    }
}

final class FixtureContainerImplB implements FixtureContainerContract
{
    public string $id;

    public function __construct()
    {
        $this->id = uniqid('b', true);
    }
}

final class FixtureContainerConsumer
{
    public FixtureContainerContract $contract;

    public function __construct(FixtureContainerContract $contract)
    {
        $this->contract = $contract;
    }
}

final class FixtureContainerDefaultScalar
{
    public string $label;

    public function __construct(string $label = 'default-label')
    {
        $this->label = $label;
    }
}

final class FixtureContainerRequiredScalar
{
    public function __construct(string $required)
    {
    }
}

final class FixtureContainerCycleA
{
    public function __construct(FixtureContainerCycleB $b)
    {
    }
}

final class FixtureContainerCycleB
{
    public function __construct(FixtureContainerCycleA $a)
    {
    }
}

/* ── Popup editor empty state ───────────────────────────────────── */

check('popup creation prompt is hidden only after content exists', static function () {
    $css = (new TestableEditorPreview())->get_empty_document_state_css();

    return false !== strpos(
        $css,
        '[data-elementor-type="elemacy_popup"] .elementor-section-wrap:not(:empty) + #elementor-add-new-section'
    );
});

check('popup preview wrapper adds no visual decoration', static function () {
    $preview = new TestableEditorPreview();
    $css     = $preview->get_frame_css('.elementor-123')
        . $preview->get_topbar_css('.elementor-123');

    return false === strpos($css, 'background')
        && false === strpos($css, 'border-radius')
        && false === strpos($css, 'box-shadow');
});

check('popup preview hides the close button while the popup document is empty', static function () {
    $css = (new TestableEditorPreview())->get_close_base_css();

    return false !== strpos(
        $css,
        '[data-elementor-type="elemacy_popup"]:has(.elementor-section-wrap:empty) .elemacy-popup__close{display:none;}'
    );
});

check('popup frontend wrapper adds no visual decoration', static function () {
    $css = file_get_contents(
        dirname(__DIR__, 2) . '/src/Modules/Popups/assets/styles/popups.css'
    );

    $box_rules = [];
    preg_match_all('/[^{}]*\.elemacy-popup__box[^{}]*\{([^}]*)\}/', $css, $box_rules);
    $box_css = implode('', $box_rules[1]);

    return false === strpos($box_css, 'background')
        && false === strpos($box_css, 'border-radius')
        && false === strpos($box_css, 'box-shadow');
});

check('close button controls outrank the popups.css fallback rule for the button', static function () {
    $root = dirname(__DIR__, 2);
    $php  = file_get_contents($root . '/src/Modules/Popups/Documents/PopupDocument.php');
    $css  = file_get_contents($root . '/src/Modules/Popups/assets/styles/popups.css');

    if (!preg_match('/CLOSE_BUTTON_SELECTOR = \'([^\']+)\';/', $php, $control)
        || !preg_match('/^(\.elemacy-popup \.elemacy-popup__close)\s*\{/m', $css, $base)) {
        return false;
    }

    // [ids, classes/attributes/pseudo-classes, element types]; arrays compare lexicographically.
    $specificity = static function (string $selector): array {
        $selector = str_replace('{{WRAPPER}}', '.elementor-1', $selector);
        preg_match_all('/#[\w-]+/', $selector, $ids);
        preg_match_all('/\.[\w-]+|\[[^\]]*\]|(?<!:):(?!:)[\w-]+/', $selector, $classes);
        preg_match_all('/(?:^|[\s>+~])([a-zA-Z][\w-]*)/', $selector, $types);

        return [count($ids[0]), count($classes[0]), count($types[0])];
    };

    // Both rules ship in different stylesheets and popups.css loads last on the
    // site, so a tie lets the fallback silently beat the author's settings.
    return $specificity($control[1]) > $specificity($base[1]);
});

check('the Distance From Edge slider can actually reach outside the popup', static function () {
    $php = file_get_contents(dirname(__DIR__, 2) . '/src/Modules/Popups/Documents/PopupDocument.php');

    // Placing the close button outside the popup is the whole point of the
    // negative range, and it is only reachable from the UI if the slider's own
    // minimum is negative. Elementor strips 'range' from a control's registered
    // args outside the editor and the fast harness stubs Elementor, so the
    // source is where this is observable.
    if (!preg_match('/\'elemacy_close_offset\',.*?\'range\'\s*=>\s*\[\s*\'px\'\s*=>\s*\[(.*?)\]/s', $php, $range)) {
        return false;
    }

    return preg_match('/\'min\'\s*=>\s*(-?\d+)/', $range[1], $min) && (int) $min[1] < 0;
});

check('every close button rule the document emits goes through that one selector', static function () {
    $php = file_get_contents(dirname(__DIR__, 2) . '/src/Modules/Popups/Documents/PopupDocument.php');

    // The Close Button toggle lives in the display section and the look/size
    // controls in the style section; a bare selector left behind in either is a
    // rule that loses the tie to popups.css on the frontend while still winning
    // in the editor, which is exactly the split this selector exists to close.
    return false === strpos($php, "'{{WRAPPER}} .elemacy-popup__close'")
        && substr_count($php, 'self::CLOSE_BUTTON_SELECTOR') >= 2;
});

check('popup overlay blur defaults preserve existing appearance', static function () {
    foreach ([PopupTypes::POPUP, PopupTypes::TOPBAR, PopupTypes::BANNER, PopupTypes::FLOATING] as $type) {
        $defaults = DisplayDefaults::for_type($type);

        if (DisplayDefaults::DEFAULT_OVERLAY_BLUR !== $defaults['overlay']['blur']) {
            return false;
        }
    }

    return true;
});

check('popup preview supports prefixed and standard backdrop blur', static function () {
    $css = (new TestableEditorPreview())->get_overlay_css();

    return false !== strpos($css, '-webkit-backdrop-filter:blur(var(--elemacy-ov-blur,0px))')
        && false !== strpos($css, 'backdrop-filter:blur(var(--elemacy-ov-blur,0px))');
});

/* ── Core\Container ──────────────────────────────────────────────── */

$container = Container::instance();

check('Container: an unbound interface reports it has no registered binding', static function () use ($container) {
    try {
        $container->make(FixtureContainerContract::class);

        return false;
    } catch (\Exception $error) {
        return false !== strpos($error->getMessage(), 'No binding is registered for contract');
    }
});

check('Container: bind() + make()/singleton() resolve a contract through its registered factory', static function () use ($container) {
    $container->bind(FixtureContainerContract::class, static fn () => new FixtureContainerImplA());

    return $container->make(FixtureContainerContract::class) instanceof FixtureContainerImplA
        && $container->singleton(FixtureContainerContract::class) instanceof FixtureContainerImplA;
});

check('Container: a configured factory is actually invoked, not bypassed', static function () use ($container) {
    $container->bind(FixtureContainerContract::class, static fn () => new FixtureContainerImplB());

    return $container->make(FixtureContainerContract::class) instanceof FixtureContainerImplB;
});

check('Container: a shared-bound contract keeps identity across calls, including as a nested dependency', static function () use ($container) {
    $container->bind(FixtureContainerContract::class, static fn () => new FixtureContainerImplA(), Container::SHARED);

    $direct1 = $container->make(FixtureContainerContract::class);
    $direct2 = $container->singleton(FixtureContainerContract::class);
    $nested = $container->make(FixtureContainerConsumer::class)->contract;

    return $direct1 === $direct2 && $direct1 === $nested;
});

check('Container: a fresh-bound contract is rebuilt every time, including as a nested dependency', static function () use ($container) {
    $container->bind(FixtureContainerContract::class, static fn () => new FixtureContainerImplA(), Container::FRESH);

    $direct1 = $container->make(FixtureContainerContract::class);
    $direct2 = $container->make(FixtureContainerContract::class);
    $nested1 = $container->make(FixtureContainerConsumer::class)->contract;
    $nested2 = $container->make(FixtureContainerConsumer::class)->contract;

    return $direct1 !== $direct2 && $nested1 !== $nested2 && $direct1 !== $nested1;
});

check('Container: an unbound class resolved via singleton() keeps shared identity across calls', static function () use ($container) {
    $container->bind(FixtureContainerContract::class, static fn () => new FixtureContainerImplA());

    $consumer1 = $container->singleton(FixtureContainerConsumer::class);
    $consumer2 = $container->singleton(FixtureContainerConsumer::class);

    return $consumer1 === $consumer2;
});

check('Container: make() on an unbound class still returns a fresh instance every call (pre-existing behavior preserved)', static function () use ($container) {
    $container->bind(FixtureContainerContract::class, static fn () => new FixtureContainerImplA());

    return $container->make(FixtureContainerConsumer::class) !== $container->make(FixtureContainerConsumer::class);
});

check('Container: a builtin-typed constructor parameter with a default value is honored, not rejected', static function () use ($container) {
    return $container->make(FixtureContainerDefaultScalar::class)->label === 'default-label';
});

check('Container: a required builtin-typed parameter with no default cannot be auto-wired and names bind() as the fix', static function () use ($container) {
    try {
        $container->make(FixtureContainerRequiredScalar::class);

        return false;
    } catch (\Exception $error) {
        return false !== strpos($error->getMessage(), 'Register a Container::bind() factory');
    }
});

check('Container: a constructor cycle is detected and reported', static function () use ($container) {
    try {
        $container->make(FixtureContainerCycleA::class);

        return false;
    } catch (\Exception $error) {
        return false !== strpos($error->getMessage(), 'Circular dependency detected');
    }
});

check('Container: a cycle reached through a bound factory calling back into the container is also detected', static function () use ($container) {
    $container->bind(FixtureContainerContract::class, static fn (Container $c) => $c->make(FixtureContainerContract::class));

    try {
        $container->make(FixtureContainerContract::class);

        return false;
    } catch (\Exception $error) {
        return false !== strpos($error->getMessage(), 'Circular dependency detected');
    }
});

/* ── Core\Elemacy bootstrap: providers register-then-boot, idempotent ── */

/**
 * Logs, in call order, when register()/boot() run — proves the
 * ServiceProviderInterface contract's own ordering guarantee end to end
 * through Elemacy::init(), without reimplementing its orchestration.
 */
final class FixtureOrderTrackingProvider implements ServiceProviderInterface
{
    /** @var string[] */
    public static array $log = [];

    private string $name;
    private bool $booted = false;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function register(Container $container): void
    {
        self::$log[] = "register:{$this->name}";
    }

    public function boot(): void
    {
        if ($this->booted) {
            self::$log[] = "boot-skipped:{$this->name}";

            return;
        }

        $this->booted = true;
        self::$log[] = "boot:{$this->name}";
    }
}

/**
 * Deliberately does NOT guard its own boot(): the point of the tests using
 * it is that the composition root, not the provider, is what makes a repeated
 * bootstrap boot nothing a second time.
 */
final class FixtureUnguardedProvider implements ServiceProviderInterface
{
    /** @var string[] */
    public static array $boots = [];

    private string $label;

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    public function register(Container $container): void {}

    public function boot(): void
    {
        self::$boots[] = $this->label;
    }
}

check('CoreServiceProvider::register() does no eager work (no hooks attached, no options changed)', static function () {
    $actions_before = $GLOBALS['__wp_actions'];
    $options_before = $GLOBALS['__wp_options'];

    (new CoreServiceProvider())->register(Container::instance());

    return $GLOBALS['__wp_actions'] === $actions_before && $GLOBALS['__wp_options'] === $options_before;
});

check('CoreServiceProvider::register() binds every shared registry to its ::instance() singleton, so a container-built service receives the populated instance rather than a fresh copy', static function () {
    (new CoreServiceProvider())->register(Container::instance());

    foreach ([TypeRegistry::class, \Elemacy\TemplateLibrary\TemplateResolver::class, LoopDataSourceRegistry::class, \Elemacy\Loop\Placement\PlacementResolverRegistry::class, ConditionManager::class, ConditionEvaluator::class] as $registry) {
        if (Container::instance()->singleton($registry) !== $registry::instance() || Container::instance()->make($registry) !== $registry::instance()) {
            return false;
        }
    }

    $policy = Container::instance()->make(\Elemacy\Loop\Pagination\PublicRenderPolicy::class);
    $types = new ReflectionProperty($policy, 'types');
    $types->setAccessible(true);
    $placements = new ReflectionProperty($policy, 'placements');
    $placements->setAccessible(true);

    return $types->getValue($policy) === TypeRegistry::instance()
        && $placements->getValue($policy) === \Elemacy\Loop\Placement\PlacementResolverRegistry::instance();
});

add_action(Hooks::REGISTER_PROVIDERS_ACTION, static function (Elemacy $elemacy) {
    $elemacy->register_provider(new FixtureOrderTrackingProvider('fixture-a'));
    $elemacy->register_provider(new FixtureOrderTrackingProvider('fixture-b'));
    // Two instances of one class: two contributions, each must boot.
    $elemacy->register_provider(new FixtureUnguardedProvider('twin-a'));
    $elemacy->register_provider(new FixtureUnguardedProvider('twin-b'));
});

do_action('elementor/loaded'); // check_requirements() gates the whole bootstrap on this.
$elemacy = Elemacy::get_instance();

// Filled by the REGISTER_MODULES_ACTION listener and the pre-init check
// below, then compared against the manager init() actually loaded into.
$fixture_modules_action_manager = null;
$fixture_pre_init_manager = null;

add_action(Hooks::REGISTER_MODULES_ACTION, static function (ModuleManager $manager) use (&$fixture_modules_action_manager) {
    $fixture_modules_action_manager = $manager;
});

check('Elemacy::get_module_manager() before init() returns an empty manager rather than throwing', static function () use ($elemacy, &$fixture_pre_init_manager) {
    try {
        $fixture_pre_init_manager = $elemacy->get_module_manager();
    } catch (\Throwable $e) {
        return false;
    }

    return $fixture_pre_init_manager instanceof ModuleManager && $fixture_pre_init_manager->get_modules() === [];
});

check('Elemacy::init(): first call registers every provider before booting any of them, then fires LOADED_ACTION and subscribes the native init-hook library-type registrations', static function () use ($elemacy) {
    FixtureOrderTrackingProvider::$log = [];
    $loaded_before = did_action(Hooks::LOADED_ACTION);
    $init_p5_before = count($GLOBALS['__wp_actions']['init'][5] ?? []);
    $init_p20_before = count($GLOBALS['__wp_actions']['init'][20] ?? []);

    $elemacy->init();

    return FixtureOrderTrackingProvider::$log === ['register:fixture-a', 'register:fixture-b', 'boot:fixture-a', 'boot:fixture-b']
        && did_action(Hooks::LOADED_ACTION) === $loaded_before + 1
        && count($GLOBALS['__wp_actions']['init'][5] ?? []) === $init_p5_before + 1
        && count($GLOBALS['__wp_actions']['init'][20] ?? []) === $init_p20_before + 1;
});

check('Elemacy::init() loads modules into the one container-shared manager: the instance get_module_manager() returned before init(), and the one REGISTER_MODULES_ACTION listeners receive', static function () use ($elemacy, &$fixture_pre_init_manager, &$fixture_modules_action_manager) {
    $manager = $elemacy->get_module_manager();

    return $manager === $fixture_pre_init_manager
        && $manager === $fixture_modules_action_manager
        && $manager === Container::instance()->singleton(ModuleManager::class)
        && $manager->get_modules() !== [];
});

check('ModuleLoader builds from a container with no bindings at all and receives that container\'s shared ModuleManager and ModuleCatalog', static function () {
    $bare = (new ReflectionClass(Container::class))->newInstanceWithoutConstructor();

    try {
        $loader = $bare->make(\Elemacy\Core\ModuleLoader::class);
    } catch (\Throwable $e) {
        return false;
    }

    $modules = new ReflectionProperty($loader, 'modules');
    $modules->setAccessible(true);
    $catalog = new ReflectionProperty($loader, 'catalog');
    $catalog->setAccessible(true);

    return $modules->getValue($loader) === $bare->singleton(ModuleManager::class)
        && $catalog->getValue($loader) === $bare->singleton(ModuleCatalog::class);
});

check('Elemacy::init(): a second (duplicate) call attaches nothing a second time', static function () use ($elemacy) {
    $admin_enqueue_before = count($GLOBALS['__wp_actions']['admin_enqueue_scripts'][10] ?? []);
    $loaded_before = did_action(Hooks::LOADED_ACTION);
    FixtureOrderTrackingProvider::$log = [];

    $elemacy->init();
    $elemacy->init();

    return FixtureOrderTrackingProvider::$log === []
        && count($GLOBALS['__wp_actions']['admin_enqueue_scripts'][10] ?? []) === $admin_enqueue_before
        && did_action(Hooks::LOADED_ACTION) === $loaded_before;
});

check('Elemacy::init(): repeated calls boot a provider that does not guard itself only once, and two providers of one class each boot', static function () use ($elemacy) {
    // The twins were booted by the first init() above; every init() since,
    // including these, is a no-op for them.
    $elemacy->init();
    $elemacy->init();

    // Class-keyed tracking would have booted only one of the two; no run-once
    // guard at all would have booted each again.
    return FixtureUnguardedProvider::$boots === ['twin-a', 'twin-b'];
});

check('ServiceProviderInterface no longer requires implementations to make boot() idempotent', static function () {
    $doc = (new ReflectionMethod(ServiceProviderInterface::class, 'boot'))->getDocComment();

    return is_string($doc)
        && stripos($doc, 'idempotent') === false
        && stripos($doc, 'at most once') !== false;
});

check('Neither CoreServiceProvider carries its own boot guard any more, and a repeated init() still attaches each hook only once', static function () use ($elemacy) {
    $free_has_flag = property_exists(CoreServiceProvider::class, 'booted');
    $pro_provider  = '\\ElemacyPro\\Core\\Providers\\CoreServiceProvider';
    // Pro is not autoloadable from this suite; assert against its source.
    $pro_source    = file_get_contents(dirname(ELEMACY_PATH, 1) . '/elemacy-pro/src/Core/Providers/CoreServiceProvider.php');
    $pro_has_flag  = is_string($pro_source) && strpos($pro_source, '$booted') !== false;

    // The guard these flags used to provide: CoreServiceProvider::boot()
    // constructs fresh collaborators every call, so their own $registered
    // flags would not stop a second pass — only the root's run-once init()
    // does.
    $admin_enqueue_before = count($GLOBALS['__wp_actions']['admin_enqueue_scripts'][10] ?? []);

    $elemacy->init();
    $elemacy->init();

    return $free_has_flag === false
        && $pro_has_flag === false
        && count($GLOBALS['__wp_actions']['admin_enqueue_scripts'][10] ?? []) === $admin_enqueue_before;
});

/* ── Task 6.3: hook attachment moved out of constructors ───────────── */

/**
 * Every shared bootstrap/manager CoreServiceProvider::boot() builds through
 * the container, each split into a hook-free constructor plus an idempotent
 * register_hooks() (design.md D7).
 */
$fixture_63_classes = [
    AdminScripts::class,
    FrontendScripts::class,
    PluginLinks::class,
    ConditionsBootstrap::class,
    LibraryBootstrap::class,
    PreviewManager::class,
    PreviewAssets::class,
    QueryControlManager::class,
    TemplateAssetsRegistrar::class,
    \Elemacy\Core\Admin\CoreAdminMenu::class,
    \Elemacy\Http\HttpBootstrap::class,
];

check('constructing any of the task 6.3 bootstrap/manager services attaches no WordPress hooks', static function () use ($fixture_63_classes) {
    foreach ($fixture_63_classes as $class) {
        $before = $GLOBALS['__wp_actions'];

        Container::instance()->make($class);

        if ($GLOBALS['__wp_actions'] !== $before) {
            return false;
        }
    }

    return true;
});

check('register_hooks() on each task 6.3 service attaches its hooks, and a second call on the same instance attaches nothing more', static function () use ($fixture_63_classes) {
    foreach ($fixture_63_classes as $class) {
        $instance = Container::instance()->make($class);
        $before = $GLOBALS['__wp_actions'];

        $instance->register_hooks();
        $after_first = $GLOBALS['__wp_actions'];

        if ($after_first === $before) {
            return false;
        }

        $instance->register_hooks();
        $after_second = $GLOBALS['__wp_actions'];

        if ($after_second !== $after_first) {
            return false;
        }
    }

    return true;
});

check('CoreServiceProvider::boot() wires every service\'s native hooks; single-boot is the composition root\'s guarantee, not the provider\'s', static function () {
    $representative_hooks = [
        'admin_enqueue_scripts',
        'wp_enqueue_scripts',
        'plugin_action_links_' . ELEMACY_PLUGIN_BASE,
        'save_post',
        'elementor/editor/before_enqueue_scripts',
        'elementor/controls/register',
        'elementor/document/related_posts',
    ];

    $counts_before = [];
    foreach ($representative_hooks as $hook) {
        $counts_before[$hook] = count(array_merge(...array_values($GLOBALS['__wp_actions'][$hook] ?? [[]])));
    }

    $init_before = count($GLOBALS['__wp_actions']['init'][99] ?? []);

    $provider = new CoreServiceProvider();
    $provider->boot();

    foreach ($representative_hooks as $hook) {
        $count_after = count(array_merge(...array_values($GLOBALS['__wp_actions'][$hook] ?? [[]])));

        if ($count_after <= $counts_before[$hook]) {
            return false;
        }
    }

    if (count($GLOBALS['__wp_actions']['init'][99] ?? []) <= $init_before) {
        return false;
    }

    $snapshot_after_first_boot = $GLOBALS['__wp_actions'];

    // Calling boot() directly a second time DOES attach again, and that is
    // the point: boot() builds fresh collaborators every call, so their own
    // register_hooks() guards cannot cover a repeat pass. Nothing in the
    // plugin calls boot() directly — Elemacy::init(), which runs once per
    // request, owns the at-most-once guarantee, covered by its tests above.
    $provider->boot();

    return $GLOBALS['__wp_actions'] !== $snapshot_after_first_boot;
});

/* ── Task 6.4: modules obtain collaborators through providers ──────── */

/**
 * A Module whose register()/boot() just log, so ModuleManager's own
 * orchestration (not any real module's collaborators, which need real
 * Elementor) can be exercised in isolation.
 */
final class FixtureTrackedModule extends Module
{
    /** @var string[] */
    public static array $log = [];

    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_title(): string
    {
        return $this->name;
    }

    public function get_icon(): string
    {
        return '';
    }

    public function get_description(): string
    {
        return '';
    }

    public function register(Container $container): void
    {
        self::$log[] = "register:{$this->name}";
    }

    public function boot(): void
    {
        self::$log[] = "boot:{$this->name}";
    }
}

check('Module is a service provider: register() is a concrete no-op by default and boot() is the one abstract lifecycle hook', static function () {
    return is_subclass_of(Module::class, ServiceProviderInterface::class)
        && !(new ReflectionMethod(Module::class, 'register'))->isAbstract()
        && (new ReflectionMethod(Module::class, 'boot'))->isAbstract()
        && !method_exists(Module::class, 'init')
        && !method_exists(Module::class, 'get_providers');
});

check('ModuleManager runs every active module\'s register() before any module\'s boot(), in registration order, and never touches an inactive module', static function () {
    FixtureTrackedModule::$log = [];

    $manager = new ModuleManager();
    $manager->register(new FixtureTrackedModule('fixture-a'));
    $manager->register(new FixtureTrackedModule('fixture-b'));
    $manager->register(new FixtureTrackedModule('fixture-inactive'));

    update_option(OptionKeys::ACTIVE_MODULES, ['fixture-a', 'fixture-b']);

    $manager->init_modules();

    return FixtureTrackedModule::$log === ['register:fixture-a', 'register:fixture-b', 'boot:fixture-a', 'boot:fixture-b']
        && $manager->is_active('fixture-a')
        && !$manager->is_active('fixture-inactive');
});

check('ModuleManager::init_modules() runs once per manager: a second call registers and boots nothing again', static function () {
    FixtureTrackedModule::$log = [];

    $manager = new ModuleManager();
    $manager->register(new FixtureTrackedModule('fixture-once'));
    update_option(OptionKeys::ACTIVE_MODULES, ['fixture-once']);

    $manager->init_modules();
    $manager->init_modules();

    return FixtureTrackedModule::$log === ['register:fixture-once', 'boot:fixture-once'];
});

check('ModuleManager with no modules registered at all still resolves an empty active set without error (the "all-off" configuration)', static function () {
    update_option(OptionKeys::ACTIVE_MODULES, []);

    $manager = new ModuleManager();
    $manager->init_modules();

    return [] === $manager->get_active_modules();
});

/* ── Module preferences: explicit per-module choices over declared defaults ── */

/** A module whose default, always-active and mock answers are set per instance, and whose enable/disable hooks log. */
final class FixtureChoiceModule extends Module
{
    /** @var string[] */
    public static array $events = [];

    private string $name;
    private bool $default_active;
    private bool $always_active;
    private bool $mock;

    public function __construct(string $name, bool $default_active = false, bool $always_active = false, bool $mock = false)
    {
        $this->name = $name;
        $this->default_active = $default_active;
        $this->always_active = $always_active;
        $this->mock = $mock;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_title(): string
    {
        return $this->name;
    }

    public function get_icon(): string
    {
        return '';
    }

    public function get_description(): string
    {
        return '';
    }

    public function is_default_active(): bool
    {
        return $this->default_active;
    }

    public function is_always_active(): bool
    {
        return $this->always_active;
    }

    public function is_mock(): bool
    {
        return $this->mock;
    }

    public function on_enable(): void
    {
        self::$events[] = "enable:{$this->name}";
    }

    public function on_disable(): void
    {
        self::$events[] = "disable:{$this->name}";
    }

    public function boot(): void {}
}

/** @param FixtureChoiceModule[] $modules */
$fixture_choice_manager = static function (array $modules): ModuleManager {
    $manager = new ModuleManager();

    foreach ($modules as $module) {
        $manager->register($module);
    }

    return $manager;
};

check('Module preferences: a freshly seeded site records no per-module choice, and every module follows its declared default', static function () use ($fixture_choice_manager) {
    delete_option(OptionKeys::ACTIVE_MODULES);
    Container::instance()->make(SiteInstaller::class)->seed_current_site_if_missing();
    $seeded = get_option(OptionKeys::ACTIVE_MODULES, null);

    $manager = $fixture_choice_manager([
        new FixtureChoiceModule('fixture-on', true),
        new FixtureChoiceModule('fixture-off', false),
    ]);
    $manager->init_modules();

    return [] === $seeded
        && $manager->is_active('fixture-on')
        && !$manager->is_active('fixture-off');
});

check('Module preferences: a default-active module added by a later release is active on a site that already recorded choices, and those choices are unchanged', static function () use ($fixture_choice_manager) {
    update_option(OptionKeys::ACTIVE_MODULES, ['fixture-existing' => false]);

    $manager = $fixture_choice_manager([
        new FixtureChoiceModule('fixture-existing', true),
        new FixtureChoiceModule('fixture-added-later', true),
    ]);
    $manager->init_modules();

    return !$manager->is_active('fixture-existing')
        && $manager->is_active('fixture-added-later')
        && ['fixture-existing' => false] === get_option(OptionKeys::ACTIVE_MODULES);
});

check('Module preferences: an administrator\'s choice outlasts the declared default in both directions', static function () use ($fixture_choice_manager) {
    update_option(OptionKeys::ACTIVE_MODULES, []);
    $modules = static fn (): array => [
        new FixtureChoiceModule('fixture-default-on', true),
        new FixtureChoiceModule('fixture-default-off', false),
    ];

    $writer = $fixture_choice_manager($modules());
    $writer->disable_module('fixture-default-on');
    $writer->enable_module('fixture-default-off');

    $later = $fixture_choice_manager($modules());
    $later->init_modules();

    return !$later->is_active('fixture-default-on')
        && $later->is_active('fixture-default-off');
});

check('Module preferences: a stored legacy list keeps exactly its listed modules plus always-active ones active', static function () use ($fixture_choice_manager) {
    update_option(OptionKeys::ACTIVE_MODULES, ['fixture-listed']);

    $manager = $fixture_choice_manager([
        new FixtureChoiceModule('fixture-listed', false),
        new FixtureChoiceModule('fixture-unlisted-default-on', true),
        new FixtureChoiceModule('fixture-always', false, true),
    ]);
    $manager->init_modules();

    return $manager->is_active('fixture-listed')
        && !$manager->is_active('fixture-unlisted-default-on')
        && $manager->is_active('fixture-always');
});

check('Module preferences: the first toggle on a legacy list stores per-module choices that keep the same set active apart from the toggled module', static function () use ($fixture_choice_manager) {
    update_option(OptionKeys::ACTIVE_MODULES, ['fixture-listed']);

    $manager = $fixture_choice_manager([
        new FixtureChoiceModule('fixture-listed', false),
        new FixtureChoiceModule('fixture-unlisted-default-on', true),
        new FixtureChoiceModule('fixture-toggled', false),
        new FixtureChoiceModule('fixture-always', false, true),
    ]);
    $manager->enable_module('fixture-toggled');

    $stored = get_option(OptionKeys::ACTIVE_MODULES);
    ksort($stored);

    return [
        'fixture-listed' => true,
        'fixture-toggled' => true,
        'fixture-unlisted-default-on' => false,
    ] === $stored;
});

check('Module preferences: enable/disable record the explicit choice even when it matches the default, and fire on_enable/on_disable only on an effective change', static function () use ($fixture_choice_manager) {
    update_option(OptionKeys::ACTIVE_MODULES, []);
    FixtureChoiceModule::$events = [];

    $manager = $fixture_choice_manager([new FixtureChoiceModule('fixture-hooks', true)]);

    $manager->enable_module('fixture-hooks'); // Already active by default: choice recorded, no event.
    $recorded = get_option(OptionKeys::ACTIVE_MODULES);
    $manager->disable_module('fixture-hooks');
    $manager->disable_module('fixture-hooks'); // Already off: no second event.
    $manager->enable_module('fixture-hooks');

    return ['fixture-hooks' => true] === $recorded
        && ['disable:fixture-hooks', 'enable:fixture-hooks'] === FixtureChoiceModule::$events
        && ['fixture-hooks' => true] === get_option(OptionKeys::ACTIVE_MODULES);
});

check('Module preferences: toggling an unknown module, or a mock through the admin endpoint, writes nothing', static function () use ($fixture_choice_manager) {
    update_option(OptionKeys::ACTIVE_MODULES, ['fixture-kept' => true]);

    $unknown_refused = false;
    try {
        $fixture_choice_manager([new FixtureChoiceModule('fixture-kept')])->enable_module('fixture-nope');
    } catch (\Elemacy\Http\Exceptions\ModuleNotFoundException $refused) {
        $unknown_refused = true;
    }

    $shared = Elemacy::get_instance()->get_module_manager();
    $shared->register(new FixtureChoiceModule('fixture-locked-mock', false, false, true));
    $request = new \Elemacy\Http\Request();
    $request->action = 'enable';

    $mock_refused = false;
    try {
        (new \Elemacy\Core\Admin\ModuleController())->toggle($request, 'fixture-locked-mock');
    } catch (\Elemacy\Http\Exceptions\HttpException $refused) {
        $mock_refused = true;
    } finally {
        $modules = new ReflectionProperty($shared, 'modules');
        $modules->setAccessible(true);
        $registered = $modules->getValue($shared);
        unset($registered['fixture-locked-mock']);
        $modules->setValue($shared, $registered);
    }

    return $unknown_refused
        && $mock_refused
        && ['fixture-kept' => true] === get_option(OptionKeys::ACTIVE_MODULES);
});

/* ── Action hook stub semantics ─────────────────────────────────── */

check('do_action stub fires callbacks in ascending priority order', static function () {
    $order = [];
    add_action('test/priority-order', static function () use (&$order) {
        $order[] = 'twenty';
    }, 20);
    add_action('test/priority-order', static function () use (&$order) {
        $order[] = 'nineteen';
    }, 19);

    do_action('test/priority-order');

    return $order === ['nineteen', 'twenty'];
});

/* ── Core\CompatibilityChecker (task 9.6 — the evidence-based Elementor
     version floor) ─────────────────────────────────────────────────── */

check('CompatibilityChecker::MIN_ELEMENTOR_VERSION correctly gates 3.35.9 (tested minimum) and 4.2.3 (tested current) as compatible, and 3.35.8 as not', static function () {
    return false === version_compare('3.35.9', CompatibilityChecker::MIN_ELEMENTOR_VERSION, '<')
        && false === version_compare('4.2.3', CompatibilityChecker::MIN_ELEMENTOR_VERSION, '<')
        && true === version_compare('3.35.8', CompatibilityChecker::MIN_ELEMENTOR_VERSION, '<');
});

check('CompatibilityChecker::check() does not fail the version gate when ELEMENTOR_VERSION is undefined (a stub/testing environment lacking it) — must run before this file ever defines that constant', static function () {
    if (defined('ELEMENTOR_VERSION')) {
        throw new \RuntimeException('ELEMENTOR_VERSION was already defined; this check can no longer prove anything about the undefined case.');
    }

    $GLOBALS['__wp_actions_fired']['elementor/loaded'] = 1;

    return true === CompatibilityChecker::instance()->check();
});

// Only ever defined once, deliberately below the tested floor, so the
// remaining checks below can exercise the "too old" branch — PHP constants
// cannot be redefined, and the check above already proved the undefined case.
define('ELEMENTOR_VERSION', '3.0.0');

check('CompatibilityChecker::check() fails and schedules deactivation when the installed Elementor is older than the tested minimum', static function () {
    $GLOBALS['__wp_actions_fired']['elementor/loaded'] = 1;
    $GLOBALS['__wp_actions'] = [];

    $result = CompatibilityChecker::instance()->check();

    return false === $result && !empty($GLOBALS['__wp_actions']['admin_notices'])
        && !empty($GLOBALS['__wp_actions']['admin_init']);
});

check('CompatibilityChecker::admin_notice_outdated_elementor_version() names both the required and the installed version', static function () {
    ob_start();
    CompatibilityChecker::instance()->admin_notice_outdated_elementor_version();
    $output = ob_get_clean();

    return false !== strpos($output, CompatibilityChecker::MIN_ELEMENTOR_VERSION)
        && false !== strpos($output, '3.0.0');
});

/* ── Core\Rendering\TemplateRenderer (Elementor not yet loaded) ─── */

check('renderer returns empty string when Elementor is not loaded', static function () {
    return (new TemplateRenderer())->render(101) === '';
});

/* ── DynamicTags\Tags\Base\PostContent (Elementor not yet loaded) ── */

check('PostContent falls back to classic content when \Elementor\Plugin is not loaded', static function () {
    $GLOBALS['__current_post_id'] = 44;
    $GLOBALS['__current_post_content'] = '<p>Plain content, no Elementor</p>';

    ob_start();
    (new PostContent())->render();
    $output = ob_get_clean();

    return $output === wp_kses_post('<p>Plain content, no Elementor</p>');
});

/* ── DynamicTags\Tags\Base\PostExcerpt ─────────────────────────────── */

if (!function_exists('get_post')) {
    /** The current post PostExcerpt::render() reads; tests set $GLOBALS['__current_post']. */
    function get_post($post = null)
    {
        return $GLOBALS['__current_post'] ?? null;
    }
}

/** Elementor's real Tag resolves settings for display; the dynamic-tag stub only stores them. */
final class FixtureDisplayedPostExcerpt extends PostExcerpt
{
    public function get_settings_for_display($key = null)
    {
        return $this->get_settings($key);
    }
}

$fixture_render_excerpt = static function (array $settings): string {
    $GLOBALS['__current_post'] = (object) ['post_excerpt' => 'one two three four five', 'post_content' => ''];

    ob_start();
    (new FixtureDisplayedPostExcerpt(['settings' => $settings]))->render();
    $output = (string) ob_get_clean();

    unset($GLOBALS['__current_post']);

    return $output;
};

check('PostExcerpt renders the whole excerpt when Excerpt Length is left at its blank default', static function () use ($fixture_render_excerpt) {
    return 'one two three four five' === $fixture_render_excerpt(['max_length' => '', 'apply_to_post_content' => 'no'])
        && 'one two three four five' === $fixture_render_excerpt(['max_length' => null, 'apply_to_post_content' => 'no'])
        && 'one two three four five' === $fixture_render_excerpt(['apply_to_post_content' => 'no']);
});

check('PostExcerpt trims to Excerpt Length words when one is set, and treats zero or a negative length as no limit', static function () use ($fixture_render_excerpt) {
    return 'one two' === $fixture_render_excerpt(['max_length' => '2', 'apply_to_post_content' => 'no'])
        && 'one two three four five' === $fixture_render_excerpt(['max_length' => '9', 'apply_to_post_content' => 'no'])
        && 'one two three four five' === $fixture_render_excerpt(['max_length' => '0', 'apply_to_post_content' => 'no'])
        && 'one two three four five' === $fixture_render_excerpt(['max_length' => '-3', 'apply_to_post_content' => 'no']);
});

/* ── Elementor stand-ins required from here on ──────────────────── */

require __DIR__ . '/elementor-stubs.php';

final class FakeElementorFrontend
{
    /** @var string[] */
    public array $calls = [];

    public function enqueue_styles(): void
    {
        $this->calls[] = 'enqueue_styles';
    }

    public function enqueue_scripts(): void
    {
        $this->calls[] = 'enqueue_scripts';
    }

    public function get_builder_content_for_display($id)
    {
        return "<fake-content id=\"{$id}\">";
    }
}

final class FakeElementorPreview
{
    public function __construct(private bool $preview_mode, private int $post_id)
    {
    }

    public function is_preview_mode(): bool
    {
        return $this->preview_mode;
    }

    public function get_post_id(): int
    {
        return $this->post_id;
    }
}

check('renderer delegates to Elementor when loaded', static function () {
    \Elementor\Plugin::$instance = new \Elementor\Plugin();
    \Elementor\Plugin::$instance->frontend = new FakeElementorFrontend();

    return (new TemplateRenderer())->render(303) === '<fake-content id="303">';
});

/* ── Core\Rendering\TemplateAssetsRegistrar ─────────────────────── */

/**
 * The stub harness accumulates hook registrations for the whole run (see
 * bootstrap.php), so tests that self-register on the real 'wp_enqueue_scripts'
 * / collect-action hooks must clear them first to avoid picking up listeners
 * left behind by earlier checks.
 */
function reset_template_assets_hooks(): void
{
    unset(
        $GLOBALS['__wp_actions']['wp_enqueue_scripts'],
        $GLOBALS['__wp_actions'][Hooks::TEMPLATE_ASSETS_COLLECT_ACTION],
        $GLOBALS['__wp_actions']['elementor/post/render'],
        $GLOBALS['__wp_actions']['elementor/document/related_posts']
    );
}

check('registrar collect() passes itself to the collect action, and register() fires elementor/post/render exactly once per id', static function () {
    reset_template_assets_hooks();

    $registered = [];
    add_action('elementor/post/render', static function ($post_id) use (&$registered) {
        $registered[] = $post_id;
    });

    $received = null;
    add_action(Hooks::TEMPLATE_ASSETS_COLLECT_ACTION, static function ($registrar) use (&$received) {
        $received = $registrar;
        $registrar->register(101);
        $registrar->register(101);
    });

    $registrar = new TemplateAssetsRegistrar(new TemplateRenderer(), new Capabilities());
    $registrar->register_hooks();

    do_action('wp_enqueue_scripts');

    return $received === $registrar && $registered === [101];
});

check('registrar registers before a simulated priority-20 style pass runs', static function () {
    reset_template_assets_hooks();

    $registered = [];
    add_action('elementor/post/render', static function ($post_id) use (&$registered) {
        $registered[] = $post_id;
    });
    add_action(Hooks::TEMPLATE_ASSETS_COLLECT_ACTION, static function ($registrar) {
        $registrar->register(404);
    });

    $seen_at_priority_20 = null;
    add_action('wp_enqueue_scripts', static function () use (&$registered, &$seen_at_priority_20) {
        $seen_at_priority_20 = $registered;
    }, 20);

    (new TemplateAssetsRegistrar(new TemplateRenderer(), new Capabilities()))->register_hooks();

    do_action('wp_enqueue_scripts');

    return $seen_at_priority_20 === [404];
});

check('registrar registers nothing when nothing is collected', static function () {
    reset_template_assets_hooks();

    $fired = false;
    add_action('elementor/post/render', static function () use (&$fired) {
        $fired = true;
    });

    (new TemplateAssetsRegistrar(new TemplateRenderer(), new Capabilities()))->collect();

    return false === $fired;
});

check('registrar collect() force-enqueues Elementor base frontend assets only when something was registered', static function () {
    reset_template_assets_hooks();

    \Elementor\Plugin::$instance = new \Elementor\Plugin();
    \Elementor\Plugin::$instance->frontend = new FakeElementorFrontend();

    $registrar = new TemplateAssetsRegistrar(new TemplateRenderer(), new Capabilities());
    $registrar->collect();
    $calls_with_nothing_registered = \Elementor\Plugin::$instance->frontend->calls;

    $registrar->register(505);
    $registrar->collect();
    $calls_after_register = \Elementor\Plugin::$instance->frontend->calls;

    return $calls_with_nothing_registered === []
        && $calls_after_register === ['enqueue_styles', 'enqueue_scripts'];
});

check('registrar wires related_posts to merge registered ids only for the currently queried post', static function () {
    reset_template_assets_hooks();

    $registrar = new TemplateAssetsRegistrar(new TemplateRenderer(), new Capabilities());
    $registrar->register_hooks();
    $registrar->register(606);

    $GLOBALS['__current_post_id'] = 55;
    $for_queried_post = apply_filters('elementor/document/related_posts', [9], 55);
    $for_other_post   = apply_filters('elementor/document/related_posts', [9], 999);
    $GLOBALS['__current_post_id'] = 0;

    return $for_queried_post === [9, 606] && $for_other_post === [9];
});

/**
 * WordPress prints <link rel='stylesheet' id='{handle}-css' …>; the editor
 * canvas removes every link whose id matches local-<id>-…-css.
 */
function template_stylesheet_tag(string $handle, string $quote = "'"): string
{
    return "<link rel={$quote}stylesheet{$quote} id={$quote}{$handle}-css{$quote} href={$quote}https://example.test/{$handle}.css?ver=1{$quote} media={$quote}all{$quote} />\n";
}

/**
 * Runs $assertion with a registrar that has template 707 registered, on a
 * request that is (or is not) the editor's preview frame for $edited_id, then
 * puts the shared Elementor stub back the way it was.
 */
function with_editor_canvas_registrar(bool $preview_mode, int $edited_id, callable $assertion): bool
{
    reset_template_assets_hooks();
    unset($GLOBALS['__wp_actions']['style_loader_tag']);

    $elementor = \Elementor\Plugin::instance();
    $previous_preview = $elementor->preview;
    $elementor->preview = new FakeElementorPreview($preview_mode, $edited_id);

    try {
        $registrar = new TemplateAssetsRegistrar(new TemplateRenderer(), new Capabilities());
        $registrar->register_hooks();
        $registrar->register(707);

        return (bool) $assertion();
    } finally {
        $elementor->preview = $previous_preview;
    }
}

check('registrar renames a registered template\'s atomic stylesheet id inside the editor preview, leaving the rest of the tag intact', static function () {
    return with_editor_canvas_registrar(true, 55, static function () {
        $single = template_stylesheet_tag('local-707-preview-desktop');
        $double = template_stylesheet_tag('local-707-preview-desktop', '"');

        $filtered_single = apply_filters('style_loader_tag', $single, 'local-707-preview-desktop', '', 'all');
        $filtered_double = apply_filters('style_loader_tag', $double, 'local-707-preview-desktop', '', 'all');

        return $filtered_single === str_replace("id='local-707-", "id='elemacy-local-707-", $single)
            && $filtered_double === str_replace('id="local-707-', 'id="elemacy-local-707-', $double);
    });
});

check('registrar leaves the atomic stylesheet of the document being edited alone — the editor takes that one over', static function () {
    return with_editor_canvas_registrar(true, 707, static function () {
        $tag = template_stylesheet_tag('local-707-preview-desktop');

        return apply_filters('style_loader_tag', $tag, 'local-707-preview-desktop', '', 'all') === $tag;
    });
});

check('registrar leaves atomic stylesheets untouched outside the editor preview', static function () {
    return with_editor_canvas_registrar(false, 0, static function () {
        $tag = template_stylesheet_tag('local-707-preview-desktop');

        return apply_filters('style_loader_tag', $tag, 'local-707-preview-desktop', '', 'all') === $tag;
    });
});

check('registrar only renames stylesheets of templates it registered, and only local atomic ones', static function () {
    return with_editor_canvas_registrar(true, 55, static function () {
        $unregistered = template_stylesheet_tag('local-808-preview-desktop');
        $global_classes = template_stylesheet_tag('global-preview-desktop');
        $classic = template_stylesheet_tag('elementor-post-707');

        return apply_filters('style_loader_tag', $unregistered, 'local-808-preview-desktop', '', 'all') === $unregistered
            && apply_filters('style_loader_tag', $global_classes, 'global-preview-desktop', '', 'all') === $global_classes
            && apply_filters('style_loader_tag', $classic, 'elementor-post-707', '', 'all') === $classic;
    });
});

/* ── Core\Rendering\AtomicStylesRenderer (Elementor atomic classes
     not loaded — elementor-stubs.php only defines \Elementor\Plugin and
     Dynamic_Prop_Type) ──────────────────────────────────────────── */

check('AtomicStylesRenderer::render_base() degrades to empty string when Elementor atomic-widgets internals are unavailable', static function () {
    return (new AtomicStylesRenderer())->render_base(101) === '';
});

check('AtomicStylesRenderer::render_dynamic() degrades to empty string when Elementor atomic-widgets internals are unavailable', static function () {
    return (new AtomicStylesRenderer())->render_dynamic(101, '.scope') === '';
});

check('AtomicStylesRenderer::has_dynamic_styles() degrades to false when Elementor atomic-widgets internals are unavailable', static function () {
    return (new AtomicStylesRenderer())->has_dynamic_styles(101) === false;
});

/**
 * Elementor's real element traversal (Utils::traverse_post_elements,
 * Atomic_Elements_Utils) isn't stubbed — collect_all_element_styles() is
 * the one method that needs it, so it's overridden here with fixture data,
 * isolating the thing this change actually fixes: given each element's
 * whole style definition, does collect_styles()'s dynamic/static split
 * route it to the right pass. Dynamic_Prop_Type::is_dynamic_prop_value()
 * (the only other real-class dependency in the path) is faithfully stubbed
 * in elementor-stubs.php, so this exercises the real filtering logic.
 */
final class FixtureAtomicStylesRenderer extends AtomicStylesRenderer
{
    /** @var array<int, array> */
    public array $fixture_element_styles = [];

    protected function collect_all_element_styles(int $post_id): array
    {
        return $this->fixture_element_styles;
    }

    public function collect_styles_for_test(int $post_id, bool $want_dynamic): array
    {
        return $this->collect_styles($post_id, $want_dynamic);
    }
}

check('AtomicStylesRenderer routes a style definition containing a dynamic value to the dynamic pass only', static function () {
    $dynamic_style = [
        'e-dyn1' => [
            'id' => 'e-dyn1',
            'variants' => [
                [
                    'props' => [
                        'background' => ['$$type' => 'dynamic', 'value' => ['name' => 'featured-image']],
                    ],
                ],
            ],
        ],
    ];

    $renderer = new FixtureAtomicStylesRenderer();
    $renderer->fixture_element_styles = [$dynamic_style];

    $dynamic_result = $renderer->collect_styles_for_test(1, true);
    $base_result = $renderer->collect_styles_for_test(1, false);

    return $dynamic_result === $dynamic_style && $base_result === [];
});

check('AtomicStylesRenderer routes a purely static style definition to the base pass only', static function () {
    $static_style = [
        'e-static1' => [
            'id' => 'e-static1',
            'variants' => [
                [
                    'props' => [
                        'border-radius' => ['$$type' => 'size', 'value' => ['size' => 11, 'unit' => 'px']],
                    ],
                ],
            ],
        ],
    ];

    $renderer = new FixtureAtomicStylesRenderer();
    $renderer->fixture_element_styles = [$static_style];

    $dynamic_result = $renderer->collect_styles_for_test(1, true);
    $base_result = $renderer->collect_styles_for_test(1, false);

    return $base_result === $static_style && $dynamic_result === [];
});

check('AtomicStylesRenderer keeps a mixed dynamic+static style definition whole, never split, in the dynamic pass', static function () {
    // Whole-definition exclusion (design.md's Decisions): a style with both
    // a dynamic prop and a static sibling prop moves entirely to the
    // dynamic pass, rather than being partially split between passes.
    $mixed_style = [
        'e-mixed1' => [
            'id' => 'e-mixed1',
            'variants' => [
                [
                    'props' => [
                        'border-radius' => ['$$type' => 'size', 'value' => ['size' => 11, 'unit' => 'px']],
                        'background' => ['$$type' => 'dynamic', 'value' => ['name' => 'featured-image']],
                    ],
                ],
            ],
        ],
    ];

    $renderer = new FixtureAtomicStylesRenderer();
    $renderer->fixture_element_styles = [$mixed_style];

    $dynamic_result = $renderer->collect_styles_for_test(1, true);
    $base_result = $renderer->collect_styles_for_test(1, false);

    return $dynamic_result === $mixed_style && $base_result === [];
});

check('AtomicStylesRenderer merges multiple elements\' styles, each routed independently', static function () {
    $dynamic_style = ['e-dyn2' => ['id' => 'e-dyn2', 'variants' => [['props' => ['background' => ['$$type' => 'dynamic', 'value' => []]]]]]];
    $static_style = ['e-static2' => ['id' => 'e-static2', 'variants' => [['props' => ['color' => ['$$type' => 'color', 'value' => '#fff']]]]]];

    $renderer = new FixtureAtomicStylesRenderer();
    $renderer->fixture_element_styles = [$dynamic_style, $static_style];

    $dynamic_result = $renderer->collect_styles_for_test(1, true);
    $base_result = $renderer->collect_styles_for_test(1, false);

    return $dynamic_result === $dynamic_style && $base_result === $static_style;
});

/* ── Core\Rendering\ClassicCssRenderer (Elementor classic CSS/dynamic-tag
     classes not loaded) ──────────────────────────────────────────── */

check('ClassicCssRenderer::render_base() degrades to a DELIVERY_NONE outcome when Post_CSS is unavailable', static function () {
    return (new ClassicCssRenderer())->render_base(101) === ['type' => ClassicCssRenderer::DELIVERY_NONE, 'content' => ''];
});

check('ClassicCssRenderer::render_dynamic() degrades to empty string when Dynamic_CSS is unavailable', static function () {
    return (new ClassicCssRenderer())->render_dynamic(101, 202, '.scope') === '';
});

check('ClassicCssRenderer::has_dynamic_settings() degrades to false when the Dynamic Tags manager capability is absent (checked via an injected double, independent of this harness\'s own ambient stub state)', static function () {
    $renderer = new ClassicCssRenderer(new class extends \Elemacy\Elementor\Compatibility\Capabilities {
        public function dynamic_tags_manager(): bool
        {
            return false;
        }
    });

    return $renderer->has_dynamic_settings(101) === false;
});

check('ClassicCssRenderer::has_dynamic_settings() returns false for a post with no registered Elementor document (this harness\'s Documents_Manager stub has no entry for post 101)', static function () {
    return (new ClassicCssRenderer())->has_dynamic_settings(101) === false;
});

check('ClassicCssRenderer::suppress_automatic_dynamic_css() makes elementor/css-file/dynamic/should_enqueue return false only for the registered post id', static function () {
    (new ClassicCssRenderer())->suppress_automatic_dynamic_css(64);

    $suppressed = apply_filters('elementor/css-file/dynamic/should_enqueue', true, 64);
    $unrelated = apply_filters('elementor/css-file/dynamic/should_enqueue', true, 999);

    return false === $suppressed && true === $unrelated;
});

check('ClassicCssRenderer::suppress_automatic_dynamic_css() hooks the filter only once, even across multiple post ids', static function () {
    // add_filter() shares add_action()'s __wp_actions storage in this stub
    // harness — [hook][priority] is a list of registered callbacks.
    $hook = 'elementor/css-file/dynamic/should_enqueue';
    $count = static fn () => count($GLOBALS['__wp_actions'][$hook][10] ?? []);

    $renderer = new ClassicCssRenderer();
    $before = $count();

    $renderer->suppress_automatic_dynamic_css(111);
    $renderer->suppress_automatic_dynamic_css(111);
    $renderer->suppress_automatic_dynamic_css(222);

    // Whatever the starting count (0 if this is the first filter test to run,
    // 1 if a prior check already hooked it), three more suppress_...() calls
    // — including a repeat of the same id — must add at most one callback.
    $added = $count() - $before;

    $both_suppressed = false === apply_filters($hook, true, 111)
        && false === apply_filters($hook, true, 222);

    return $added <= 1 && $both_suppressed;
});

/* ── Core\Integrations\Elementor\Capabilities / AtomicElementsAdapter /
     StylesRendererAdapter (task 9.2 — design.md D9) ─────────────────── */

check('Capabilities reports the exact ambient state of this harness: \Elementor\Plugin, Core\DynamicTags\Manager and Atomic Dynamic_Prop_Type stubbed by elementor-stubs.php (required above) — the three genuinely core/faithfully-stubbed pieces — every genuinely optional Atomic Widgets internal still absent', static function () {
    $capabilities = new Capabilities();

    return true === $capabilities->plugin_loaded()
        && true === $capabilities->dynamic_tags_manager()
        && true === $capabilities->atomic_dynamic_prop_type()
        && false === $capabilities->classic_css_files()
        && false === $capabilities->dynamic_css()
        && false === $capabilities->atomic_widgets()
        && false === $capabilities->atomic_global_classes()
        && false === $capabilities->atomic_styles_renderer();
});

/**
 * A Capabilities double reporting every capability absent regardless of what
 * classes actually happen to be loaded in this process — proves the two
 * adapters' own degradation branches are driven by the injected Capabilities
 * contract, not incidentally by this particular harness's ambient stub set
 * (the tests above already cover that ambient case for the real Capabilities
 * class itself).
 */
final class AllCapabilitiesAbsent extends Capabilities
{
    public function plugin_loaded(): bool
    {
        return false;
    }

    public function classic_css_files(): bool
    {
        return false;
    }

    public function dynamic_css(): bool
    {
        return false;
    }

    public function dynamic_tags_manager(): bool
    {
        return false;
    }

    public function atomic_widgets(): bool
    {
        return false;
    }

    public function atomic_global_classes(): bool
    {
        return false;
    }

    public function atomic_styles_renderer(): bool
    {
        return false;
    }

    public function atomic_dynamic_prop_type(): bool
    {
        return false;
    }
}

check('AtomicElementsAdapter::available() is false when the injected Capabilities reports the Atomic capabilities absent', static function () {
    return false === (new AtomicElementsAdapter(new AllCapabilitiesAbsent()))->available();
});

check('AtomicElementsAdapter::collect_element_styles() degrades to an empty array when unavailable, never calling into the (here, genuinely absent) Elementor classes', static function () {
    return [] === (new AtomicElementsAdapter(new AllCapabilitiesAbsent()))->collect_element_styles(101);
});

check('ElementTreeWalker::walk() invokes its callback zero times when Elementor is not loaded', static function () {
    $calls = 0;
    (new ElementTreeWalker(new AllCapabilitiesAbsent()))->walk(101, static function () use (&$calls) {
        $calls++;
    });

    return 0 === $calls;
});

check('ElementTreeWalker::walk() degrades to a no-op, not a fatal, when Plugin exists but its documents/db shape is incomplete', static function () {
    // A bare stdClass in place of documents/db has neither get() nor
    // iterate_data() — exactly the "test stub omits it" case this class's
    // own docblock names, proven here without depending on ambient ordering
    // in this file (a later section legitimately gives the stub Documents_
    // Manager a real get()).
    $plugin = \Elementor\Plugin::instance();
    $previous_documents = $plugin->documents;
    $previous_db = $plugin->db;
    $plugin->documents = new stdClass();
    $plugin->db = new stdClass();

    $calls = 0;

    try {
        (new ElementTreeWalker())->walk(101, static function () use (&$calls) {
            $calls++;
        });
    } finally {
        $plugin->documents = $previous_documents;
        $plugin->db = $previous_db;
    }

    return 0 === $calls;
});

check('ElementTreeWalker::walk() finds a real classic dynamic-tag-bound setting through Elementor\'s core Documents_Manager/Document/DB stubs, with no Atomic Widgets class involved — the exact scenario task 9.3 decouples has_dynamic_settings() to support', static function () {
    $post_id = 9101;
    $document = new \Elementor\Core\Base\Document($post_id);
    $document->elements_data = [[
        'id' => 'a',
        'elType' => 'widget',
        'settings' => ['title' => 'static'],
        'elements' => [[
            'id' => 'b',
            'elType' => 'widget',
            'settings' => [\Elementor\Core\DynamicTags\Manager::DYNAMIC_SETTING_KEY => ['title' => 'elemacy-post-title']],
            'elements' => [],
        ]],
    ]];
    \Elementor\Plugin::instance()->documents->by_id[$post_id] = $document;

    $found_settings = [];
    (new ElementTreeWalker())->walk($post_id, function (array $element_data) use (&$found_settings) {
        $found_settings[] = $element_data['settings'];
    });

    $has_dynamic_binding = false;
    foreach ($found_settings as $settings) {
        if (!empty($settings[\Elementor\Core\DynamicTags\Manager::DYNAMIC_SETTING_KEY])) {
            $has_dynamic_binding = true;
        }
    }

    // Both elements (outer widget and its nested child) must have been
    // visited — proves recursion into 'elements', not just the top level.
    return 2 === count($found_settings) && true === $has_dynamic_binding;
});

check('ClassicCssRenderer::has_dynamic_settings() returns true for a real classic dynamic-tag binding found through the classic-owned ElementTreeWalker (no Atomic Widgets class exists anywhere in this process)', static function () {
    $post_id = 9102;
    $document = new \Elementor\Core\Base\Document($post_id);
    $document->elements_data = [[
        'id' => 'c',
        'elType' => 'widget',
        'settings' => [\Elementor\Core\DynamicTags\Manager::DYNAMIC_SETTING_KEY => ['heading' => 'elemacy-post-title']],
        'elements' => [],
    ]];
    \Elementor\Plugin::instance()->documents->by_id[$post_id] = $document;

    return true === (new ClassicCssRenderer())->has_dynamic_settings($post_id);
});

check('ClassicCssRenderer::has_dynamic_settings() returns false for a real classic document with no dynamic-tag binding anywhere', static function () {
    $post_id = 9103;
    $document = new \Elementor\Core\Base\Document($post_id);
    $document->elements_data = [[
        'id' => 'd',
        'elType' => 'widget',
        'settings' => ['heading' => 'plain static text'],
        'elements' => [],
    ]];
    \Elementor\Plugin::instance()->documents->by_id[$post_id] = $document;

    return false === (new ClassicCssRenderer())->has_dynamic_settings($post_id);
});

check('AtomicElementsAdapter::contains_dynamic_value() falls back to plain structural recursion (never a fatal) when the Dynamic_Prop_Type capability is absent', static function () {
    $adapter = new AtomicElementsAdapter(new AllCapabilitiesAbsent());

    // A value that WOULD be detected as dynamic by the real/stubbed
    // Dynamic_Prop_Type::is_dynamic_prop_value() ($$type === 'dynamic') is
    // invisible to the adapter once that capability is reported absent —
    // proving the short-circuit is skipped entirely, not silently retried.
    $would_be_dynamic_if_capability_present = ['$$type' => 'dynamic'];
    $genuinely_static = ['color' => 'red'];

    return false === $adapter->contains_dynamic_value($would_be_dynamic_if_capability_present)
        && false === $adapter->contains_dynamic_value($genuinely_static);
});

check('StylesRendererAdapter::available() is false when the injected Capabilities reports the styles-renderer capability absent', static function () {
    return false === (new StylesRendererAdapter(new AllCapabilitiesAbsent()))->available();
});

check('StylesRendererAdapter::render() degrades to an empty string when unavailable, for a non-empty styles array', static function () {
    return '' === (new StylesRendererAdapter(new AllCapabilitiesAbsent()))->render(['some' => 'style'], '.scope');
});

check('Elementor/Rendering renderers no longer call the Elementor internal APIs Capabilities/AtomicElementsAdapter/StylesRendererAdapter now own directly — task 9.2\'s own verify clause', static function () {
    // Each needle is the exact literal text a removed guard used to contain,
    // written with real single backslashes exactly as it appears in the
    // .php source files on disk (a single-quoted PHP string leaves \E, \C,
    // etc. untouched — only \\ and \' are special).
    $removed_guards = [
        "class_exists('\Elementor\Plugin')",
        "class_exists('\Elementor\Core\Files\CSS\Post')",
        "class_exists('\Elementor\Core\DynamicTags\Dynamic_CSS')",
        "class_exists('\Elementor\Core\DynamicTags\Manager')",
        "class_exists('\Elementor\Modules\AtomicWidgets\Utils\Utils')",
        "class_exists('\Elementor\Modules\GlobalClasses\Utils\Atomic_Elements_Utils')",
        "class_exists('\Elementor\Modules\AtomicWidgets\Styles\Styles_Renderer')",
        "class_exists('\Elementor\Modules\AtomicWidgets\DynamicTags\Dynamic_Prop_Type')",
    ];

    // ClassicCssRenderer.php still legitimately *uses* Dynamic_CSS/Files\CSS\Post
    // as concrete return types/instances once its own Capabilities guard has
    // already passed (that ownership does not move — only the existence
    // *probe* does); this check targets the removed class_exists() guard
    // pattern specifically, not every mention of the class name.
    $files = [
        dirname(__DIR__, 2) . '/src/Elementor/Rendering/TemplateRenderer.php',
        dirname(__DIR__, 2) . '/src/Elementor/Rendering/TemplateAssetsRegistrar.php',
        dirname(__DIR__, 2) . '/src/Elementor/Rendering/AtomicStylesRenderer.php',
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($file);

        if (false === $contents) {
            return false;
        }

        foreach ($removed_guards as $needle) {
            if (false !== strpos($contents, $needle)) {
                return false;
            }
        }
    }

    return true;
});

check('ClassicCssRenderer.php no longer references any Atomic Widgets class at all — task 9.3\'s own verify clause (design.md D9\'s "accidental dependency" fully removed, not merely re-guarded)', static function () {
    $contents = file_get_contents(dirname(__DIR__, 2) . '/src/Elementor/Rendering/ClassicCssRenderer.php');

    if (false === $contents) {
        return false;
    }

    return false === strpos($contents, 'AtomicWidgets')
        && false === strpos($contents, 'AtomicElementsAdapter');
});

/* ── Loop\LoopItemStyles ──────────────────────── */

/**
 * Stand-ins that skip Elementor entirely and let each test control exactly
 * what the two renderers return, so LoopItemStyles's own orchestration
 * (merging vs. splitting the base CSS output, per-request idempotency, the
 * dynamic-content cache, and the no-op-when-nothing-to-print guard) is what's
 * under test here — the real CSS and link/inline decisions these renderers
 * produce are covered by ClassicCssRenderer's and AtomicStylesRenderer's own
 * tests (and were verified live against the real Elementor installation; see
 * openspec/changes/cache-loop-base-css/design.md).
 */
final class FakeClassicCssRenderer extends ClassicCssRenderer
{
    // Defaults to DELIVERY_INLINE so every pre-existing test that only ever
    // sets $base_css (never $base_type) keeps exercising the same "here is
    // literal CSS text" outcome it always has.
    public string $base_type = self::DELIVERY_INLINE;
    public string $base_css = '';
    public string $dynamic_css = '';
    public bool $has_dynamic = false;
    /** @var array<int, array{0:int,1:string,2:string}> */
    public array $dynamic_calls = [];
    public int $has_dynamic_calls = 0;
    /** @var array<int, int> */
    public array $suppress_calls = [];

    public function render_base(int $post_id): array
    {
        return ['type' => $this->base_type, 'content' => $this->base_css];
    }

    public function render_dynamic(int $template_id, string $item_identity, string $selector_prefix): string
    {
        $this->dynamic_calls[] = [$template_id, $item_identity, $selector_prefix];

        return $this->dynamic_css;
    }

    public function has_dynamic_settings(int $post_id): bool
    {
        $this->has_dynamic_calls++;

        return $this->has_dynamic;
    }

    public function suppress_automatic_dynamic_css(int $post_id): void
    {
        // Spy only — never touches the real, shared suppression registry,
        // keeping these tests isolated from ClassicCssRenderer's own.
        $this->suppress_calls[] = $post_id;
    }
}

final class FakeAtomicStylesRenderer extends AtomicStylesRenderer
{
    public string $base_css = '';
    public string $dynamic_css = '';
    public bool $has_dynamic = false;
    /** @var array<int, int> */
    public array $render_base_calls = [];
    /** @var array<int, array{0:int,1:string}> */
    public array $render_dynamic_calls = [];
    public int $has_dynamic_calls = 0;

    public function render_base(int $post_id): string
    {
        $this->render_base_calls[] = $post_id;

        return $this->base_css;
    }

    public function render_dynamic(int $post_id, string $selector_prefix): string
    {
        $this->render_dynamic_calls[] = [$post_id, $selector_prefix];

        return $this->dynamic_css;
    }

    public function has_dynamic_styles(int $post_id): bool
    {
        $this->has_dynamic_calls++;

        return $this->has_dynamic;
    }
}

check('LoopItemStyles::print_base_css() echoes classic and atomic base CSS wrapped in one style tag', static function () {
    $classic = new FakeClassicCssRenderer();
    $classic->base_css = '.a{color:red;}';
    $atomic = new FakeAtomicStylesRenderer();
    $atomic->base_css = '.b{color:blue;}';

    ob_start();
    (new LoopItemStyles($classic, $atomic))->print_base_css(9001);
    $output = ob_get_clean();

    return $output === '<style id="elemacy-loop-base-9001">.a{color:red;}.b{color:blue;}</style>';
});

check('LoopItemStyles::print_base_css() prints nothing when both renderers return empty CSS', static function () {
    ob_start();
    (new LoopItemStyles(new FakeClassicCssRenderer(), new FakeAtomicStylesRenderer()))->print_base_css(9002);
    $output = ob_get_clean();

    return $output === '';
});

check('LoopItemStyles::print_base_css() only prints once per template id per request', static function () {
    $classic = new FakeClassicCssRenderer();
    $classic->base_css = '.a{color:red;}';

    $styles = new LoopItemStyles($classic, new FakeAtomicStylesRenderer());

    ob_start();
    $styles->print_base_css(9003);
    $styles->print_base_css(9003);
    $output = ob_get_clean();

    return 1 === substr_count($output, '<style');
});

check('LoopItemStyles::print_base_css() links the classic base CSS and prints atomic CSS separately when the classic renderer reports DELIVERY_LINK', static function () {
    $classic = new FakeClassicCssRenderer();
    $classic->base_type = ClassicCssRenderer::DELIVERY_LINK;
    $classic->base_css = 'https://example.test/wp-content/uploads/elementor/css/post-9010.css';
    $atomic = new FakeAtomicStylesRenderer();
    $atomic->base_css = '.b{color:blue;}';

    ob_start();
    (new LoopItemStyles($classic, $atomic))->print_base_css(9010);
    $output = ob_get_clean();

    return $output === '<link rel="stylesheet" id="elemacy-loop-base-9010" '
        . 'href="https://example.test/wp-content/uploads/elementor/css/post-9010.css">'
        . '<style id="elemacy-loop-base-atomic-9010">.b{color:blue;}</style>';
});

check('LoopItemStyles::print_base_css() links the classic base CSS alone when there is no atomic CSS to print', static function () {
    $classic = new FakeClassicCssRenderer();
    $classic->base_type = ClassicCssRenderer::DELIVERY_LINK;
    $classic->base_css = 'https://example.test/wp-content/uploads/elementor/css/post-9011.css';

    ob_start();
    (new LoopItemStyles($classic, new FakeAtomicStylesRenderer()))->print_base_css(9011);
    $output = ob_get_clean();

    return $output === '<link rel="stylesheet" id="elemacy-loop-base-9011" '
        . 'href="https://example.test/wp-content/uploads/elementor/css/post-9011.css">';
});

check('LoopItemStyles::print_base_css() merges atomic CSS into the shared style tag, not a link, when the classic renderer reports DELIVERY_NONE', static function () {
    $classic = new FakeClassicCssRenderer();
    $classic->base_type = ClassicCssRenderer::DELIVERY_NONE;
    $classic->base_css = ''; // DELIVERY_NONE always carries no content
    $atomic = new FakeAtomicStylesRenderer();
    $atomic->base_css = '.b{color:blue;}';

    ob_start();
    (new LoopItemStyles($classic, $atomic))->print_base_css(9012);
    $output = ob_get_clean();

    return $output === '<style id="elemacy-loop-base-9012">.b{color:blue;}</style>';
});

check('LoopItemStyles::print_item_css() is a no-op when the template has no dynamic content', static function () {
    $classic = new FakeClassicCssRenderer();
    $atomic = new FakeAtomicStylesRenderer();

    ob_start();
    (new LoopItemStyles($classic, $atomic))->print_item_css(9004, 55, 'scoped-55');
    $output = ob_get_clean();

    return '' === $output && [] === $classic->dynamic_calls && [] === $atomic->render_dynamic_calls;
});

check('LoopItemStyles::print_item_css() prints scoped CSS from both renderers when the template has dynamic content, keyed by the RENDER selector, not the raw item identity', static function () {
    $classic = new FakeClassicCssRenderer();
    $classic->has_dynamic = true;
    $classic->dynamic_css = '.c{color:green;}';
    $atomic = new FakeAtomicStylesRenderer();
    $atomic->dynamic_css = '.d{color:yellow;}';

    ob_start();
    // A deliberately DIFFERENT render selector than the raw item identity —
    // proving the output (style id/selector) is scoped by render identity
    // (design.md D8), while the raw item identity still reaches
    // ClassicCssRenderer's own internal token-matching unchanged.
    (new LoopItemStyles($classic, $atomic))->print_item_css(9005, 77, 'scoped-77');
    $output = ob_get_clean();

    return $output === '<style id="elemacy-loop-item-scoped-77">.c{color:green;}.d{color:yellow;}</style>'
        && $classic->dynamic_calls === [[9005, '77', '.elemacy-loop-item-scoped-77']]
        && $atomic->render_dynamic_calls === [[9005, '.elemacy-loop-item-scoped-77']];
});

check('LoopItemStyles::print_base_css() suppresses Elementor\'s automatic dynamic CSS for the template before anything else runs', static function () {
    $classic = new FakeClassicCssRenderer();
    $atomic = new FakeAtomicStylesRenderer();

    ob_start();
    (new LoopItemStyles($classic, $atomic))->print_base_css(9007);
    ob_get_clean();

    return $classic->suppress_calls === [9007];
});

check('LoopItemStyles caches has_dynamic_content() per template id across multiple items', static function () {
    // classic false, atomic true: forces the || in has_dynamic_content() to
    // actually evaluate both sides (a classic-true short-circuit would never
    // call the atomic side at all), so this also proves atomic content alone
    // is enough to turn dynamic printing on.
    $classic = new FakeClassicCssRenderer();
    $atomic = new FakeAtomicStylesRenderer();
    $atomic->has_dynamic = true;

    $styles = new LoopItemStyles($classic, $atomic);

    ob_start();
    $styles->print_item_css(9006, 1, 'scoped-1');
    $styles->print_item_css(9006, 2, 'scoped-2');
    $styles->print_item_css(9006, 3, 'scoped-3');
    ob_get_clean();

    return 1 === $classic->has_dynamic_calls && 1 === $atomic->has_dynamic_calls;
});

/* ── ThemeBuilder single/archive/search/404 content wrapper ──────── */

/**
 * Real theme-builder-wrapper.php, required fresh per test: it's a plain
 * top-level script (no declarations), so re-requiring it re-runs get_header(),
 * the wp_body_open guard, and get_footer() each time.
 */
$theme_builder_wrapper_path = dirname(__DIR__, 2) . '/src/Modules/ThemeBuilder/Views/theme-builder-wrapper.php';

function reset_wrapper_hooks(): void
{
    unset($GLOBALS['__wp_actions']['get_header'], $GLOBALS['__wp_actions']['get_footer'], $GLOBALS['__locate_template_missing']);
    $GLOBALS['__wp_actions_fired']['wp_body_open'] = 0;
}

check('wrapper fires wp_body_open when the theme header does not (deprecated theme-compat fallback)', static function () use ($theme_builder_wrapper_path) {
    reset_wrapper_hooks();
    // No listener on 'get_header' — mirrors WordPress's deprecated
    // wp-includes/theme-compat/header.php, which never calls wp_body_open().

    ob_start();
    require $theme_builder_wrapper_path;
    ob_get_clean();

    return did_action('wp_body_open') === 1;
});

check('wrapper does not double-fire wp_body_open when the theme header already fired it', static function () use ($theme_builder_wrapper_path) {
    reset_wrapper_hooks();
    add_action('get_header', static function () {
        wp_body_open(); // simulates a modern theme's header.php calling it directly
    });

    ob_start();
    require $theme_builder_wrapper_path;
    ob_get_clean();

    return did_action('wp_body_open') === 1;
});

/* ── ThemeBuilder\Compatibility\Themes\ClassicThemeCompatibility ───────── */

final class FixedChromeThemeBuilderManager extends ThemeBuilderManager
{
    /** @var int|null */
    public $header_id = 20;
    /** @var int|null */
    public $footer_id = 21;

    public function __construct()
    {
        parent::__construct(
            \Elemacy\Modules\ThemeBuilder\Compatibility\CompatibilityManager::instance(),
            \Elemacy\Modules\ThemeBuilder\Services\LocationRegistry::instance(),
            \Elemacy\TemplateLibrary\TemplateResolver::instance(),
            TypeRegistry::instance(),
            new TemplateRenderer()
        );
    }

    public function get_header_id()
    {
        return $this->header_id;
    }

    public function get_footer_id()
    {
        return $this->footer_id;
    }
}

/**
 * The real shells are full document views (tested against real WordPress in
 * tests/integration/theme-lifecycle.php). This records when one would print,
 * and which shell hooks still had callbacks at that moment.
 */
final class RecordingClassicThemeCompatibility extends ClassicThemeCompatibility
{
    /** @var string[] */
    public $log = [];

    protected function print_shell_open(): void
    {
        $this->log[] = 'shell:header:' . $this->live_shell_hooks();
        echo '<shell-header>';
    }

    protected function print_template(string $side): void
    {
        echo "<template-$side>";
    }

    protected function print_shell_close(): void
    {
        $this->log[] = 'shell:footer:' . $this->live_shell_hooks();
        echo '<shell-footer>';
    }

    private function live_shell_hooks(): string
    {
        return implode(',', array_filter(['wp_head', 'wp_body_open', 'wp_footer'], static fn(string $hook): bool => !empty($GLOBALS['__wp_actions'][$hook])));
    }
}

function reset_classic_compat_hooks(): void
{
    foreach (['get_header', 'get_footer', 'wp_head', 'wp_body_open', 'wp_footer', Hooks::THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER] as $hook) {
        unset($GLOBALS['__wp_actions'][$hook]);
    }
    unset($GLOBALS['__locate_template_stub'], $GLOBALS['__locate_template_missing']);
    $GLOBALS['__wp_actions_fired']['wp_body_open'] = 0;
}

/** A third-party callback on every shell hook, as another plugin would register. */
function add_third_party_shell_callbacks(): void
{
    foreach (['wp_head', 'wp_body_open', 'wp_footer'] as $hook) {
        add_action($hook, static function () {
        });
    }
}

/* ── ThemeBuilderManager: page layout of a location template ───── */

if (!class_exists('Elementor\\Modules\\PageTemplates\\Module')) {
    /** Elementor's page-templates module, reduced to the constants the manager compares against. */
    final class ElementorPageTemplatesConstants
    {
        public const TEMPLATE_CANVAS = 'elementor_canvas';
        public const TEMPLATE_HEADER_FOOTER = 'elementor_header_footer';
    }
    class_alias('ElementorPageTemplatesConstants', 'Elementor\\Modules\\PageTemplates\\Module');
}

/** A module standing in for Elementor's: records the print callback it is given. */
class RecordingPageTemplatesModule
{
    /** @var callable|null */
    public $print_callback = null;

    public function get_template_path($layout)
    {
        return 'elementor_canvas' === $layout ? '/elementor/templates/canvas.php' : '';
    }

    public function set_print_callback($callback)
    {
        $this->print_callback = $callback;
    }
}

/** Exposes the layout decisions of the manager without a WordPress request. */
final class LayoutProbeThemeBuilderManager extends ThemeBuilderManager
{
    /** @var object|null */
    public $module = null;
    /** @var array<int, array{int, string}> */
    public $rendered = [];

    public function __construct()
    {
        parent::__construct(
            \Elemacy\Modules\ThemeBuilder\Compatibility\CompatibilityManager::instance(),
            \Elemacy\Modules\ThemeBuilder\Services\LocationRegistry::instance(),
            \Elemacy\TemplateLibrary\TemplateResolver::instance(),
            TypeRegistry::instance(),
            new TemplateRenderer()
        );
    }

    protected function page_templates_module()
    {
        return $this->module;
    }

    public function render_template($post_id, string $placement)
    {
        $this->rendered[] = [$post_id, $placement];
    }

    public function canvas(int $id): bool
    {
        return $this->uses_canvas_layout($id);
    }

    public function full_width(int $id): bool
    {
        return $this->uses_full_width_layout($id);
    }

    public function canvas_template(int $id): ?string
    {
        return $this->canvas_template_for($id);
    }
}

check('only Elementor Canvas counts as a Canvas layout; Full Width, Default and no layout do not', static function () {
    $manager = new LayoutProbeThemeBuilderManager();
    foreach ([['elementor_canvas', true], ['elementor_header_footer', false], ['default', false], ['', false], ['page-named.php', false]] as $i => [$layout, $canvas]) {
        $id = 700 + $i;
        update_post_meta($id, '_wp_page_template', $layout);
        if ($canvas !== $manager->canvas($id)) {
            return false;
        }
    }

    return false === $manager->canvas(799); // a template with no layout meta at all
});

check('only Elementor Full Width counts as a Full Width layout', static function () {
    $manager = new LayoutProbeThemeBuilderManager();
    foreach ([['elementor_header_footer', true], ['elementor_canvas', false], ['default', false], ['', false]] as $i => [$layout, $full_width]) {
        $id = 710 + $i;
        update_post_meta($id, '_wp_page_template', $layout);
        if ($full_width !== $manager->full_width($id)) {
            return false;
        }
    }

    return true;
});

check('a Canvas location template is handed to Elementor\'s Canvas file with a callback that prints the location', static function () {
    $manager = new LayoutProbeThemeBuilderManager();
    $manager->module = new RecordingPageTemplatesModule();

    $path = $manager->canvas_template(42);
    ($manager->module->print_callback)();

    return '/elementor/templates/canvas.php' === $path && [[42, 'location']] === $manager->rendered;
});

check('without Elementor\'s page-templates module a Canvas template falls back to the wrapper instead of a blank page', static function () {
    $manager = new LayoutProbeThemeBuilderManager();

    return null === $manager->canvas_template(42);
});

check('when Elementor gives no Canvas file the print callback is not set and the wrapper is used', static function () {
    $manager = new LayoutProbeThemeBuilderManager();
    $manager->module = new class extends RecordingPageTemplatesModule {
        public function get_template_path($layout)
        {
            return '';
        }
    };

    return null === $manager->canvas_template(42) && null === $manager->module->print_callback;
});

check('a side is replaced when a template applies to it and the site has not opted out', static function () {
    reset_classic_compat_hooks();
    $manager = new FixedChromeThemeBuilderManager();

    return true === $manager->replaces_native_chrome('header') && true === $manager->replaces_native_chrome('footer');
});

check('a side with no template is not replaced, and the opt-out is not even asked about it', static function () {
    reset_classic_compat_hooks();
    $asked = [];
    add_filter(Hooks::THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER, static function (bool $suppress, string $side) use (&$asked): bool {
        $asked[] = $side;

        return $suppress;
    }, 10, 2);
    $manager = new FixedChromeThemeBuilderManager();
    $manager->footer_id = null;

    return true === $manager->replaces_native_chrome('header') && false === $manager->replaces_native_chrome('footer') && ['header'] === $asked;
});

check('opting out of one side leaves the other replaced', static function () {
    reset_classic_compat_hooks();
    add_filter(Hooks::THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER, static fn(bool $suppress, string $side): bool => 'header' === $side ? false : $suppress, 10, 2);
    $manager = new FixedChromeThemeBuilderManager();

    return false === $manager->replaces_native_chrome('header') && true === $manager->replaces_native_chrome('footer');
});

check('the opt-out is asked once per side per page, however many adapters ask', static function () {
    reset_classic_compat_hooks();
    $asked = 0;
    add_filter(Hooks::THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER, static function (bool $suppress) use (&$asked): bool {
        $asked++;

        return $suppress;
    }, 10, 2);
    $manager = new FixedChromeThemeBuilderManager();
    for ($i = 0; $i < 5; $i++) {
        $manager->replaces_native_chrome('header');
        $manager->replaces_native_chrome('footer');
    }
    $once = $asked;
    $manager->forget_rendered_locations();
    $manager->replaces_native_chrome('header');

    return 2 === $once && 3 === $asked;
});

check('no theme adapter empties a hook another plugin may share; only the generic shell neutralisation does', static function () {
    $directory = ELEMACY_PATH . 'src/Modules/ThemeBuilder/Compatibility/Themes/';
    foreach (glob($directory . '*.php') ?: [] as $file) {
        if ('ClassicThemeCompatibility.php' === basename($file)) {
            continue;
        }
        if (false !== strpos(elemacy_code_without_comments($file), 'remove_all_actions')) {
            return false;
        }
    }

    return true;
});

check('every adapter asks the manager whether a side is replaced, never the template id', static function () {
    $directory = ELEMACY_PATH . 'src/Modules/ThemeBuilder/Compatibility/';
    foreach (array_merge(glob($directory . 'Themes/*.php') ?: [], [$directory . 'CompatibilityManager.php']) as $file) {
        $source = elemacy_code_without_comments($file);
        if (false !== strpos($source, 'get_header_id') || false !== strpos($source, 'get_footer_id')) {
            return false;
        }
        if (false !== strpos($source, 'THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER')) {
            return false;
        }
    }

    return true;
});

check('native chrome suppression defaults to on for both sides', static function () {
    reset_classic_compat_hooks();

    (new ClassicThemeCompatibility())->register(new FixedChromeThemeBuilderManager());

    return !empty($GLOBALS['__wp_actions']['get_header']) && !empty($GLOBALS['__wp_actions']['get_footer']);
});

check('opting one side out of native chrome suppression leaves the other side replaced', static function () {
    reset_classic_compat_hooks();
    $sides = [];
    add_filter(Hooks::THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER, static function (bool $suppress, string $side) use (&$sides): bool {
        $sides[] = $side;

        return 'header' === $side ? false : $suppress;
    }, 10, 2);

    (new ClassicThemeCompatibility())->register(new FixedChromeThemeBuilderManager());

    return $sides === ['header', 'footer']
        && empty($GLOBALS['__wp_actions']['get_header'])
        && !empty($GLOBALS['__wp_actions']['get_footer']);
});

check('a side with no configured template is never replaced, and never asks the opt-out filter', static function () {
    reset_classic_compat_hooks();
    $asked = 0;
    add_filter(Hooks::THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER, static function (bool $suppress) use (&$asked): bool {
        $asked++;

        return $suppress;
    }, 10, 2);
    $manager = new FixedChromeThemeBuilderManager();
    $manager->footer_id = null;

    (new ClassicThemeCompatibility())->register($manager);

    return 1 === $asked
        && !empty($GLOBALS['__wp_actions']['get_header'])
        && empty($GLOBALS['__wp_actions']['get_footer']);
});

check('the generic header replacement prints the shell, then neutralizes the head and body-open hooks, then discards the theme header', static function () {
    reset_classic_compat_hooks();
    add_third_party_shell_callbacks();
    $compat = new RecordingClassicThemeCompatibility();
    $compat->register(new FixedChromeThemeBuilderManager());
    $GLOBALS['__locate_template_stub'] = static function ($templates) use ($compat) {
        $live = array_filter(['wp_head', 'wp_body_open', 'wp_footer'], static fn(string $hook): bool => !empty($GLOBALS['__wp_actions'][$hook]));
        $compat->log[] = 'discard:' . implode('|', (array) $templates) . ':' . implode(',', $live);
        echo '<native-header>';
    };

    ob_start();
    get_header();
    $html = ob_get_clean();

    return $compat->log === ['shell:header:wp_head,wp_body_open,wp_footer', 'discard:header.php:wp_footer']
        && '<shell-header><template-header>' === $html;
});

check('the generic footer replacement prints the shell, then neutralizes the footer hook, then discards the theme footer', static function () {
    reset_classic_compat_hooks();
    add_third_party_shell_callbacks();
    $compat = new RecordingClassicThemeCompatibility();
    $compat->register(new FixedChromeThemeBuilderManager());
    $GLOBALS['__locate_template_stub'] = static function ($templates) use ($compat) {
        $live = array_filter(['wp_head', 'wp_body_open', 'wp_footer'], static fn(string $hook): bool => !empty($GLOBALS['__wp_actions'][$hook]));
        $compat->log[] = 'discard:' . implode('|', (array) $templates) . ':' . implode(',', $live);
        echo '<native-footer>';
    };

    ob_start();
    get_footer();
    $html = ob_get_clean();

    return $compat->log === ['shell:footer:wp_head,wp_body_open,wp_footer', 'discard:footer.php:wp_head,wp_body_open']
        && '<template-footer><shell-footer>' === $html;
});

/** Renders one generic replacement whose discarded theme file prints $discarded. */
function generic_replacement_html(string $side, string $discarded): string
{
    reset_classic_compat_hooks();
    $compat = new RecordingClassicThemeCompatibility();
    $compat->register(new FixedChromeThemeBuilderManager());
    $GLOBALS['__locate_template_stub'] = static function () use ($discarded) {
        echo $discarded;
    };

    ob_start();
    'header' === $side ? get_header() : get_footer();

    return ob_get_clean();
}

check('the generic header replacement prints the wrappers its discarded header opened around the Elemacy template', static function () {
    return '<shell-header><div id="page"><template-header><div id="content">'
        === generic_replacement_html('header', '<div id="page"><a class="skip" href="#">Skip</a><header class="site-header"><nav>menu</nav></header><div id="content">');
});

check('the generic footer replacement prints the wrappers its discarded footer closed around the Elemacy template', static function () {
    return '</div><template-footer></div><shell-footer>'
        === generic_replacement_html('footer', '</div><footer class="site-footer">credits</footer></div>');
});

check('the generic replacement prints no wrappers when the discarded file cannot be read', static function () {
    return '<shell-header><template-header>' === generic_replacement_html('header', '<div id="page"><a href="')
        && '<template-footer><shell-footer>' === generic_replacement_html('footer', '</div><footer><nav></footer>');
});

check('the generic replacement leaves no output buffer open, even when the theme file throws', static function () {
    $level = ob_get_level();
    reset_classic_compat_hooks();
    $compat = new RecordingClassicThemeCompatibility();
    $compat->register(new FixedChromeThemeBuilderManager());
    $GLOBALS['__locate_template_stub'] = static function () {
        throw new \RuntimeException('theme file failed');
    };

    $thrown = false;
    ob_start();
    try {
        get_header();
    } catch (\RuntimeException $e) {
        $thrown = true;
    }
    ob_end_clean();

    return $thrown && ob_get_level() === $level;
});

check('a named header is discarded through the same template hierarchy get_header() uses', static function () {
    reset_classic_compat_hooks();
    $compat = new RecordingClassicThemeCompatibility();
    $compat->register(new FixedChromeThemeBuilderManager());
    $located = [];
    $GLOBALS['__locate_template_stub'] = static function ($templates) use (&$located) {
        $located = (array) $templates;
    };

    ob_start();
    get_header('custom');
    ob_end_clean();

    return $located === ['header-custom.php', 'header.php'];
});

/* ── ThemeBuilder\Compatibility\LayoutWrapperReader ────────────── */

/** Opening and closing tags in $html, in order, as 'open:div' / 'close:div'. */
function wrapper_events(string $html): array
{
    preg_match_all('~<(/?)([a-z]+)[^>]*>~i', $html, $m, PREG_SET_ORDER);

    return array_map(static fn(array $t): string => ('/' === $t[1] ? 'close:' : 'open:') . strtolower($t[2]), $m);
}

/** Whether the events open and close in matching order, with nothing left over. */
function wrapper_events_balance(array $events): bool
{
    $stack = [];
    foreach ($events as $event) {
        [$kind, $name] = explode(':', $event);
        if ('open' === $kind) {
            $stack[] = $name;
        } elseif ($name !== array_pop($stack)) {
            return false;
        }
    }

    return [] === $stack;
}

function read_wrappers(string $html, string $side = 'header'): ?array
{
    return (new \Elemacy\Modules\ThemeBuilder\Compatibility\LayoutWrapperReader())->read($html, $side);
}

check('the wrapper reader balances a real theme: what its header leaves open, its footer closes (12 real themes)', static function () {
    $dir = __DIR__ . '/fixtures/theme-chrome/';
    $themes = array_map(static fn(string $f): string => basename($f, '-header.html'), glob($dir . '*-header.html'));
    if (count($themes) < 12) {
        return false;
    }

    foreach ($themes as $theme) {
        $header = read_wrappers(file_get_contents($dir . $theme . '-header.html'), 'header');
        $footer = read_wrappers(file_get_contents($dir . $theme . '-footer.html'), 'footer');
        if (null === $header || null === $footer) {
            return false;
        }
        // The page the theme prints is header, content, footer: its unbalanced tags must close each other.
        $events = array_merge(
            wrapper_events($header['before'] . $header['after']),
            wrapper_events($footer['before'] . $footer['after'])
        );
        if (!wrapper_events_balance($events)) {
            return false;
        }
    }

    return true;
});

check('the wrapper reader returns tags only, never the theme\'s text, navigation or chrome element', static function () {
    $dir = __DIR__ . '/fixtures/theme-chrome/';
    foreach (glob($dir . '*.html') as $file) {
        $result = read_wrappers(file_get_contents($file), false !== strpos($file, '-header') ? 'header' : 'footer');
        foreach ($result ?? [] as $markup) {
            if ('' !== strip_tags($markup) || false !== stripos($markup, '<header') || false !== stripos($markup, '<footer') || false !== stripos($markup, '<script')) {
                return false;
            }
        }
    }

    return true;
});

check('the wrapper reader places Twenty Twenty-One\'s wrappers around where its header and footer sat', static function () {
    $dir = __DIR__ . '/fixtures/theme-chrome/';
    $header = read_wrappers(file_get_contents($dir . 'twentytwentyone-header.html'), 'header');
    $footer = read_wrappers(file_get_contents($dir . 'twentytwentyone-footer.html'), 'footer');

    return '<div id="page" class="site">' === $header['before']
        && '<div id="content" class="site-content"><div id="primary" class="content-area"><main id="main" class="site-main">' === preg_replace('~>\s+<~', '><', $header['after'])
        && '</main></div></div>' === preg_replace('~>\s+<~', '><', $footer['before'])
        && '</div>' === $footer['after'];
});

check('the wrapper reader returns nothing for a theme whose header and footer balance on their own', static function () {
    $dir = __DIR__ . '/fixtures/theme-chrome/';
    foreach (['hello-elementor', 'twentytwenty'] as $theme) {
        foreach (['header', 'footer'] as $side) {
            if (['before' => '', 'after' => ''] !== read_wrappers(file_get_contents($dir . "$theme-$side.html"), $side)) {
                return false;
            }
        }
    }

    return true;
});

check('the wrapper reader ignores tags inside comments, scripts, styles and textareas', static function () {
    return ['before' => '', 'after' => ''] === read_wrappers('<div id="a"><!-- <div> --><script>var s = "<div>";</script><style>/* <main> */</style><textarea><section></textarea></div>');
});

check('the wrapper reader ignores void elements, self-closing tags and the document shell', static function () {
    return ['before' => '', 'after' => ''] === read_wrappers('<!doctype html><html lang="en"><head><meta charset="UTF-8"><link rel="x"><title>t</title></head><body class="b"><div><br><img src="x"><svg><path d="M0"/></svg></div>');
});

check('the wrapper reader accepts elements whose closing tag HTML makes optional', static function () {
    return ['before' => '', 'after' => ''] === read_wrappers('<ul><li>one<li>two</ul><p>unclosed paragraph');
});

check('the wrapper reader keeps a tag whose attribute contains a closing bracket', static function () {
    return ['before' => '<div data-x="a>b" id="page">', 'after' => ''] === read_wrappers('<div data-x="a>b" id="page"><a href="#">skip</a>');
});

check('the wrapper reader puts every wrapper before the template when there is no chrome element', static function () {
    return ['before' => '<div id="page"><div id="content">', 'after' => ''] === read_wrappers('<div id="page"><a href="#">skip</a><div id="content">');
});

check('the wrapper reader splits wrappers around the first header and the last footer element', static function () {
    $header = read_wrappers('<div id="a"><header class="site-header"><div class="inner"></div></header><div id="b">', 'header');
    $footer = read_wrappers('</div><footer class="entry-footer"></footer></div><footer id="colophon"><div></div></footer></div>', 'footer');

    return ['before' => '<div id="a">', 'after' => '<div id="b">'] === $header
        && ['before' => '</div></div>', 'after' => '</div>'] === $footer;
});

check('the wrapper reader gives up on output it cannot read with certainty', static function () {
    foreach ([
        '<div id="page"><a href="',            // a tag cut off
        '<div id="page"><script>var a = 1;',   // a script never closed
        '<div><nav><ul>',                       // a non-wrapper left open
        '<div><span></div>',                    // a close that skips an open element
        '</a><div>',                            // a stray close of a non-wrapper
    ] as $html) {
        if (null !== read_wrappers($html)) {
            return false;
        }
    }

    return true;
});

/** A Hello Elementor request, recording each point where Hello would ask whether to show its native chrome. */
function hello_native_chrome_checks(?int $header_id, ?int $footer_id): array
{
    foreach (['get_header', 'get_footer', 'wp_head', 'wp_body_open', 'hello_elementor_header_footer'] as $hook) {
        unset($GLOBALS['__wp_actions'][$hook]);
    }

    $manager = new FixedChromeThemeBuilderManager();
    $manager->header_id = $header_id;
    $manager->footer_id = $footer_id;
    // Both locations count as already printed, so only the moments Hello asks
    // are exercised and no template is rendered.
    \Closure::bind(function () {
        $this->rendered_locations = ['header' => true, 'footer' => true];
    }, $manager, ThemeBuilderManager::class)();
    (new \Elemacy\Modules\ThemeBuilder\Compatibility\Themes\HelloElementorCompatibility())->register($manager);
    $shown = static fn(): bool => apply_filters('hello_elementor_header_footer', true);

    do_action('get_header');
    $in_head = $shown();
    do_action('wp_body_open');
    $header = $shown();
    do_action('get_footer');

    return ['head' => $in_head, 'header' => $header, 'footer' => $shown()];
}

check('Hello with only a Header template hides its own header and keeps its own footer', static function () {
    return ['head' => true, 'header' => false, 'footer' => true] === hello_native_chrome_checks(20, null);
});

check('Hello with only a Footer template hides its own footer and keeps its own header', static function () {
    return ['head' => true, 'header' => true, 'footer' => false] === hello_native_chrome_checks(null, 21);
});

check('Hello with both templates hides both of its own sides', static function () {
    return ['head' => true, 'header' => false, 'footer' => false] === hello_native_chrome_checks(20, 21);
});

check('the generic adapter carries no styling-based suppression', static function () {
    $source = elemacy_code_without_comments(ELEMACY_PATH . 'src/Modules/ThemeBuilder/Compatibility/Themes/ClassicThemeCompatibility.php');

    foreach (['display: none', 'display:none', '100vw', 'wp_add_inline_style', 'site-header', 'masthead'] as $needle) {
        if (false !== strpos($source, $needle)) {
            return false;
        }
    }

    return true;
});

/* ── Core\RequestState ─────────────────────────────────────────── */

final class CountingElementorFrontend
{
    /** @var int[] */
    public array $rendered = [];

    public function get_builder_content_for_display($id)
    {
        $this->rendered[] = (int) $id;

        return '<counted id="' . (int) $id . '" render="' . count($this->rendered) . '">';
    }
}

function counting_template_renderer(): CountingElementorFrontend
{
    \Elementor\Plugin::$instance = new \Elementor\Plugin();
    \Elementor\Plugin::$instance->frontend = new CountingElementorFrontend();
    TemplateRenderer::reset();

    return \Elementor\Plugin::$instance->frontend;
}

check('RequestState::flush() runs every registered reset, and re-registering a name replaces it', static function () {
    $log = [];
    \Elemacy\Core\RequestState::register('zz-first', static function () use (&$log) {
        $log[] = 'first-old';
    });
    \Elemacy\Core\RequestState::register('zz-first', static function () use (&$log) {
        $log[] = 'first';
    });
    \Elemacy\Core\RequestState::register('zz-second', static function () use (&$log) {
        $log[] = 'second';
    });

    \Elemacy\Core\RequestState::flush();

    \Elemacy\Core\RequestState::register('zz-first', static function () {
    });
    \Elemacy\Core\RequestState::register('zz-second', static function () {
    });

    return $log === ['first', 'second'];
});

check('a TemplateRenderer fragment is reused within a request and prepared again after a RequestState flush', static function () {
    $frontend = counting_template_renderer();
    \Elemacy\Core\RequestState::register(TemplateRenderer::class, [TemplateRenderer::class, 'reset']);
    $renderer = new TemplateRenderer();

    $first = $renderer->prepare(501, 'theme-builder', 'header');
    $again = $renderer->prepare(501, 'theme-builder', 'header');
    $reused = $frontend->rendered === [501] && $first === $again && $renderer->get_prepared(501, 'theme-builder', 'header') === $first;

    \Elemacy\Core\RequestState::flush();

    return $reused
        && null === $renderer->get_prepared(501, 'theme-builder', 'header')
        && $renderer->prepare(501, 'theme-builder', 'header') !== $first
        && $frontend->rendered === [501, 501];
});

check('a fragment prepared on one site is never served on another, and is still there when the first site comes back', static function () {
    $frontend = counting_template_renderer();
    $renderer = new TemplateRenderer();

    $site_one = $renderer->prepare(502, 'theme-builder', 'header');
    switch_to_blog(2);
    $on_site_two = $renderer->get_prepared(502, 'theme-builder', 'header');
    $site_two = $renderer->prepare(502, 'theme-builder', 'header');
    restore_current_blog();

    return null === $on_site_two
        && $site_two !== $site_one
        && $renderer->get_prepared(502, 'theme-builder', 'header') === $site_one
        && $frontend->rendered === [502, 502];
});

check('RequestState flushes as the first wp callback, ahead of every consumer that resolves or prepares a template', static function () {
    unset($GLOBALS['__wp_actions']['wp']);
    $log = [];
    \Elemacy\Core\RequestState::register('zz-order', static function () use (&$log) {
        $log[] = 'flush';
    });
    add_action('wp', static function () use (&$log) {
        $log[] = 'early consumer';
    }, -1000);
    add_action('wp', static function () use (&$log) {
        $log[] = 'adapter boot';
    });
    \Elemacy\Core\RequestState::register_hooks();

    do_action('wp', new stdClass());

    \Elemacy\Core\RequestState::register('zz-order', static function () {
    });
    unset($GLOBALS['__wp_actions']['wp']);

    return $log === ['flush', 'early consumer', 'adapter boot'];
});

check('every request-scoped cache registers its reset with RequestState, and the flush is wired to wp', static function () {
    $registered = [
        'src/Core/Providers/CoreServiceProvider.php' => ['RequestState::register_hooks()', 'RequestState::register(TemplateRenderer::class', 'RequestState::register(LoopItemStyles::class'],
        'src/TemplateLibrary/LibraryBootstrap.php' => ['RequestState::register(TemplateResolver::class'],
        'src/Elementor/Rendering/TemplateAssetsRegistrar.php' => ['RequestState::register(self::class'],
        'src/Modules/ThemeBuilder/Services/ThemeBuilderManager.php' => ["RequestState::register(self::class, [\$this, 'forget_rendered_locations'])"],
        'src/Modules/Popups/Services/PopupManager.php' => ["RequestState::register(self::class, [\$this, 'reset'])"],
    ];

    foreach ($registered as $file => $needles) {
        $source = elemacy_code_without_comments(ELEMACY_PATH . $file);
        foreach ($needles as $needle) {
            if (false === strpos($source, $needle)) {
                return false;
            }
        }
    }

    return false !== strpos(elemacy_code_without_comments(ELEMACY_PATH . 'src/Core/RequestState.php'), "add_action('wp', [self::class, 'flush'], PHP_INT_MIN, 0)");
});

/* ── Core\Capabilities\FeatureAvailability ──────────────────────── */

check('available status carries no reason code or message', static function () {
    $availability = FeatureAvailability::available();

    return $availability->is_available()
        && !$availability->is_unavailable()
        && $availability->get_reason_code() === ''
        && $availability->get_message() === '';
});

check('unknown identifier is unavailable and names the identifier', static function () {
    $availability = FeatureAvailability::unknown_identifier('acme/removed');

    return $availability->is_unavailable()
        && $availability->get_reason_code() === FeatureAvailability::UNKNOWN_IDENTIFIER
        && false !== strpos($availability->get_message(), 'acme/removed');
});

check('inactive extension is unavailable under its own, distinct reason code', static function () {
    $availability = FeatureAvailability::inactive_extension('mock/locked');

    return $availability->is_unavailable()
        && $availability->get_reason_code() === FeatureAvailability::EXTENSION_INACTIVE
        && $availability->get_reason_code() !== FeatureAvailability::UNKNOWN_IDENTIFIER;
});

check('missing client handler is unavailable under its own, distinct reason code', static function () {
    $availability = FeatureAvailability::client_handler_missing('trigger/scroll');

    return $availability->is_unavailable()
        && $availability->get_reason_code() === FeatureAvailability::CLIENT_HANDLER_MISSING
        && $availability->get_reason_code() !== FeatureAvailability::EXTENSION_INACTIVE;
});

check('unavailable is distinguishable from a false predicate even when both evaluate false', static function () {
    // Available, but this request's predicate genuinely does not match.
    $false_predicate = new FixtureCapability(static fn() => false, FeatureAvailability::available());
    // Cannot run at all — a different reason for the same boolean outcome.
    $unavailable = new FixtureCapability(static fn() => false, FeatureAvailability::unknown_identifier('x'));

    return $false_predicate->check() === false
        && $unavailable->check() === false
        && $false_predicate->availability()->is_available()
        && $unavailable->availability()->is_unavailable();
});

check('an empty configured rule set has no unavailable entries', static function () {
    /** @var FixtureCapability[] $rules */
    $rules = [];

    $unavailable = array_values(array_filter(
        $rules,
        static fn(FixtureCapability $c): bool => $c->availability()->is_unavailable()
    ));

    // Nothing configured is not the same as something configured-but-unavailable.
    return $unavailable === [];
});

check('a configured-but-unavailable rule is reported, unlike an empty set', static function () {
    $rules = [
        new FixtureCapability(static fn() => false, FeatureAvailability::unknown_identifier('acme/removed')),
    ];

    $unavailable = array_values(array_filter(
        $rules,
        static fn(FixtureCapability $c): bool => $c->availability()->is_unavailable()
    ));

    return count($unavailable) === 1
        && $unavailable[0]->availability()->get_reason_code() === FeatureAvailability::UNKNOWN_IDENTIFIER;
});

/* ── ConditionEvaluator semantics ───────────────────────────────── */

$manager = ConditionManager::instance();
$manager->register(new FixedCondition('test/yes', true));
$manager->register(new FixedCondition('test/no', false));
$manager->register(new FixedMockCondition());

$evaluator = ConditionEvaluator::instance();

$rule = static function (string $type, string $operator = 'include'): ConditionRuleDTO {
    return ConditionRuleDTO::from_array(['type' => $type, 'operator' => $operator]);
};

check('ConditionManager reports a real, registered condition as available', static fn() =>
    $manager->get_availability('test/yes')->is_available());

check('ConditionManager reports an unregistered identifier as unknown', static function () use ($manager) {
    $availability = $manager->get_availability('test/unregistered');

    return $availability->is_unavailable()
        && $availability->get_reason_code() === FeatureAvailability::UNKNOWN_IDENTIFIER;
});

check('ConditionManager reports a mock stand-in as an inactive extension, not unknown', static function () use ($manager) {
    $availability = $manager->get_availability('mock/locked');

    return $availability->is_unavailable()
        && $availability->get_reason_code() === FeatureAvailability::EXTENSION_INACTIVE;
});

check('no conditions → show everywhere', static fn() => $evaluator->evaluate([]) === true);

check('single matching include → shown', static fn() =>
    $evaluator->evaluate([$rule('test/yes')]) === true);

check('single non-matching include → hidden', static fn() =>
    $evaluator->evaluate([$rule('test/no')]) === false);

check('includes OR together (no + yes → shown)', static fn() =>
    $evaluator->evaluate([$rule('test/no'), $rule('test/yes')]) === true);

check('matching exclude vetoes a matching include', static fn() =>
    $evaluator->evaluate([$rule('test/yes'), $rule('test/yes', 'exclude')]) === false);

check('non-matching exclude alone → show everywhere', static fn() =>
    $evaluator->evaluate([$rule('test/no', 'exclude')]) === true);

check('unknown-only include suspends rather than showing everywhere', static fn() =>
    $evaluator->evaluate([$rule('test/unregistered')]) === false);

check('unknown include suspends even though another real include would match', static fn() =>
    $evaluator->evaluate([$rule('test/unregistered'), $rule('test/yes')]) === false);

check('unknown include suspends the same as a real non-matching include', static fn() =>
    $evaluator->evaluate([$rule('test/unregistered'), $rule('test/no')]) === false);

check('mock (inactive extension) include suspends (pro deactivated → template hidden)', static fn() =>
    $evaluator->evaluate([$rule('mock/locked')]) === false);

check('mock (inactive extension) exclude suspends rather than failing to veto', static fn() =>
    $evaluator->evaluate([$rule('mock/locked', 'exclude'), $rule('test/yes')]) === false);

/* ── Template precedence: Specificity, condition levels, resolver ── */

/** A condition with a fixed match result and a fixed declared specificity. */
final class LeveledCondition extends BaseCondition
{
    private string $name;
    private bool $matches;
    private int $level;

    public function __construct(string $name, bool $matches, int $level)
    {
        $this->name    = $name;
        $this->matches = $matches;
        $this->level   = $level;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_type(): string
    {
        return 'general';
    }

    public function get_label(): string
    {
        return $this->name;
    }

    public function check(ConditionRuleDTO $rule): bool
    {
        return $this->matches;
    }

    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return $this->level;
    }
}

/** A ResolverCache whose candidate index is supplied directly. */
final class FixedResolverCache extends \Elemacy\TemplateLibrary\ResolverCache
{
    private array $index;

    public function __construct(array $index)
    {
        $this->index = $index;
    }

    public function get_index(): array
    {
        return $this->index;
    }
}

check('Specificity levels are distinct and ordered ascending from SITE', static function () {
    $levels = Specificity::levels();

    return $levels === [Specificity::SITE, Specificity::SECTION, Specificity::TYPE, Specificity::OBJECT]
        && count(array_unique($levels)) === 4
        && $levels === array_values(array_filter($levels, 'is_int'))
        && Specificity::SITE === min($levels)
        && Specificity::OBJECT === max($levels)
        && $levels[0] < $levels[1] && $levels[1] < $levels[2] && $levels[2] < $levels[3];
});

check('Specificity::narrowed() returns the narrow level for a value and the broad level for an empty one', static fn() =>
    Specificity::narrowed('12', Specificity::OBJECT, Specificity::TYPE) === Specificity::OBJECT
    && Specificity::narrowed('', Specificity::OBJECT, Specificity::TYPE) === Specificity::TYPE);

check('a condition that never declares a specificity still registers, still matches, and reports the broadest level', static function () use ($manager, $evaluator, $rule) {
    $bare = new FixedCondition('prec/bare', true);
    $manager->register($bare);

    return !method_exists(FixedCondition::class, 'get_specificity')
        || true === $evaluator->evaluate([$rule('prec/bare')])
        && $bare->get_specificity(ConditionRuleDTO::from_array([])) === Specificity::SITE
        && $evaluator->match([$rule('prec/bare')]) === Specificity::SITE;
});

check('every built-in Free condition declares the level the documented table gives it', static function () {
    $empty = ConditionRuleDTO::from_array(['value' => '']);
    $value = ConditionRuleDTO::from_array(['value' => 'page']);

    return (new \Elemacy\Conditions\General\EntireSite())->get_specificity($empty) === Specificity::SITE
        && (new \Elemacy\Conditions\Singular\PostType())->get_specificity($empty) === Specificity::SECTION
        && (new \Elemacy\Conditions\Singular\PostType())->get_specificity($value) === Specificity::TYPE
        && (new \Elemacy\Conditions\Archive\PostType())->get_specificity($empty) === Specificity::SECTION
        && (new \Elemacy\Conditions\Archive\PostType())->get_specificity($value) === Specificity::TYPE;
});

check('every Free mock condition declares the same level its real Pro counterpart will', static function () {
    $empty = ConditionRuleDTO::from_array(['value' => '']);
    $value = ConditionRuleDTO::from_array(['value' => '7']);

    $expected = [
        ['Mock\AllArchives',    $empty, Specificity::SECTION],
        ['Mock\Blog',           $empty, Specificity::TYPE],
        ['Mock\NotFound',       $empty, Specificity::TYPE],
        ['Mock\SearchResults',  $empty, Specificity::TYPE],
        ['Mock\SearchPostType', $empty, Specificity::TYPE],
        ['Mock\SearchPostType', $value, Specificity::OBJECT],
        ['Mock\DateArchive',    $empty, Specificity::TYPE],
        ['Mock\FrontPage',      $empty, Specificity::OBJECT],
        ['Mock\ChildOf',        $value, Specificity::OBJECT],
        ['Mock\AuthorArchive',  $empty, Specificity::TYPE],
        ['Mock\AuthorArchive',  $value, Specificity::OBJECT],
        ['Mock\SpecificPost',   $empty, Specificity::SECTION],
        ['Mock\SpecificPost',   $value, Specificity::OBJECT],
        ['Mock\ByAuthor',       $empty, Specificity::SECTION],
        ['Mock\ByAuthor',       $value, Specificity::OBJECT],
    ];

    foreach ($expected as [$class, $rule_dto, $level]) {
        $fqcn = '\\Elemacy\\Conditions\\' . $class;

        if ((new $fqcn())->get_specificity($rule_dto) !== $level) {
            return false;
        }
    }

    // The two taxonomy-scoped mocks take the taxonomy in their constructor.
    return (new \Elemacy\Conditions\Mock\TaxonomyArchive(new WP_Taxonomy('category', 'Category')))->get_specificity($empty) === Specificity::TYPE
        && (new \Elemacy\Conditions\Mock\TaxonomyArchive(new WP_Taxonomy('category', 'Category')))->get_specificity($value) === Specificity::OBJECT
        && (new \Elemacy\Conditions\Mock\InTaxonomy(new WP_Taxonomy('category', 'Category')))->get_specificity($empty) === Specificity::TYPE
        && (new \Elemacy\Conditions\Mock\InTaxonomy(new WP_Taxonomy('category', 'Category')))->get_specificity($value) === Specificity::OBJECT;
});

/* ── Search Results template conditions ──────────────────────────── */

check('Free\'s Search Results condition matches exactly a search request and ranks at the search level', static function () {
    $all = new \Elemacy\Conditions\Search\AllResults();
    $rule = ConditionRuleDTO::from_array(['type' => 'search/all']);

    $GLOBALS['__wp_is_search'] = true;
    $on_search = $all->check($rule);
    $GLOBALS['__wp_is_search'] = false;
    $elsewhere = $all->check($rule);

    return 'search/all' === $all->get_name()
        && !$all->is_mock()
        && true === $on_search
        && false === $elsewhere
        && Specificity::TYPE === $all->get_specificity($rule);
});

check('the Search Results conditions apply to Search templates only, and no other template type gains them', static function () {
    $all  = new \Elemacy\Conditions\Search\AllResults();
    $post = new \Elemacy\Conditions\Mock\SearchPostType();

    foreach ([$all, $post] as $condition) {
        if (!$condition->applies_to('search')) {
            return false;
        }

        foreach (['header', 'footer', 'single', 'archive', '404'] as $other) {
            if ($condition->applies_to($other)) {
                return false;
            }
        }
    }

    // The existing Header/Footer condition keeps its scope.
    $existing = new \Elemacy\Conditions\Mock\SearchResults();

    return $existing->applies_to('header') && $existing->applies_to('footer') && !$existing->applies_to('search');
});

check('Free\'s Search Results post type condition is a locked placeholder that never matches and carries the level Pro\'s real one declares', static function () {
    $mock = new \Elemacy\Conditions\Mock\SearchPostType();
    $empty = ConditionRuleDTO::from_array(['type' => 'search/post_type', 'value' => '']);
    $value = ConditionRuleDTO::from_array(['type' => 'search/post_type', 'value' => 'product']);

    $GLOBALS['__wp_is_search'] = true;
    $matches = $mock->check($value);
    $GLOBALS['__wp_is_search'] = false;

    return 'search/post_type' === $mock->get_name()
        && $mock->is_mock()
        && false === $matches
        && Specificity::TYPE === $mock->get_specificity($empty)
        && Specificity::OBJECT === $mock->get_specificity($value);
});

check('a Search template offers the global condition as available and the post type condition as unavailable without Pro', static function () {
    $manager = new ConditionManager();
    $manager->register(new \Elemacy\Conditions\Search\AllResults());
    $manager->register(new \Elemacy\Conditions\Mock\SearchPostType());

    return $manager->get_availability('search/all')->is_available()
        && $manager->get_availability('search/post_type')->is_unavailable();
});

check('a Search template whose only rule is the post type placeholder is suspended, not broadened, without Pro', static function () {
    $manager = new ConditionManager();
    $manager->register(new \Elemacy\Conditions\Search\AllResults());
    $manager->register(new \Elemacy\Conditions\Mock\SearchPostType());
    $evaluator = new ConditionEvaluator($manager);

    $GLOBALS['__wp_is_search'] = true;
    $suspended = $evaluator->match([ConditionRuleDTO::from_array(['type' => 'search/post_type', 'value' => 'product'])]);
    $global = $evaluator->match([ConditionRuleDTO::from_array(['type' => 'search/all'])]);
    $GLOBALS['__wp_is_search'] = false;

    return null === $suspended && Specificity::TYPE === $global;
});

check('a Search template holding a blank-type rule is suspended and its rule is left as stored', static function () {
    $manager = new ConditionManager();
    $manager->register(new \Elemacy\Conditions\Search\AllResults());
    $evaluator = new ConditionEvaluator($manager);
    $blank = ConditionRuleDTO::from_array(['type' => '', 'operator' => 'include', 'value' => '']);

    $GLOBALS['__wp_is_search'] = true;
    $result = $evaluator->match([$blank]);
    $GLOBALS['__wp_is_search'] = false;

    return null === $result && '' === $blank->type;
});

$manager->register(new LeveledCondition('prec/site', true, Specificity::SITE));
$manager->register(new LeveledCondition('prec/section', true, Specificity::SECTION));
$manager->register(new LeveledCondition('prec/type', true, Specificity::TYPE));
$manager->register(new LeveledCondition('prec/object', true, Specificity::OBJECT));
$manager->register(new LeveledCondition('prec/object_nomatch', false, Specificity::OBJECT));

$prec_entry = static function (int $id, array $types): array {
    return [
        'id' => $id,
        'conditions' => array_map(
            static fn(array $pair): array => [
                'type' => $pair[0],
                'operator' => $pair[1] ?? 'include',
                'value' => '',
            ],
            $types
        ),
    ];
};

$prec_resolve = static function (array $entries): ?int {
    \Elemacy\TemplateLibrary\TemplateResolver::reset();

    return (new \Elemacy\TemplateLibrary\TemplateResolver(
        new FixedResolverCache(['header' => $entries])
    ))->resolve('header');
};

check('ConditionEvaluator::match() reports the highest level among the includes that matched', static fn() =>
    $evaluator->match([$rule('prec/site'), $rule('prec/type')]) === Specificity::TYPE
    && $evaluator->match([$rule('prec/site')]) === Specificity::SITE);

check('ConditionEvaluator::match() reports null, not a level, when nothing matched', static fn() =>
    $evaluator->match([$rule('prec/object_nomatch')]) === null);

check('ConditionEvaluator::match() reports the broadest level for an exclude-only set that does not veto', static fn() =>
    $evaluator->match([$rule('prec/object_nomatch', 'exclude')]) === Specificity::SITE);

check('precedence: a site-wide header does not override one targeting singular posts', static fn() =>
    $prec_resolve([$prec_entry(1, [['prec/site']]), $prec_entry(2, [['prec/type']])]) === 2);

check('precedence: a header targeting one specific post beats one targeting its post type', static fn() =>
    $prec_resolve([$prec_entry(1, [['prec/type']]), $prec_entry(2, [['prec/object']])]) === 2);

check('precedence: the newer but broader header still loses to the older narrower one', static fn() =>
    // Index order is newest first, so id 9 here is the newer, broader candidate.
    $prec_resolve([$prec_entry(9, [['prec/site']]), $prec_entry(3, [['prec/object']])]) === 3);

check('precedence: when only the broader header matches, it is the one resolved', static fn() =>
    $prec_resolve([$prec_entry(1, [['prec/object_nomatch']]), $prec_entry(2, [['prec/site']])]) === 2);

check('precedence: two headers at the same level resolve to exactly one, identically on repeated calls', static function () use ($prec_resolve, $prec_entry) {
    $entries = [$prec_entry(5, [['prec/type']]), $prec_entry(4, [['prec/type']])];
    $first   = $prec_resolve($entries);

    return $first === 5 && $prec_resolve($entries) === 5 && $prec_resolve($entries) === 5;
});

check('precedence: a matching exclusion vetoes the template before any scoring', static fn() =>
    $prec_resolve([
        $prec_entry(1, [['prec/object'], ['prec/site', 'exclude']]),
        $prec_entry(2, [['prec/section']]),
    ]) === 2);

check('precedence: an exclusion never raises a template above the inclusions it matched on', static fn() =>
    // id 1 is [Entire Site, exclude Specific Post] — the exclusion does not
    // match this request, so id 1 still only scores SITE and must lose.
    $prec_resolve([
        $prec_entry(1, [['prec/site'], ['prec/object_nomatch', 'exclude']]),
        $prec_entry(2, [['prec/type']]),
    ]) === 2);

check('precedence: a conditionless template is the fallback, used only when nothing matched', static function () use ($prec_resolve, $prec_entry) {
    $with_match = $prec_resolve([['id' => 1, 'conditions' => []], $prec_entry(2, [['prec/site']])]);
    $no_match   = $prec_resolve([['id' => 1, 'conditions' => []], $prec_entry(2, [['prec/object_nomatch']])]);

    return $with_match === 2 && $no_match === 1;
});

check('precedence: a condition with no declared level never outranks one that declares a narrow level', static function () use ($manager, $prec_resolve, $prec_entry) {
    $manager->register(new FixedCondition('prec/undeclared', true));

    return $prec_resolve([$prec_entry(1, [['prec/undeclared']]), $prec_entry(2, [['prec/type']])]) === 2;
});

check('collect-all resolution is untouched by specificity: every matching popup is still returned', static function () use ($prec_entry) {
    \Elemacy\TemplateLibrary\TemplateResolver::reset();
    TypeRegistry::instance()->register(new TypeDefinition('prec_popup', 'Prec Popup', 'popup', 'popups'));

    $resolver = new \Elemacy\TemplateLibrary\TemplateResolver(new FixedResolverCache([
        'prec_popup' => [$prec_entry(1, [['prec/site']]), $prec_entry(2, [['prec/object']])],
    ]));

    $matched = $resolver->resolve_group('popup');

    return in_array(1, $matched, true) && in_array(2, $matched, true);
});

/**
 * A PHP file's executable code with every comment removed, so a test asserting
 * that source does NOT contain a construct is not satisfied by a docblock that
 * merely mentions it.
 */
function elemacy_code_without_comments(string $path): string
{
    $code = '';

    foreach (token_get_all(file_get_contents($path)) as $token) {
        if (is_array($token)) {
            if (T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0]) {
                continue;
            }

            $code .= $token[1];
            continue;
        }

        $code .= $token;
    }

    return $code;
}

/* ── Theme Builder chrome documents (Header / Footer) ────────────── */

check('the Theme Builder document map backs Header and Footer with their own chrome documents', static function () {
    $documents = require ELEMACY_PATH . 'src/Modules/ThemeBuilder/Config/documents.php';

    return ($documents['header'] ?? null) === \Elemacy\Modules\ThemeBuilder\Documents\HeaderDocument::class
        && ($documents['footer'] ?? null) === \Elemacy\Modules\ThemeBuilder\Documents\FooterDocument::class
        && \Elemacy\Modules\ThemeBuilder\Documents\HeaderDocument::get_type() === 'elemacy_header'
        && \Elemacy\Modules\ThemeBuilder\Documents\FooterDocument::get_type() === 'elemacy_footer';
});

check('a TypeDefinition built from that map resolves Header and Footer to the chrome document types, not the generic page document', static function () {
    $documents = require ELEMACY_PATH . 'src/Modules/ThemeBuilder/Config/documents.php';

    $header = new TypeDefinition('header', 'Header', 'theme', 'theme-builder', $documents['header']);
    $footer = new TypeDefinition('footer', 'Footer', 'theme', 'theme-builder', $documents['footer']);
    $untyped = new TypeDefinition('404', '404', 'theme', 'theme-builder', $documents['404'] ?? null);

    return $header->get_document_type() === 'elemacy_header'
        && $footer->get_document_type() === 'elemacy_footer'
        && $untyped->get_document_type() === null;
});

check('ThemeBuilder registers its types from the same document map DocumentManager registers documents from, so the two cannot disagree', static function () {
    $module = file_get_contents(ELEMACY_PATH . 'src/Modules/ThemeBuilder/ThemeBuilder.php');
    $manager = file_get_contents(ELEMACY_PATH . 'src/Modules/ThemeBuilder/Documents/DocumentManager.php');
    $path = 'src/Modules/ThemeBuilder/Config/documents.php';

    // Both read the shared map, and neither may `require_once` it — the
    // second reader of a require_once gets `true` instead of the array.
    return false !== strpos($module, $path)
        && false !== strpos($manager, $path)
        && false === strpos($module, "require_once Utils::get_plugin_path('" . $path)
        && false === strpos($manager, "require_once Utils::get_plugin_path('" . $path)
        && false === strpos($module, 'SingleDocument::class');
});

check('the chrome documents suppress the page-layout control Elementor injects only for documents that declare support for it', static fn() =>
    \Elemacy\Modules\ThemeBuilder\Documents\HeaderDocument::get_properties()['support_page_layout'] === false
    && \Elemacy\Modules\ThemeBuilder\Documents\FooterDocument::get_properties()['support_page_layout'] === false
    && \Elemacy\Modules\ThemeBuilder\Documents\HeaderDocument::get_properties()['support_kit'] === true
    && \Elemacy\Modules\ThemeBuilder\Documents\HeaderDocument::get_properties()['show_in_library'] === false);

check('the chrome documents do not register PageBase\'s page-level controls: no hide-title, excerpt, featured image, menu order, comments or body style', static function () {
    $source = elemacy_code_without_comments(ELEMACY_PATH . 'src/Modules/ThemeBuilder/Documents/ChromeDocument.php');

    // register_controls() must not chain into PageBase, which is what adds
    // every one of those controls.
    if (false !== strpos($source, 'parent::register_controls()')) {
        return false;
    }

    foreach (['register_hide_title_control', 'register_post_fields_control', 'register_style_controls'] as $page_level) {
        if (false !== strpos($source, $page_level)) {
            return false;
        }
    }

    // It must still register the document's own identity/status section and
    // still fire Elementor's public document-controls extension point.
    return false !== strpos($source, 'register_document_controls()')
        && false !== strpos($source, "do_action('elementor/documents/register_controls'");
});

check('the chrome documents scope their settings CSS to the template\'s own root, not a page body class that never exists for them', static function () {
    $source = elemacy_code_without_comments(ELEMACY_PATH . 'src/Modules/ThemeBuilder/Documents/ChromeDocument.php');

    return false !== strpos($source, "return '.elementor-' . \$this->get_main_id();")
        && false === strpos($source, 'body.elementor-page-');
});

check('Migrator::schedule() defers the sync to init rather than running it inline, so a migration sees populated registries', static function () {
    $migrator = new class extends Migrator {
        public int $syncs = 0;

        public function sync(bool $is_fresh_install): void
        {
            $this->syncs++;
        }
    };

    $before = count(array_merge(...array_values($GLOBALS['__wp_actions']['init'] ?? [[]])));
    $migrator->schedule(false);
    $after = count(array_merge(...array_values($GLOBALS['__wp_actions']['init'] ?? [[]])));

    // Queued, not run.
    if ($migrator->syncs !== 0 || $after !== $before + 1) {
        return false;
    }

    do_action('init');

    return $migrator->syncs === 1;
});

check('Migrator schedules its sync after every library-type registration, never before', static fn() =>
    Migrator::SYNC_PRIORITY > 20 && Migrator::SYNC_PRIORITY > 10 && Migrator::SYNC_PRIORITY > 5);

check('Free (SiteInstaller) and ElemacyPro both schedule their schema sync instead of running it during plugins_loaded', static function () {
    $free = elemacy_code_without_comments(ELEMACY_PATH . 'src/Core/SiteInstaller.php');

    if (false !== strpos($free, '->sync(') || false === strpos($free, '->schedule($is_fresh_install)')) {
        return false;
    }

    $pro_path = dirname(ELEMACY_PATH, 1) . '/elemacy-pro/src/Core/ElemacyPro.php';

    if (!is_readable($pro_path)) {
        return true; // Pro is not checked out beside Free; Free's own half stands.
    }

    $pro = elemacy_code_without_comments($pro_path);

    return false === strpos($pro, '->sync(') && false !== strpos($pro, '->schedule(false)');
});

/* ── Theme compatibility adapter selection ──────────────────────── */

check('adapter selection is data, not code: every built-in theme adapter is chosen from one slug map, and no adapter class names another', static function () {
    $manager = elemacy_code_without_comments(ELEMACY_PATH . 'src/Modules/ThemeBuilder/Compatibility/CompatibilityManager.php');

    // One registration table, one extension point, and the documented
    // fallback order: exact slug, then block theme, then generic.
    if (false === strpos($manager, 'Hooks::THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION')
        || false === strpos($manager, 'wp_is_block_theme()')
        || false === strpos($manager, 'ClassicThemeCompatibility')) {
        return false;
    }

    // Adding an adapter must never mean editing another one.
    $directory = ELEMACY_PATH . 'src/Modules/ThemeBuilder/Compatibility/Themes/';
    $adapters = array_map(
        static fn(string $file): string => basename($file, '.php'),
        glob($directory . '*.php') ?: []
    );

    foreach ($adapters as $adapter) {
        $source = elemacy_code_without_comments($directory . $adapter . '.php');

        foreach ($adapters as $other) {
            // ClassicThemeCompatibility and HelloElementorCompatibility are the two
            // fallbacks CompatibilityManager itself selects; no adapter may
            // reference any sibling, including those.
            if ($other !== $adapter && false !== strpos($source, $other)) {
                return false;
            }
        }
    }

    return count($adapters) >= 10;
});

check('every shipped theme adapter implements the one adapter contract, so registration stays uniform', static function () {
    $directory = ELEMACY_PATH . 'src/Modules/ThemeBuilder/Compatibility/Themes/';

    foreach (glob($directory . '*.php') ?: [] as $file) {
        $source = elemacy_code_without_comments($file);

        if (false === strpos($source, 'implements ThemeCompatibilityInterface')
            || false === strpos($source, 'public function register(ThemeBuilderManager $manager): void')) {
            return false;
        }
    }

    return true;
});

/* ── Popups\Services\RuleManager / TriggerManager availability ──── */

$rule_manager = RuleManager::instance();
$rule_manager->register(new FixedRule());
$rule_manager->register(new FixedMockRule());

check('RuleManager reports a real, registered rule as available', static fn() =>
    $rule_manager->get_availability('test/rule')->is_available());

check('RuleManager reports an unregistered rule identifier as unknown', static function () use ($rule_manager) {
    $availability = $rule_manager->get_availability('test/unregistered-rule');

    return $availability->is_unavailable()
        && $availability->get_reason_code() === FeatureAvailability::UNKNOWN_IDENTIFIER;
});

check('RuleManager reports a mock rule as an inactive extension, not unknown', static function () use ($rule_manager) {
    $availability = $rule_manager->get_availability('mock/rule');

    return $availability->is_unavailable()
        && $availability->get_reason_code() === FeatureAvailability::EXTENSION_INACTIVE;
});

$trigger_manager = TriggerManager::instance();
$trigger_manager->register(new FixedTrigger());
$trigger_manager->register(new FixedMockTrigger());

check('TriggerManager reports a real, registered trigger as available', static fn() =>
    $trigger_manager->get_availability('test/trigger')->is_available());

check('TriggerManager reports an unregistered trigger identifier as unknown', static function () use ($trigger_manager) {
    $availability = $trigger_manager->get_availability('test/unregistered-trigger');

    return $availability->is_unavailable()
        && $availability->get_reason_code() === FeatureAvailability::UNKNOWN_IDENTIFIER;
});

check('TriggerManager reports a mock trigger as an inactive extension, not unknown', static function () use ($trigger_manager) {
    $availability = $trigger_manager->get_availability('mock/trigger');

    return $availability->is_unavailable()
        && $availability->get_reason_code() === FeatureAvailability::EXTENSION_INACTIVE;
});

/* ── Task 10.1: RegistryIdentifierGuard — reserved/duplicate identifiers ── */
/* Each check uses a fresh registry instance so it can never disturb the   */
/* shared $manager/$rule_manager/$trigger_manager singletons above.        */

final class NamedFixtureRule extends BaseRule
{
    private string $name;
    private string $label;

    public function __construct(string $name, string $label = 'Fixture rule')
    {
        $this->name  = $name;
        $this->label = $label;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_label(): string
    {
        return $this->label;
    }
}

final class NamedFixtureTrigger extends BaseTrigger
{
    private string $name;
    private string $label;

    public function __construct(string $name, string $label = 'Fixture trigger')
    {
        $this->name  = $name;
        $this->label = $label;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_label(): string
    {
        return $this->label;
    }
}

check('ConditionManager rejects registering the reserved identifier "type"', static function () {
    try {
        (new ConditionManager())->register(new FixedCondition('type', true));
    } catch (\InvalidArgumentException $e) {
        return true;
    }

    return false;
});

check('ConditionManager rejects two distinct non-mock conditions claiming the same name', static function () {
    $registry = new ConditionManager();
    $registry->register(new FixedCondition('dup/fixture-condition', true));

    try {
        // Same class, different content (matches=false vs true) — genuinely
        // distinct registrations, not an idempotent re-declaration.
        $registry->register(new FixedCondition('dup/fixture-condition', false));
    } catch (\InvalidArgumentException $e) {
        return true;
    }

    return false;
});

check('ConditionManager tolerates re-registering an identical condition under a name it already owns', static function () {
    $registry = new ConditionManager();
    $registry->register(new FixedCondition('idempotent/fixture-condition', true));
    $registry->register(new FixedCondition('idempotent/fixture-condition', true));

    return $registry->get('idempotent/fixture-condition') instanceof FixedCondition;
});

check('ConditionManager still allows a real condition to override a mock of the same name (extension activation)', static function () {
    $registry = new ConditionManager();
    $registry->register(new FixedMockCondition());
    $registry->register(new FixedCondition('mock/locked', true));

    return $registry->get('mock/locked') instanceof FixedCondition
        && $registry->get_availability('mock/locked')->is_available();
});

check('ConditionManager rejects a mock trying to displace the real condition already holding its name', static function () {
    $registry = new ConditionManager();
    $registry->register(new FixedCondition('mock/locked', true));

    try {
        $registry->register(new FixedMockCondition());
    } catch (\InvalidArgumentException $e) {
        // The real implementation stays in place and stays available.
        return $registry->get('mock/locked') instanceof FixedCondition
            && $registry->get_availability('mock/locked')->is_available()
            && strpos($e->getMessage(), 'mock/locked') !== false;
    }

    return false;
});

check('RuleManager rejects a mock trying to displace the real rule already holding its name', static function () {
    $registry = new RuleManager();
    $registry->register(new NamedFixtureRule('mock/rule', 'Real'));

    try {
        $registry->register(new FixedMockRule());
    } catch (\InvalidArgumentException $e) {
        return $registry->get('mock/rule') instanceof NamedFixtureRule
            && $registry->get_availability('mock/rule')->is_available();
    }

    return false;
});

check('RegistryIdentifierGuard: a mock may still supersede another mock', static function () {
    try {
        RegistryIdentifierGuard::assert_registrable(
            'Condition',
            'mock/locked',
            new FixedMockCondition(),
            new FixedMockCondition()
        );
    } catch (\InvalidArgumentException $e) {
        return false;
    }

    return true;
});

check('RegistryIdentifierGuard covers all four replacement branches directionally', static function () {
    $real_a = new FixedCondition('branch/fixture', true);
    $real_b = new FixedCondition('branch/fixture', false); // Same class, different content.
    $mock   = new FixedMockCondition();

    $accepts = static function (?object $existing, object $incoming): bool {
        try {
            RegistryIdentifierGuard::assert_registrable('Condition', 'branch/fixture', $existing, $incoming);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        return true;
    };

    return $accepts($mock, $real_a)                                     // real supersedes mock
        && $accepts($real_a, new FixedCondition('branch/fixture', true)) // loose-equal re-registration
        && !$accepts($real_a, $mock)                                     // mock must not displace real
        && !$accepts($real_a, $real_b);                                  // real vs real is still a conflict
});

check('RegistryIdentifierGuard names the unavailable stand-in when a mock is what was rejected', static function () {
    try {
        RegistryIdentifierGuard::assert_registrable(
            'Condition',
            'mock/locked',
            new FixedCondition('mock/locked', true),
            new FixedMockCondition()
        );
    } catch (\InvalidArgumentException $e) {
        // Distinguishes a downgrade attempt from a real-vs-real collision.
        return strpos($e->getMessage(), 'unavailable stand-in') !== false
            && strpos($e->getMessage(), 'FixedMockCondition') !== false;
    }

    return false;
});

check('RuleManager rejects registering the reserved identifier "id"', static function () {
    try {
        (new RuleManager())->register(new NamedFixtureRule('id'));
    } catch (\InvalidArgumentException $e) {
        return true;
    }

    return false;
});

check('RuleManager rejects two distinct non-mock rules claiming the same name', static function () {
    $registry = new RuleManager();
    $registry->register(new NamedFixtureRule('dup/fixture-rule', 'A'));

    try {
        $registry->register(new NamedFixtureRule('dup/fixture-rule', 'B'));
    } catch (\InvalidArgumentException $e) {
        return true;
    }

    return false;
});

check('RuleManager still allows a real rule to override a mock of the same name (extension activation)', static function () {
    $registry = new RuleManager();
    $registry->register(new FixedMockRule());
    $registry->register(new NamedFixtureRule('mock/rule', 'Now real'));

    return $registry->get('mock/rule') instanceof NamedFixtureRule
        && $registry->get_availability('mock/rule')->is_available();
});

check('TriggerManager rejects registering the reserved identifier "unknown"', static function () {
    try {
        (new TriggerManager())->register(new NamedFixtureTrigger('unknown'));
    } catch (\InvalidArgumentException $e) {
        return true;
    }

    return false;
});

check('TriggerManager rejects two distinct non-mock triggers claiming the same name', static function () {
    $registry = new TriggerManager();
    $registry->register(new NamedFixtureTrigger('dup/fixture-trigger', 'A'));

    try {
        $registry->register(new NamedFixtureTrigger('dup/fixture-trigger', 'B'));
    } catch (\InvalidArgumentException $e) {
        return true;
    }

    return false;
});

check('TriggerManager still allows a real trigger to override a mock of the same name (extension activation)', static function () {
    $registry = new TriggerManager();
    $registry->register(new FixedMockTrigger());
    $registry->register(new NamedFixtureTrigger('mock/trigger', 'Now real'));

    return $registry->get('mock/trigger') instanceof NamedFixtureTrigger
        && $registry->get_availability('mock/trigger')->is_available();
});

/* ── Task 10.2: RuleTriggerParams — discriminator/shape guard ──────── */

check('RuleTriggerParams::assert_valid() refuses a params payload that tries to set "type"', static function () {
    try {
        RuleTriggerParams::assert_valid('rules', ['type' => 'frequency_cap', 'max' => 3]);
    } catch (\Elemacy\Http\Exceptions\ValidationException $e) {
        return true;
    }

    return false;
});

check('RuleTriggerParams::assert_valid() refuses a params payload that tries to set "id"', static function () {
    try {
        RuleTriggerParams::assert_valid('triggers', ['id' => 'forged', 'delay' => 30]);
    } catch (\Elemacy\Http\Exceptions\ValidationException $e) {
        return true;
    }

    return false;
});

check('RuleTriggerParams::assert_valid() refuses a nested-array/object parameter value', static function () {
    try {
        RuleTriggerParams::assert_valid('rules', ['roles' => [['nested' => 'array']]]);
    } catch (\Elemacy\Http\Exceptions\ValidationException $e) {
        return true;
    }

    return false;
});

check('RuleTriggerParams::assert_valid() accepts every currently registered schema shape (scalar and flat scalar-list)', static function () {
    RuleTriggerParams::assert_valid('rules', ['max' => 3]);
    RuleTriggerParams::assert_valid('rules', ['url' => 'https://example.com']);
    RuleTriggerParams::assert_valid('rules', ['roles' => ['administrator', 'editor']]);
    RuleTriggerParams::assert_valid('rules', ['start' => '', 'end' => '2030-01-01T00:00:00']);

    return true; // No exception thrown for any legitimate shape above.
});

check('RuleDTO::collection_or_fail() rejects a rule whose params attempts to override "type"', static function () {
    try {
        RuleDTO::collection_or_fail([
            ['id' => 'r1', 'type' => 'frequency_cap', 'params' => ['type' => 'logged_in', 'max' => 3]],
        ]);
    } catch (\Elemacy\Http\Exceptions\ValidationException $e) {
        return true;
    }

    return false;
});

check('TriggerDTO::collection_or_fail() rejects a trigger whose params attempts to override "type"', static function () {
    try {
        TriggerDTO::collection_or_fail([
            ['id' => 't1', 'type' => 'page_load', 'params' => ['type' => 'click']],
        ]);
    } catch (\Elemacy\Http\Exceptions\ValidationException $e) {
        return true;
    }

    return false;
});

check('RuleDTO::collection_or_fail() still accepts a normal, legitimate rule unchanged', static function () {
    $rules = RuleDTO::collection_or_fail([
        ['id' => 'r1', 'type' => 'frequency_cap', 'params' => ['max' => 5]],
    ]);

    return count($rules) === 1
        && $rules[0]->type === 'frequency_cap'
        && $rules[0]->params === ['max' => 5];
});

check('BaseRule::to_js_config() default implementation cannot be spoofed by a malicious params["type"] even if constructed directly (defense in depth, bypassing collection_or_fail())', static function () {
    $rule = new NamedFixtureRule('real/rule');
    $dto = new RuleDTO();
    $dto->type = 'real/rule';
    $dto->params = ['type' => 'attacker-controlled', 'note' => 'x'];

    $config = $rule->to_js_config($dto);

    return $config['type'] === 'real/rule' && $config['note'] === 'x';
});

check('BaseTrigger::to_js_config() default implementation cannot be spoofed by a malicious params["type"] even if constructed directly (defense in depth, bypassing collection_or_fail())', static function () {
    $trigger = new NamedFixtureTrigger('real/trigger');
    $dto = new TriggerDTO();
    $dto->type = 'real/trigger';
    $dto->params = ['type' => 'attacker-controlled'];

    $config = $trigger->to_js_config($dto);

    return $config['type'] === 'real/trigger';
});

check('BaseRule::to_js_config() still serializes a normal rule\'s params unchanged (no regression from the merge-order fix)', static function () {
    $rule = new NamedFixtureRule('plain/rule');
    $dto = new RuleDTO();
    $dto->type = 'plain/rule';
    $dto->params = ['max' => 7];

    return $rule->to_js_config($dto) === ['max' => 7, 'type' => 'plain/rule'];
});

/* ── Task 10.3: type-list resources expose availability too ────────── */

check('RuleTypeResource reports availability:available for a real, registered rule', static function () {
    $data = (new RuleTypeResource(new NamedFixtureRule('real/rule')))->to_array();

    return $data['availability']['available'] === true && $data['availability']['reason_code'] === '';
});

check('RuleTypeResource reports availability:unavailable (inactive_extension) for a Mock rule, without exposing it as an unknown identifier', static function () {
    $data = (new RuleTypeResource(new FixedMockRule()))->to_array();

    return $data['availability']['available'] === false
        && $data['availability']['reason_code'] === FeatureAvailability::EXTENSION_INACTIVE;
});

check('TriggerTypeResource reports availability:available for a real, registered trigger', static function () {
    $data = (new TriggerTypeResource(new NamedFixtureTrigger('real/trigger')))->to_array();

    return $data['availability']['available'] === true;
});

check('TriggerTypeResource reports availability:unavailable (inactive_extension) for a Mock trigger', static function () {
    $data = (new TriggerTypeResource(new FixedMockTrigger()))->to_array();

    return $data['availability']['available'] === false
        && $data['availability']['reason_code'] === FeatureAvailability::EXTENSION_INACTIVE;
});

check('ConditionTypeResource reports availability:available for a real, registered condition', static function () {
    $data = (new ConditionTypeResource(new FixedCondition('real/condition', true)))->to_array();

    return $data['availability']['available'] === true;
});

check('ConditionTypeResource reports availability:unavailable (inactive_extension) for a Mock condition', static function () {
    $data = (new ConditionTypeResource(new FixedMockCondition()))->to_array();

    return $data['availability']['available'] === false
        && $data['availability']['reason_code'] === FeatureAvailability::EXTENSION_INACTIVE;
});

check('PopupResource encodes a param-less trigger/rule as a JSON object, not a list the admin app rejects', static function () {
    $trigger = new TriggerDTO();
    $trigger->id = 't1';
    $trigger->type = 'exit_intent';
    $rule = new RuleDTO();
    $rule->id = 'r1';
    $rule->type = 'frequency_cap';
    $rule->params = ['max' => 3];

    $json = json_encode((new PopupResource([
        'id' => 1, 'title' => 'P', 'type' => 'popup', 'status' => 'draft', 'author' => 1, 'date' => null,
        'conditions' => [], 'triggers' => [$trigger], 'rules' => [$rule],
    ]))->to_array());

    return strpos($json, '"type":"exit_intent","params":{}') !== false
        && strpos($json, '"params":{"max":3}') !== false;
});

check('PopupConfigResource omits a trigger whose type is not registered instead of forwarding raw type/params (task 10.3: never expose an identifier/params pair the server does not recognize)', static function () {
    TriggerManager::instance()->register(new NamedFixtureTrigger('elemacy_fixture/known_trigger'));

    $popup = new PopupDTO();
    $popup->id = 909001;
    $popup->type = 'popup';
    $popup->triggers = [
        ['id' => 't1', 'type' => 'elemacy_fixture/known_trigger', 'params' => []],
        ['id' => 't2', 'type' => 'elemacy_fixture/totally_unregistered_trigger', 'params' => ['evil' => true]],
    ];

    $config = (new PopupConfigResource($popup))->to_array();

    return count($config['triggers']) === 1 && $config['triggers'][0]['type'] === 'elemacy_fixture/known_trigger';
});

check('PopupConfigResource omits a rule whose type is not registered instead of forwarding raw type/params', static function () {
    $popup = new PopupDTO();
    $popup->id = 909002;
    $popup->type = 'popup';
    $popup->rules = [
        ['id' => 'r1', 'type' => 'elemacy_fixture/totally_unregistered_rule', 'params' => ['evil' => true]],
    ];

    $config = (new PopupConfigResource($popup))->to_array();

    return $config['rules'] === [];
});

/* ── Migrator ordering, range and stamping ──────────────────────── */

check('runs due migrations in ascending version order', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.0', '2.0.0');
    $versions = array_column($migrator->log, 0);

    return $versions === ['1.0.5', '1.2.0', '1.10.0'];
});

check('stamps SCHEMA version (not DB_VERSION/release version) after the last completed step', static function () {
    delete_option(OptionKeys::SCHEMA_VERSION);
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.0', '2.0.0');

    return get_option(OptionKeys::SCHEMA_VERSION) === '1.10.0'
        && get_option(OptionKeys::DB_VERSION) === false;
});

check('skips migrations at or below the installed version', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.5', '2.0.0');

    return array_column($migrator->log, 0) === ['1.2.0', '1.10.0'];
});

check('skips migrations above the target version', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.0', '1.2.0');

    return array_column($migrator->log, 0) === ['1.0.5', '1.2.0'];
});

check('nothing runs when already current', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.10.0', '1.10.0');

    return $migrator->log === [];
});

check('callbacks receive (from, to)', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.0', '2.0.0');

    return $migrator->log[0][1] === '1.0.0' && $migrator->log[0][2] === '2.0.0';
});

/* ── Task 11.5: Migrator::sync() — schema version separate from release ── */

final class EmptyMigrator extends SchemaFlowMigrator
{
    protected function migrations(): array
    {
        return [];
    }
}

final class FailingStepMigrator extends SchemaFlowMigrator
{
    public array $log = [];

    protected function migrations(): array
    {
        return [
            '1.0.0' => function ($from, $to) {
                $this->log[] = '1.0.0';
            },
            '2.0.0' => function ($from, $to) {
                throw new \RuntimeException('simulated failure');
            },
            '3.0.0' => function ($from, $to) {
                $this->log[] = '3.0.0';
            },
        ];
    }
}

check('EmptyMigrator::latest_version() is the baseline sentinel when no migrations are registered', static function () {
    return (new EmptyMigrator())->latest_version() === Migrator::BASELINE_VERSION;
});

check('RecordingMigrator::latest_version() returns the highest registered version, not lexicographic order', static function () {
    // '1.10.0' > '1.2.0' by version_compare(); lexicographic string sort would get this wrong.
    return (new RecordingMigrator())->latest_version() === '1.10.0';
});

check('sync() on a clean/fresh install seeds straight to latest_version() and runs nothing', static function () {
    delete_option(OptionKeys::SCHEMA_VERSION);
    $migrator = new RecordingMigrator();
    $migrator->sync(true);

    return $migrator->log === []
        && get_option(OptionKeys::SCHEMA_VERSION) === '1.10.0';
});

check('sync() on an existing (non-fresh) install with no prior schema marker seeds at BASELINE and runs every migration', static function () {
    delete_option(OptionKeys::SCHEMA_VERSION);
    $migrator = new RecordingMigrator();
    $migrator->sync(false);

    return array_column($migrator->log, 0) === ['1.0.5', '1.2.0', '1.10.0']
        && get_option(OptionKeys::SCHEMA_VERSION) === '1.10.0';
});

check('a repeated sync() call never replays already-completed migrations', static function () {
    delete_option(OptionKeys::SCHEMA_VERSION);
    $migrator = new RecordingMigrator();
    $migrator->sync(false);
    $first_run_count = count($migrator->log);

    $migrator->sync(false); // same site, same request-independent call, nothing new to do.

    return $first_run_count === 3 && count($migrator->log) === 3;
});

check('a failed migration step does not advance the schema marker past it, and a later sync() resumes from there rather than replaying the completed step', static function () {
    delete_option(OptionKeys::SCHEMA_VERSION);
    $migrator = new FailingStepMigrator();

    try {
        $migrator->sync(false);
        return false; // the simulated failure must propagate.
    } catch (\RuntimeException $e) {
        // Marker must sit at the last *successful* step, not the one that threw.
        if (get_option(OptionKeys::SCHEMA_VERSION) !== '1.0.0') {
            return false;
        }
    }

    // A later attempt (e.g. the next request) must not replay 1.0.0's already-applied step.
    $migrator2 = new FailingStepMigrator();
    try {
        $migrator2->sync(false);
        return false;
    } catch (\RuntimeException $e) {
        return $migrator2->log === []; // 1.0.0 never re-ran; it failed again at 2.0.0 before reaching 3.0.0's log entry.
    }
});

check('sync() never lowers a newer schema version on a downgrade (older code registers fewer migrations)', static function () {
    update_option(OptionKeys::SCHEMA_VERSION, '5.0.0'); // simulates a newer release's already-completed schema state.
    $migrator = new RecordingMigrator(); // this "older" build's own ceiling (1.10.0) is below the stored marker.
    $migrator->sync(false);

    return $migrator->log === []
        && get_option(OptionKeys::SCHEMA_VERSION) === '5.0.0';
});

/* ── Task 11.6: BatchCursor — resumable offset + expiring lock ─────── */

check('BatchCursor::get_offset() defaults to 0 for a never-used name', static function () {
    return (new BatchCursor('fixture_batch_' . uniqid()))->get_offset() === 0;
});

check('BatchCursor::advance() persists the offset for a later cursor instance of the same name', static function () {
    $name = 'fixture_batch_' . uniqid();
    (new BatchCursor($name))->advance(150);

    return (new BatchCursor($name))->get_offset() === 150;
});

check('BatchCursor::complete() resets the offset back to 0', static function () {
    $name = 'fixture_batch_' . uniqid();
    $cursor = new BatchCursor($name);
    $cursor->advance(200);
    $cursor->complete();

    return $cursor->get_offset() === 0;
});

/* ── Task 11.6: resumable multi-batch migration + concurrency lock ── */

/**
 * A migration whose single step processes one fixed-size batch per call via
 * a real BatchCursor, returning false (not yet complete) until every batch
 * is done — proving Migrator::run()'s "false means not yet complete, don't
 * advance the marker, try again on a later sync()" contract end to end,
 * exactly the shape task 14.2's real counter-consolidation migration will
 * use.
 */
final class ResumableBatchMigrator extends SchemaFlowMigrator
{
    public const TOTAL_ITEMS = 25;
    public const BATCH_SIZE = 10;

    public array $processed = [];

    protected string $cursor_name;

    public function __construct()
    {
        // A fresh, non-recyclable name per instance — spl_object_id() can be
        // reused after garbage collection within one long-running script.
        $this->cursor_name = 'fixture_resumable_' . uniqid('', true);
    }

    protected function migrations(): array
    {
        return [
            '1' => [$this, 'consolidate'],
        ];
    }

    public function consolidate($from, $to)
    {
        $cursor = new SchemaFlowCursor($this->cursor_name);

        if (!$cursor->acquire_lock()) {
            return false;
        }

        try {
            $offset = $cursor->get_offset();
            $remaining = self::TOTAL_ITEMS - $offset;

            if ($remaining <= 0) {
                $cursor->complete();
                return true;
            }

            $batch = min(self::BATCH_SIZE, $remaining);
            for ($i = 0; $i < $batch; $i++) {
                $this->processed[] = $offset + $i;
            }
            $cursor->advance($offset + $batch);

            return $offset + $batch >= self::TOTAL_ITEMS ? true : false;
        } finally {
            $cursor->release_lock();
        }
    }
}

check('a migration returning false does not advance the schema marker, and interruption resumes from the last successful batch rather than restarting', static function () {
    delete_option(OptionKeys::SCHEMA_VERSION);
    $migrator = new ResumableBatchMigrator();

    // 25 items / batches of 10 = 3 sync() calls to finish (10, 10, 5).
    $migrator->sync(false);
    $after_first = [$migrator->processed, get_option(OptionKeys::SCHEMA_VERSION)];

    $migrator->sync(false);
    $after_second = [$migrator->processed, get_option(OptionKeys::SCHEMA_VERSION)];

    $migrator->sync(false);
    $after_third = [$migrator->processed, get_option(OptionKeys::SCHEMA_VERSION)];

    // sync() seeds the marker at Migrator::BASELINE_VERSION on its first
    // call (schema_version was unset); it only advances to the migration's
    // own version ('1') once consolidate() finally returns non-false.
    return $after_first === [range(0, 9), Migrator::BASELINE_VERSION]
        && $after_second === [range(0, 19), Migrator::BASELINE_VERSION]
        && $after_third === [range(0, 24), '1'];
});

/* ── Task 11.7: SiteBatchIterator ──────────────────────────────────── */

check('SiteBatchIterator::process_batch() pages through sites in bounded batches and reports has_more correctly', static function () {
    $GLOBALS['__wp_multisite_sites'] = range(1, 12);
    $iterator = new SiteBatchIterator();
    $seen = [];
    $visit = static function (int $site_id) use (&$seen) {
        $seen[] = $site_id;
    };

    $first = $iterator->process_batch($visit, 0, 5);
    $second = $iterator->process_batch($visit, 5, 5);
    $third = $iterator->process_batch($visit, 10, 5);

    return $first === ['processed' => 5, 'has_more' => true]
        && $second === ['processed' => 5, 'has_more' => true]
        && $third === ['processed' => 2, 'has_more' => false]
        && $seen === range(1, 12);
});

check('SiteBatchIterator::process_batch() switches into each site for the callback and restores the prior blog afterward', static function () {
    $GLOBALS['__wp_multisite_sites'] = [101, 102, 103];
    $GLOBALS['__wp_current_blog_id'] = 1;
    $GLOBALS['__wp_blog_stack'] = [];
    $seen_during_callback = [];

    (new SiteBatchIterator())->process_batch(static function () use (&$seen_during_callback) {
        $seen_during_callback[] = $GLOBALS['__wp_current_blog_id'];
    }, 0, 10);

    return $seen_during_callback === [101, 102, 103]
        && $GLOBALS['__wp_current_blog_id'] === 1
        && $GLOBALS['__wp_blog_stack'] === [];
});

check('SiteBatchIterator::process_batch() restores the prior blog via finally even when the callback throws, and abandons the rest of the batch', static function () {
    $GLOBALS['__wp_multisite_sites'] = [201, 202, 203];
    $GLOBALS['__wp_current_blog_id'] = 1;
    $GLOBALS['__wp_blog_stack'] = [];
    $processed = [];

    try {
        (new SiteBatchIterator())->process_batch(static function (int $site_id) use (&$processed) {
            $processed[] = $site_id;

            if ($site_id === 202) {
                throw new RuntimeException('boom');
            }
        }, 0, 10);

        return false; // The failure must propagate, not be swallowed.
    } catch (RuntimeException $e) {
        return $e->getMessage() === 'boom'
            && $processed === [201, 202]
            && $GLOBALS['__wp_current_blog_id'] === 1
            && $GLOBALS['__wp_blog_stack'] === [];
    }
});

check('SiteBatchIterator::process_batch() with a sub-one limit does nothing', static function () {
    $GLOBALS['__wp_multisite_sites'] = [301, 302];
    $GLOBALS['__wp_current_blog_id'] = 1;
    $GLOBALS['__wp_blog_stack'] = [];

    $result = (new SiteBatchIterator())->process_batch(static function () {
        throw new RuntimeException('must not be called');
    }, 0, 0);

    return $result === ['processed' => 0, 'has_more' => false] && $GLOBALS['__wp_current_blog_id'] === 1;
});

/* ── Task 11.7: SiteInstaller — bounded network seeding + lazy per-site fallback ── */

// The same shared instance Elemacy itself resolved at construction.
$installer = Container::instance()->singleton(SiteInstaller::class);

check('SiteInstaller builds from a container with no bindings at all (activation and plugins_loaded@1 run before any provider registers)', static function () {
    $bare = (new ReflectionClass(Container::class))->newInstanceWithoutConstructor();

    try {
        $installer = $bare->singleton(SiteInstaller::class);
    } catch (\Throwable $e) {
        return false;
    }

    return $installer instanceof SiteInstaller;
});

check('SiteInstaller::seed_current_site_if_missing() seeds ACTIVE_MODULES, with no recorded choice, only the first time the option has never existed', static function () use ($installer) {
    delete_option(OptionKeys::ACTIVE_MODULES);

    $installer->seed_current_site_if_missing();

    $seeded = get_option(OptionKeys::ACTIVE_MODULES);

    // A second call — nothing WordPress core itself would ever trigger
    // twice, but exactly what a lazily-caught-up site's own next request
    // does — must not clobber choices an admin may have since recorded.
    // Seeding stores no choice at all: every module then follows the
    // default it declares (ModuleManager), so none is frozen at today's.
    update_option(OptionKeys::ACTIVE_MODULES, ['popups' => false]);
    $installer->seed_current_site_if_missing();
    $after_customization = get_option(OptionKeys::ACTIVE_MODULES);

    return $seeded === []
        && $after_customization === ['popups' => false];
});

check('SiteInstaller::resume_network_seeding() does nothing on a single-site install even if a stale network offset exists', static function () use ($installer) {
    $GLOBALS['__wp_multisite'] = false;
    update_site_option(SiteInstaller::NETWORK_SEED_OFFSET_OPTION, 5);

    $installer->resume_network_seeding();

    return get_site_option(SiteInstaller::NETWORK_SEED_OFFSET_OPTION) === 5;
});

check('SiteInstaller::resume_network_seeding() does nothing when no network seed is in progress', static function () use ($installer) {
    $GLOBALS['__wp_multisite'] = true;
    delete_site_option(SiteInstaller::NETWORK_SEED_OFFSET_OPTION);
    $GLOBALS['__wp_multisite_sites'] = [9001, 9002]; // would be processed if this ran.

    $installer->resume_network_seeding();

    return get_site_option(SiteInstaller::NETWORK_SEED_OFFSET_OPTION, false) === false;
});

check('SiteInstaller::resume_network_seeding() resumes a network of over 100 sites in bounded batches across separate requests, never in one unbounded pass', static function () use ($installer) {
    $GLOBALS['__wp_multisite'] = true;
    $GLOBALS['__wp_multisite_sites'] = range(20001, 20000 + 150); // 150 sites, > NETWORK_SEED_BATCH_SIZE (100).
    $GLOBALS['__wp_current_blog_id'] = 1;
    $GLOBALS['__wp_blog_stack'] = [];
    // Mirrors what SiteInstaller::activate() does
    // for a network activation: seed the offset at 0, then run one bounded
    // pass — leaving the job interrupted with 50 sites still unseeded.
    update_site_option(SiteInstaller::NETWORK_SEED_OFFSET_OPTION, 0);

    $installer->resume_network_seeding(); // The activation request's own first bounded pass.
    $offset_after_first_pass = get_site_option(SiteInstaller::NETWORK_SEED_OFFSET_OPTION, false);

    $installer->resume_network_seeding(); // A later request's plugins_loaded resumes it.
    $offset_after_second_pass = get_site_option(SiteInstaller::NETWORK_SEED_OFFSET_OPTION, false);

    // A third call must be a safe no-op: the job is already gone.
    $installer->resume_network_seeding();
    $offset_after_third_pass = get_site_option(SiteInstaller::NETWORK_SEED_OFFSET_OPTION, false);

    return $offset_after_first_pass === 100
        && $offset_after_second_pass === false
        && $offset_after_third_pass === false
        && $GLOBALS['__wp_current_blog_id'] === 1
        && $GLOBALS['__wp_blog_stack'] === [];
});

check('SiteInstaller::register_hooks(): a newly-created site under network activation is seeded via wp_initialize_site, switching and restoring the blog context', static function () use ($installer) {
    $GLOBALS['__wp_plugin_active_for_network'] = true;
    $GLOBALS['__wp_current_blog_id'] = 1;
    $GLOBALS['__wp_blog_stack'] = [];
    delete_option(OptionKeys::ACTIVE_MODULES); // So the seeding add_option() below is observable.

    // An earlier, unrelated CompatibilityChecker test deliberately wipes
    // $GLOBALS['__wp_actions'] entirely to isolate its own assertions, which
    // also discards the wp_initialize_site listener this instance's
    // constructor registered back at bootstrap. Re-registering here is what
    // makes the listener present again to dispatch below, regardless of that
    // earlier wipe.
    $installer->register_hooks();

    do_action('wp_initialize_site', (object) ['blog_id' => 777]);

    $seeded = get_option(OptionKeys::ACTIVE_MODULES, null);

    return $seeded === []
        && $GLOBALS['__wp_current_blog_id'] === 1
        && $GLOBALS['__wp_blog_stack'] === [];
});

/* ── ModuleCatalog + module-owned default activation ───────────────── */

final class FixtureDefaultActiveModule extends Module
{
    public function get_name(): string
    {
        return 'fixture-default-active';
    }

    public function get_title(): string
    {
        return 'Fixture Default Active';
    }

    public function get_icon(): string
    {
        return '';
    }

    public function get_description(): string
    {
        return '';
    }

    public function is_default_active(): bool
    {
        return true;
    }

    public function boot(): void {}
}

final class FixtureOptOutModule extends Module
{
    public function get_name(): string
    {
        return 'fixture-opt-out';
    }

    public function get_title(): string
    {
        return 'Fixture Opt Out';
    }

    public function get_icon(): string
    {
        return '';
    }

    public function get_description(): string
    {
        return '';
    }

    public function boot(): void {}
}

check('Module::is_default_active() defaults to false, so a module opts in rather than out', static function () {
    return (new FixtureTrackedModule('fixture-bare'))->is_default_active() === false
        && (new FixtureOptOutModule())->is_default_active() === false
        && (new FixtureDefaultActiveModule())->is_default_active() === true
        && !(new ReflectionMethod(Module::class, 'is_default_active'))->isAbstract();
});

check('ModuleCatalog::all() instantiates each configured module once and returns the same instances on a second call', static function () {
    $catalog = new ModuleCatalog();

    $first  = $catalog->all();
    $second = $catalog->all();

    return $first === $second
        && $first !== []
        && count(array_filter($first, static fn ($m) => $m instanceof Module)) === count($first);
});

check('ModuleCatalog reads the module config with require, not require_once, so a second catalog in the same request still gets the array', static function () {
    // The regression this guards: Elemacy seeds a site before it loads
    // modules. With require_once, whichever of the two read the config file
    // second would receive true instead of the array, and iterating that is
    // a runtime error rather than a visible one.
    $first  = (new ModuleCatalog())->all();
    $second = (new ModuleCatalog())->all();

    return $first !== []
        && count($second) === count($first)
        && count(array_filter($second, static fn ($m) => $m instanceof Module)) === count($second);
});

check('The built-in modules declaring themselves default-active are exactly the five released ones (a released default is frozen: flipping it changes every site without a recorded choice)', static function () {
    $default_active = array_filter((new ModuleCatalog())->all(), static fn (Module $module): bool => $module->is_default_active());
    $slugs = array_values(array_map(static fn (Module $module): string => $module->get_name(), $default_active));
    $expected = ['theme-builder', 'widgets', 'custom-css', 'dynamic-tags', 'popups'];
    sort($slugs);
    sort($expected);

    return $slugs === $expected;
});

check('ModuleCatalog skips a configured class that does not exist or is not a Module, rather than failing', static function () {
    $catalog = new ModuleCatalog([
        FixtureDefaultActiveModule::class,
        'Elemacy\\Nope\\NoSuchModule',
        ReflectionMethod::class, // exists, but is not a Module.
        FixtureOptOutModule::class,
    ]);

    $names = array_map(static fn ($m) => $m->get_name(), $catalog->all());

    return $names === ['fixture-default-active', 'fixture-opt-out'];
});

check('A default-active module an extension registers is active on a seeded site, with no recorded choice, whenever it registers', static function () {
    update_option(OptionKeys::ACTIVE_MODULES, []);

    $manager = new ModuleManager();
    $manager->register(new FixtureDefaultActiveModule());
    $manager->register(new FixtureOptOutModule());
    $manager->init_modules();

    $active = array_keys($manager->get_active_modules());

    delete_option(OptionKeys::ACTIVE_MODULES);

    return $active === ['fixture-default-active'];
});

check('SiteInstaller::register_hooks(): wp_initialize_site does nothing when the plugin is not active for the whole network', static function () use ($installer) {
    $GLOBALS['__wp_plugin_active_for_network'] = false;
    $GLOBALS['__wp_current_blog_id'] = 1;
    $GLOBALS['__wp_blog_stack'] = [];
    delete_option(OptionKeys::ACTIVE_MODULES);
    $installer->register_hooks();

    do_action('wp_initialize_site', (object) ['blog_id' => 778]);

    $unseeded = get_option(OptionKeys::ACTIVE_MODULES, null) === null;
    $GLOBALS['__wp_plugin_active_for_network'] = true; // restore the shared default for any later test.

    return $unseeded && $GLOBALS['__wp_current_blog_id'] === 1 && $GLOBALS['__wp_blog_stack'] === [];
});

// Leave the shared multisite globals in a neutral, single-site state for
// anything that runs later, matching how they started.
$GLOBALS['__wp_multisite'] = false;
$GLOBALS['__wp_multisite_sites'] = [];
$GLOBALS['__wp_blog_stack'] = [];
$GLOBALS['__wp_current_blog_id'] = 1;
delete_site_option(SiteInstaller::NETWORK_SEED_OFFSET_OPTION);

/* ── Sanitizer rules ────────────────────────────────────────────── */

check('ARRAY: JSON string decodes to array', static fn() =>
    Sanitizer::apply_rule('{"a":1,"b":[2,3]}', Sanitizer::ARRAY) === ['a' => 1, 'b' => [2, 3]]);

check('ARRAY: array passes through untouched', static fn() =>
    Sanitizer::apply_rule([1, 2], Sanitizer::ARRAY) === [1, 2]);

check('ARRAY: scalar wraps instead of unserializing (object-injection guard)', static function () {
    $payload = 'O:8:"stdClass":0:{}'; // PHP-serialized — must NOT be unserialized.
    $result  = Sanitizer::apply_rule($payload, Sanitizer::ARRAY);

    return $result === [$payload];
});

check('ARRAY: empty string becomes empty array', static fn() =>
    Sanitizer::apply_rule('', Sanitizer::ARRAY) === []);

check('ARRAY_DEEP: string leaves sanitized, non-strings untouched', static function () {
    $result = Sanitizer::apply_rule(
        ['a' => "<script>x</script>ok", 'b' => 5, 'c' => ['d' => true, 'e' => " spaced  out "]],
        Sanitizer::ARRAY_DEEP
    );

    return $result['a'] === 'ok' && $result['b'] === 5 && $result['c']['d'] === true && $result['c']['e'] === 'spaced out';
});

check('DATETIME: valid input normalized to Y-m-d H:i:s', static fn() =>
    Sanitizer::apply_rule('2026-07-17T10:30:00+00:00', Sanitizer::DATETIME) === '2026-07-17 10:30:00');

check('DATETIME: garbage returns null', static fn() =>
    Sanitizer::apply_rule('not-a-date', Sanitizer::DATETIME) === null);

check('INT/BOOL casts', static fn() =>
    Sanitizer::apply_rule('42abc', Sanitizer::INT) === 42 && Sanitizer::apply_rule('1', Sanitizer::BOOL) === true);

check('null passes through every rule untouched', static fn() =>
    Sanitizer::apply_rule(null, Sanitizer::TEXT) === null);

/* ── Loop\DTO\LoopResultDTO ────────────────────────────── */

check('LoopResultDTO stores items, totals, and pagination support as given', static function () {
    $items = [1, 2, 3];
    $result = new LoopResultDTO($items, 30, 3, true);

    return $result->items === $items
        && $result->total_items === 30
        && $result->max_num_pages === 3
        && $result->supports_pagination === true;
});

check('LoopResultDTO defaults to empty/zero/false', static function () {
    $result = new LoopResultDTO();

    return $result->items === [] && $result->total_items === 0
        && $result->max_num_pages === 0 && $result->supports_pagination === false;
});

check('LoopResultDTO::from_array() populates fields like other DTOs in this codebase', static function () {
    $result = LoopResultDTO::from_array(['total_items' => 5, 'max_num_pages' => 2, 'supports_pagination' => true]);

    return $result->total_items === 5 && $result->max_num_pages === 2 && $result->supports_pagination === true;
});

/* ── Loop\LoopContext ─────────────────────────── */

/**
 * Identity-only test double: records nothing, exists purely so LoopContext
 * has something typed as LoopItemInterface to push.
 */
final class FixedLoopItem implements LoopItemInterface
{
    protected string $identity;

    public function __construct(string $identity)
    {
        $this->identity = $identity;
    }

    public function get_identity(): string
    {
        return $this->identity;
    }

    public function get_field(string $key)
    {
        return null;
    }

    public function enter(): void
    {
    }

    public function exit(): void
    {
    }
}

check('LoopContext::current() is null with nothing pushed', static function () {
    return LoopContext::current() === null;
});

check('LoopContext push/pop tracks the innermost item, and unwinds to the outer item after an inner pair completes', static function () {
    $outer = new FixedLoopItem('outer');
    $inner = new FixedLoopItem('inner');

    LoopContext::push($outer);
    $seen_outer = LoopContext::current();

    LoopContext::push($inner);
    $seen_inner = LoopContext::current();
    LoopContext::pop();

    $seen_outer_again = LoopContext::current();
    LoopContext::pop();
    $seen_after_all_popped = LoopContext::current();

    return $seen_outer === $outer && $seen_inner === $inner
        && $seen_outer_again === $outer && $seen_after_all_popped === null;
});

/* ── Loop\LoopDataSourceRegistry ──────────────── */

final class FixedLoopDataSource implements LoopDataSourceInterface
{
    protected string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function get_key(): string
    {
        return $this->key;
    }

    public function get_label(): string
    {
        return ucfirst($this->key);
    }

    public function register_controls($widget): void
    {
    }

    public function get_items(array $settings): LoopResultDTO
    {
        return new LoopResultDTO();
    }

    public function sanitize_ajax_settings(array $raw_settings, int $paged): array
    {
        return [];
    }

    public function register_preview_controls($document): void
    {
    }

    public function resolve_preview_item(array $settings): ?LoopItemInterface
    {
        return null;
    }
}

check('LoopDataSourceRegistry register/get/all round-trip by key', static function () {
    $registry = new LoopDataSourceRegistry();
    $a = new FixedLoopDataSource('source-a');
    $b = new FixedLoopDataSource('source-b');

    $registry->register($a);
    $registry->register($b);

    return $registry->get('source-a') === $a
        && $registry->get('source-b') === $b
        && $registry->get('missing') === null
        && $registry->all() === ['source-a' => $a, 'source-b' => $b];
});

check('LoopDataSourceRegistry::instance() returns the same instance across calls', static function () {
    return LoopDataSourceRegistry::instance() === LoopDataSourceRegistry::instance();
});

/* ── Task 10.1: RegistryIdentifierGuard on LoopDataSourceRegistry/       ── */
/* TypeRegistry — neither has a mock-stand-in concept, so a duplicate is   */
/* tolerated only when it is an identical re-declaration (PHP's loose      */
/* object equality: same class, equal property values).                   */

final class ConflictingFixedLoopDataSource implements LoopDataSourceInterface
{
    protected string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function get_key(): string
    {
        return $this->key;
    }

    public function get_label(): string
    {
        return ucfirst($this->key);
    }

    public function register_controls($widget): void
    {
    }

    public function get_items(array $settings): LoopResultDTO
    {
        return new LoopResultDTO();
    }

    public function sanitize_ajax_settings(array $raw_settings, int $paged): array
    {
        return [];
    }

    public function register_preview_controls($document): void
    {
    }

    public function resolve_preview_item(array $settings): ?LoopItemInterface
    {
        return null;
    }
}

check('LoopDataSourceRegistry rejects registering the reserved identifier "type"', static function () {
    try {
        (new LoopDataSourceRegistry())->register(new FixedLoopDataSource('type'));
    } catch (\InvalidArgumentException $e) {
        return true;
    }

    return false;
});

check('LoopDataSourceRegistry rejects two distinct implementations claiming the same key', static function () {
    $registry = new LoopDataSourceRegistry();
    $registry->register(new FixedLoopDataSource('dup-source'));

    try {
        // A different class (even sharing the parent's fields) is a
        // genuinely different implementation, not an idempotent re-declaration.
        $registry->register(new ConflictingFixedLoopDataSource('dup-source'));
    } catch (\InvalidArgumentException $e) {
        return true;
    }

    return false;
});

check('LoopDataSourceRegistry tolerates re-registering an identical source under a key it already owns', static function () {
    $registry = new LoopDataSourceRegistry();
    $registry->register(new FixedLoopDataSource('idempotent-source'));
    $registry->register(new FixedLoopDataSource('idempotent-source'));

    return $registry->get('idempotent-source') instanceof FixedLoopDataSource;
});

check('LoopDataSourceRegistry reports an unregistered key as unavailable and a registered one as available', static function () {
    $registry = new LoopDataSourceRegistry();
    $registry->register(new FixedLoopDataSource('available-source'));

    $available = $registry->get_availability('available-source');
    $unknown   = $registry->get_availability('never-registered-source');

    return $available->is_available()
        && $unknown->is_unavailable()
        && $unknown->get_reason_code() === FeatureAvailability::UNKNOWN_IDENTIFIER;
});

check('LoopDataSourceRegistry::replace() intentionally substitutes an already-registered source that register() would refuse as a conflict (an instrumented test decorator, e.g. tests/integration/fixtures/ObservedLoopSource)', static function () {
    $registry = new LoopDataSourceRegistry();
    $original = new FixedLoopDataSource('wrapped-source');
    $registry->register($original);

    $wrapper = new ConflictingFixedLoopDataSource('wrapped-source');
    $registry->replace($wrapper);

    return $registry->get('wrapped-source') === $wrapper;
});

check('LoopDataSourceRegistry::replace() still refuses a reserved identifier', static function () {
    try {
        (new LoopDataSourceRegistry())->replace(new FixedLoopDataSource('id'));
    } catch (\InvalidArgumentException $e) {
        return true;
    }

    return false;
});

check('TypeRegistry rejects registering the reserved identifier "id"', static function () {
    try {
        (new TypeRegistry())->register(new TypeDefinition('id', 'Reserved', 'block', 'fixture-extension'));
    } catch (\InvalidArgumentException $e) {
        return true;
    }

    return false;
});

check('TypeRegistry rejects two distinct type definitions claiming the same name', static function () {
    $registry = new TypeRegistry();
    $registry->register(new TypeDefinition('dup/fixture-type', 'A', 'block', 'fixture-extension'));

    try {
        // Same class, different label — genuinely distinct content, not an
        // idempotent re-declaration of the exact same type.
        $registry->register(new TypeDefinition('dup/fixture-type', 'B', 'block', 'fixture-extension'));
    } catch (\InvalidArgumentException $e) {
        return true;
    }

    return false;
});

check('TypeRegistry tolerates re-registering an identical type definition under a name it already owns', static function () {
    $registry = new TypeRegistry();
    $registry->register(new TypeDefinition('idempotent/fixture-type', 'Same', 'block', 'fixture-extension'));
    $registry->register(new TypeDefinition('idempotent/fixture-type', 'Same', 'block', 'fixture-extension'));

    return $registry->get('idempotent/fixture-type') instanceof TypeDefinition;
});

check('TypeRegistry reports an unregistered name as unavailable and a registered one as available', static function () {
    $registry = new TypeRegistry();
    $registry->register(new TypeDefinition('available/fixture-type', 'Available', 'block', 'fixture-extension'));

    $available = $registry->get_availability('available/fixture-type');
    $unknown   = $registry->get_availability('never-registered/fixture-type');

    return $available->is_available()
        && $unknown->is_unavailable()
        && $unknown->get_reason_code() === FeatureAvailability::UNKNOWN_IDENTIFIER;
});

/* ── Task 7.2: LoopDataSourceRegistry/LoopItemStyles/LoopItemIdentity/ ── */

check('LoopItemIdentity::sanitize() keeps letters, digits, underscore and hyphen', static function () {
    return LoopItemIdentity::sanitize('Row-3_of-Post42') === 'Row-3_of-Post42';
});

check('LoopItemIdentity::sanitize() strips everything else', static function () {
    return LoopItemIdentity::sanitize('42<script>.foo bar/baz') === '42scriptfoobarbaz';
});

/* ── Loop\Context\WordPressPostScope ───────────────────────────────── */

check('WordPressPostScope::exit() restores an outer acquisition\'s exact state after a nested inner one exits, not merely resetting the globals', static function () {
    $outer = new WordPressPostScope();
    $outer->enter(new WP_Post(501, ['post_content' => 'outer']));
    $outer_post_before = $GLOBALS['post'];
    $outer_id_before = $GLOBALS['id'];

    $inner = new WordPressPostScope();
    $inner->enter(new WP_Post(502, ['post_content' => 'inner']));
    $inner_ok = $GLOBALS['post']->ID === 502 && $GLOBALS['id'] === 502;
    $inner->exit();

    $restored = $GLOBALS['post'] === $outer_post_before && $GLOBALS['id'] === $outer_id_before;
    $outer->exit();

    return $inner_ok && $restored;
});

check('WordPressPostScope::exit() removes a global that was unset before enter(), rather than leaving it at the entered value', static function () {
    foreach (['post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages'] as $key) {
        unset($GLOBALS[$key]);
    }

    $scope = new WordPressPostScope();
    $scope->enter(new WP_Post(503));
    $entered = array_key_exists('post', $GLOBALS) && array_key_exists('id', $GLOBALS);
    $scope->exit();

    return $entered && !array_key_exists('post', $GLOBALS) && !array_key_exists('id', $GLOBALS);
});

check('WordPressPostScope::exit() is a safe no-op if called without a matching enter()', static function () {
    (new WordPressPostScope())->exit();

    return true;
});

/* ── Loop\Rendering\TemplateRecursionGuard ─────────────────────────── */

check('TemplateRecursionGuard: sequential reuse of the same template across non-nested items succeeds', static function () {
    TemplateRecursionGuard::enter(701);
    TemplateRecursionGuard::exit();
    TemplateRecursionGuard::enter(701);
    TemplateRecursionGuard::exit();

    return TemplateRecursionGuard::depth() === 0;
});

check('TemplateRecursionGuard: sibling (non-nested) reuse of the same template while a DIFFERENT template is active still succeeds', static function () {
    TemplateRecursionGuard::enter(710);
    TemplateRecursionGuard::enter(711);
    TemplateRecursionGuard::exit();
    TemplateRecursionGuard::enter(711); // 711 again, sequentially, while 710 is still active
    TemplateRecursionGuard::exit();
    TemplateRecursionGuard::exit();

    return TemplateRecursionGuard::depth() === 0;
});

check('TemplateRecursionGuard: a direct cycle (template entering itself while still active) is refused', static function () {
    TemplateRecursionGuard::enter(720);
    $threw = false;

    try {
        TemplateRecursionGuard::enter(720);
    } catch (TemplateRecursionException $e) {
        $threw = true;
    }

    TemplateRecursionGuard::exit();

    return $threw && TemplateRecursionGuard::depth() === 0;
});

check('TemplateRecursionGuard: an indirect cycle (A embeds B embeds A) is refused, not just a direct self-embed', static function () {
    TemplateRecursionGuard::enter(730); // A
    TemplateRecursionGuard::enter(731); // B, nested inside A
    $threw = false;

    try {
        TemplateRecursionGuard::enter(730); // A again, nested inside B — indirect cycle
    } catch (TemplateRecursionException $e) {
        $threw = true;
    }

    TemplateRecursionGuard::exit();
    TemplateRecursionGuard::exit();

    return $threw && TemplateRecursionGuard::depth() === 0;
});

check('TemplateRecursionGuard: ten distinct, non-repeating levels of nesting succeed; an eleventh is refused purely on depth', static function () {
    for ($i = 0; $i < TemplateRecursionGuard::MAX_DEPTH; $i++) {
        TemplateRecursionGuard::enter(800 + $i);
    }
    $depth_at_ten = TemplateRecursionGuard::depth();
    $threw = false;

    try {
        TemplateRecursionGuard::enter(999); // a brand new id — not a cycle, purely over depth
    } catch (TemplateRecursionException $e) {
        $threw = true;
    }

    for ($i = 0; $i < TemplateRecursionGuard::MAX_DEPTH; $i++) {
        TemplateRecursionGuard::exit();
    }

    return $depth_at_ten === TemplateRecursionGuard::MAX_DEPTH && $threw && TemplateRecursionGuard::depth() === 0;
});

/* ── Loop\Rendering\LoopRenderIdentity / LoopRenderContext ─────────── */

check('LoopRenderIdentity::key() differs across placements even for the same numeric domain item id', static function () {
    $a = new LoopRenderIdentity('widget-a', 'template-1', 'posts', '42');
    $b = new LoopRenderIdentity('widget-b', 'template-1', 'posts', '42');

    return $a->key() !== $b->key();
});

check('LoopRenderIdentity::key() differs across sources even for the same placement/template and numeric domain item id', static function () {
    $posts = new LoopRenderIdentity('widget-a', 'template-1', 'posts', '42');
    $terms = new LoopRenderIdentity('widget-a', 'template-1', 'terms', '42');

    return $posts->key() !== $terms->key();
});

check('LoopRenderIdentity::key() differs across templates even for the same placement/source and numeric domain item id', static function () {
    $one = new LoopRenderIdentity('widget-a', 'template-1', 'posts', '42');
    $two = new LoopRenderIdentity('widget-a', 'template-2', 'posts', '42');

    return $one->key() !== $two->key();
});

check('LoopRenderIdentity::key() differs by occurrence and by AJAX batch context, all else equal', static function () {
    $first = new LoopRenderIdentity('widget-a', 'template-1', 'posts', '42', 0, 'page-1');
    $second_occurrence = new LoopRenderIdentity('widget-a', 'template-1', 'posts', '42', 1, 'page-1');
    $second_batch = new LoopRenderIdentity('widget-a', 'template-1', 'posts', '42', 0, 'page-2');

    return $first->key() !== $second_occurrence->key() && $first->key() !== $second_batch->key() && $second_occurrence->key() !== $second_batch->key();
});

check('LoopRenderIdentity::key() is identical for two separately-constructed identities with the exact same components (deterministic, not random)', static function () {
    $a = new LoopRenderIdentity('widget-a', 'template-1', 'posts', '42', 3, 'page-2');
    $b = new LoopRenderIdentity('widget-a', 'template-1', 'posts', '42', 3, 'page-2');

    return $a->key() === $b->key();
});

check('LoopRenderIdentity::key() keeps the sanitized item identity readable as its prefix', static function () {
    $identity = new LoopRenderIdentity('widget-a', 'template-1', 'posts', '42');

    return strpos($identity->key(), '42-') === 0;
});

check('LoopRenderIdentity::selector() returns only characters safe for a CSS class token / HTML id attribute', static function () {
    $identity = new LoopRenderIdentity('widget <a>', 'template/1', 'posts;drop', "42'\"", 0, 'page 1');

    return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $identity->selector());
});

check('LoopRenderContext::push()/pop()/current() track the innermost active render identity, restoring the outer one after an inner pop', static function () {
    $outer = new LoopRenderIdentity('widget-a', 'template-1', 'posts', '1');
    $inner = new LoopRenderIdentity('widget-a', 'template-1', 'posts', '2');

    LoopRenderContext::push($outer);
    $outer_current = LoopRenderContext::current();
    LoopRenderContext::push($inner);
    $inner_current = LoopRenderContext::current();
    LoopRenderContext::pop();
    $restored = LoopRenderContext::current();
    LoopRenderContext::pop();

    return $outer_current === $outer && $inner_current === $inner && $restored === $outer && LoopRenderContext::current() === null;
});

/* ── Loop\Items\PostLoopItem ────────────────────────── */

check('PostLoopItem::get_identity() returns the sanitized post ID as a string', static function () {
    $item = new PostLoopItem(new WP_Post(42));

    return $item->get_identity() === '42' && is_string($item->get_identity());
});

check('PostLoopItem::get_field(\'ID\') returns the post ID', static function () {
    $item = new PostLoopItem(new WP_Post(7));

    return $item->get_field('ID') === 7;
});

check('PostLoopItem::get_field(\'content\') runs the_content filters over post_content', static function () {
    $item = new PostLoopItem(new WP_Post(8, ['post_content' => 'hello world']));

    return $item->get_field('content') === 'hello world';
});

check('PostLoopItem::get_field() falls back to post meta for an unrecognized key when ACF is not active', static function () {
    update_post_meta(9, 'my_custom_field', 'custom value');
    $item = new PostLoopItem(new WP_Post(9));

    return $item->get_field('my_custom_field') === 'custom value';
});

check('PostLoopItem::get_field() returns empty string for an unrecognized key with no meta set', static function () {
    $item = new PostLoopItem(new WP_Post(10));

    return $item->get_field('nonexistent_field') === '';
});

/* ── Task 7.5: Integrations/Acf — Widgets/Loop no longer imports Dynamic ── */
/* Tags internals; the field-reader contract is optional and injectable    */

check('Elemacy\Integrations\Acf\UnavailableFieldReader always returns null, no ACF/post-meta lookup at all', static function () {
    update_post_meta(11, 'a_field_with_real_meta', 'should never be seen');
    $reader = new \Elemacy\Integrations\Acf\UnavailableFieldReader();

    return $reader->get_field_value('a_field_with_real_meta', 11) === null
        && $reader instanceof \Elemacy\Integrations\Acf\Contracts\FieldReaderInterface;
});

final class FixtureFieldReader implements \Elemacy\Integrations\Acf\Contracts\FieldReaderInterface
{
    /** @var array<int, array{0: string, 1: mixed}> */
    public array $calls = [];

    public function get_field_value(string $key, $post_id = false)
    {
        $this->calls[] = [$key, $post_id];

        return 'fixture:' . $key;
    }
}

check('PostLoopItem::get_field() delegates an unrecognized key to an injected FieldReaderInterface instead of AcfFieldReader', static function () {
    $reader = new FixtureFieldReader();
    $item = new PostLoopItem(new WP_Post(12), $reader);

    $value = $item->get_field('some_custom_field');

    return $value === 'fixture:some_custom_field' && $reader->calls === [['some_custom_field', 12]];
});

check('PostLoopItem::enter()/exit() restore an outer item\'s exact global state after a nested inner item exits', static function () {
    $outer = new PostLoopItem(new WP_Post(601));
    $outer->enter();
    $outer_post_before = $GLOBALS['post'];

    $inner = new PostLoopItem(new WP_Post(602));
    $inner->enter();
    $inner_ok = $GLOBALS['post']->ID === 602;
    $inner->exit();

    $restored = $GLOBALS['post'] === $outer_post_before && $GLOBALS['post']->ID === 601;
    $outer->exit();

    return $inner_ok && $restored;
});

check('PostLoopItem::exit() without a prior enter() is a safe no-op', static function () {
    (new PostLoopItem(new WP_Post(603)))->exit();

    return true;
});

check('TermLoopItem::get_field() delegates an unrecognized key to an injected FieldReaderInterface with the "term_{id}" post-id shape', static function () {
    $reader = new FixtureFieldReader();
    $item = new TermLoopItem(new WP_Term(13, 'category'), $reader);

    $value = $item->get_field('some_custom_field');

    return $value === 'fixture:some_custom_field' && $reader->calls === [['some_custom_field', 'term_13']];
});

check('PostLoopItem/TermLoopItem no longer import any Dynamic Tags class (Widgets/Loop has no lateral module dependency)', static function () {
    foreach ([PostLoopItem::class, TermLoopItem::class] as $class) {
        $source = file_get_contents((new ReflectionClass($class))->getFileName());

        if (false !== strpos($source, 'DynamicTags')) {
            return false;
        }
    }

    return true;
});

/* ── Task 7.6: PreviewContextProviderInterface — Core no longer imports ── */
/* a concrete Loop context service                                       */

final class TrackedPreviewLoopItem implements LoopItemInterface
{
    public int $enter_calls = 0;
    public int $exit_calls = 0;

    public function get_identity(): string
    {
        return 'tracked-preview-item';
    }

    public function get_field(string $key)
    {
        return null;
    }

    public function enter(): void
    {
        $this->enter_calls++;
    }

    public function exit(): void
    {
        $this->exit_calls++;
    }
}

check('LoopItemPreviewContextProvider::enter() pushes the item onto LoopContext and calls its own enter(), returning the item as the opaque token', static function () {
    $item = new TrackedPreviewLoopItem();
    $provider = new \Elemacy\Loop\Preview\LoopItemPreviewContextProvider();

    $token = $provider->enter($item);

    $result = $token === $item && $item->enter_calls === 1 && LoopContext::current() === $item;

    // Clean up regardless of the assertion above, so a failure here doesn't
    // leave a stale item on the shared stack for every later test.
    LoopContext::pop();

    return $result;
});

check('LoopItemPreviewContextProvider::leave() pops LoopContext and calls the token\'s own exit()', static function () {
    $item = new TrackedPreviewLoopItem();
    $provider = new \Elemacy\Loop\Preview\LoopItemPreviewContextProvider();

    $token = $provider->enter($item);
    $provider->leave($token);

    return $item->exit_calls === 1 && LoopContext::current() === null;
});

check('PreviewManager stores an injected PreviewContextProviderInterface instead of constructing the Loop implementation directly', static function () {
    $fixture_provider = new class implements \Elemacy\Elementor\Contracts\PreviewContextProviderInterface {
        public function enter($item)
        {
            return $item;
        }

        public function leave($context): void
        {
        }
    };

    $manager = new PreviewManager($fixture_provider);

    $ref = new ReflectionProperty(PreviewManager::class, 'loop_provider');
    $ref->setAccessible(true);

    return $ref->getValue($manager) === $fixture_provider;
});

check('The container builds PreviewManager with Loop\Preview\LoopItemPreviewContextProvider, bound to the contract by CoreServiceProvider', static function () {
    (new CoreServiceProvider())->register(Container::instance());
    $manager = Container::instance()->make(PreviewManager::class);

    $ref = new ReflectionProperty(PreviewManager::class, 'loop_provider');
    $ref->setAccessible(true);

    return $ref->getValue($manager) instanceof \Elemacy\Loop\Preview\LoopItemPreviewContextProvider;
});

check('PreviewManager depends only on the preview contract and imports no Elemacy\\Loop class', static function () {
    $source = elemacy_code_without_comments(ELEMACY_PATH . 'src/Elementor/Preview/PreviewManager.php');

    return false === strpos($source, 'Elemacy\\Loop\\');
});

check('CoreServiceProvider builds every bootstrap through the container rather than with new', static function () {
    $source = elemacy_code_without_comments(ELEMACY_PATH . 'src/Core/Providers/CoreServiceProvider.php');

    return false === strpos($source, '(new ')
        && substr_count($source, '$container->make(') === 11;
});

check('Core\Preview\PreviewManager and Core\Documents\PreviewablePageBase source files import no concrete Elemacy\Modules\Widgets class', static function () {
    // PreviewablePageBase extends an Elementor document class this fast
    // harness never stubs (its own editor/document machinery is out of
    // scope here — see the harness's own README), so its source is read by
    // file path directly rather than through ReflectionClass(), which would
    // force-autoload the whole real class and fatal on the missing parent.
    $files = [
        (new ReflectionClass(PreviewManager::class))->getFileName(),
        dirname(__DIR__, 2) . '/src/Elementor/Documents/PreviewablePageBase.php',
    ];

    foreach ($files as $file) {
        $source = file_get_contents($file);

        if (preg_match('/^use Elemacy\\\\Modules\\\\Widgets\\\\/m', $source)) {
            return false;
        }
    }

    return true;
});

/* ── Task 7.7: optional NativeTermItemInterface/NativeUserItemInterface — */
/* Dynamic Tags no longer checks a concrete Widgets/Loop item class       */

check('TermLoopItem/UserLoopItem implement the optional NativeTermItemInterface/NativeUserItemInterface capability contracts, without widening the base LoopItemInterface', static function () {
    $term_item = new TermLoopItem(new WP_Term(21, 'category'));
    $user_item = new UserLoopItem(new WP_User(22));

    return $term_item instanceof \Elemacy\Loop\Contracts\NativeTermItemInterface
        && $user_item instanceof \Elemacy\Loop\Contracts\NativeUserItemInterface
        && !method_exists(\Elemacy\Loop\Contracts\LoopItemInterface::class, 'get_term')
        && !method_exists(\Elemacy\Loop\Contracts\LoopItemInterface::class, 'get_user');
});

final class FixtureCustomTermItem implements LoopItemInterface, \Elemacy\Loop\Contracts\NativeTermItemInterface
{
    private WP_Term $term;

    public function __construct(WP_Term $term)
    {
        $this->term = $term;
    }

    public function get_identity(): string
    {
        return (string) $this->term->term_id;
    }

    public function get_field(string $key)
    {
        return null;
    }

    public function enter(): void
    {
    }

    public function exit(): void
    {
    }

    public function get_term(): WP_Term
    {
        return $this->term;
    }
}

check('Taxonomy::get_current_term() recognizes ANY item implementing NativeTermItemInterface on the loop stack, not only TermLoopItem specifically', static function () {
    $term = new WP_Term(23, 'category');
    LoopContext::push(new FixtureCustomTermItem($term));

    $result = Taxonomy::get_current_term();

    LoopContext::pop();

    return $result === $term;
});

check('Taxonomy::get_current_term() falls back to get_queried_object() outside a loop (no active item on the stack)', static function () {
    $GLOBALS['__queried_object'] = new WP_Term(24, 'category');

    $result = Taxonomy::get_current_term();

    $GLOBALS['__queried_object'] = null;

    return $result instanceof WP_Term && $result->term_id === 24;
});

check('Taxonomy::get_current_term() returns null outside a loop when the queried object is not a term (e.g. a normal page)', static function () {
    $GLOBALS['__queried_object'] = new WP_Post(25);

    $result = Taxonomy::get_current_term();

    $GLOBALS['__queried_object'] = null;

    return $result === null;
});

check('User::get_current_user() recognizes a UserLoopItem on the loop stack via the NativeUserItemInterface capability, and user_email resolves correctly through it', static function () {
    $user = new WP_User(26, ['user_email' => 'author@example.test']);
    $item = new UserLoopItem($user);
    LoopContext::push($item);

    $result = User::get_current_user();
    $email_field = $item->get_field('user_email');

    LoopContext::pop();

    return $result === $user && $email_field === 'author@example.test';
});

check('User::get_current_user() falls back to get_queried_object() outside a loop (e.g. an author archive)', static function () {
    $GLOBALS['__queried_object'] = new WP_User(27);

    $result = User::get_current_user();

    $GLOBALS['__queried_object'] = null;

    return $result instanceof WP_User && $result->ID === 27;
});

check('Modules\DynamicTags\Services\{Taxonomy,User} source files import no concrete Elemacy\Modules\Widgets class', static function () {
    foreach ([Taxonomy::class, User::class] as $class) {
        $source = file_get_contents((new ReflectionClass($class))->getFileName());

        if (preg_match('/^use Elemacy\\\\Modules\\\\Widgets\\\\/m', $source)) {
            return false;
        }
    }

    return true;
});

/* ── Task 7.8: LoopGrid/LoopCarousel import canonical Loop/ contracts ── */

check('LoopGrid.php/LoopCarousel.php obtain every loop collaborator from the shared Loop layer: no Widgets-module Services/Contracts/Support/DTO loop import remains', static function () {
    $files = [
        dirname(__DIR__, 2) . '/src/Modules/Widgets/Widgets/LoopGrid.php',
        dirname(__DIR__, 2) . '/src/Modules/Widgets/Widgets/LoopCarousel.php',
    ];

    foreach ($files as $file) {
        $source = file_get_contents($file);

        if (preg_match('/^use Elemacy\\\\Modules\\\\Widgets\\\\(Services|Contracts|Support|DTO)\\\\/m', $source)) {
            return false;
        }

        if (false === strpos($source, 'use Elemacy\\Loop\\Contracts\\LoopDataSourceInterface;')
            || false === strpos($source, 'use Elemacy\\Loop\\Rendering\\LoopRenderer;')) {
            return false;
        }
    }

    return true;
});

check('LoopGrid.php/LoopCarousel.php/AjaxPaginationController.php delegate per-item rendering to Loop\\Rendering\\LoopRenderer, not an inline render loop', static function () {
    $files = [
        dirname(__DIR__, 2) . '/src/Modules/Widgets/Widgets/LoopGrid.php',
        dirname(__DIR__, 2) . '/src/Modules/Widgets/Widgets/LoopCarousel.php',
        dirname(__DIR__, 2) . '/src/Modules/Widgets/Controllers/AjaxPaginationController.php',
    ];

    foreach ($files as $file) {
        $source = file_get_contents($file);

        if (false === strpos($source, 'use Elemacy\\Loop\\Rendering\\LoopRenderer;')
            || false === strpos($source, '->render_item(')
            || false !== strpos($source, 'get_builder_content_for_display')) {
            return false;
        }
    }

    return true;
});

check('LoopGrid.php no longer defines render_pagination_html(); LoopGrid.php/AjaxPaginationController.php both delegate to Loop\\Pagination\\PaginationRenderer', static function () {
    $loop_grid = file_get_contents(dirname(__DIR__, 2) . '/src/Modules/Widgets/Widgets/LoopGrid.php');

    if (false !== strpos($loop_grid, 'function render_pagination_html')) {
        return false;
    }

    foreach ([
        dirname(__DIR__, 2) . '/src/Modules/Widgets/Widgets/LoopGrid.php',
        dirname(__DIR__, 2) . '/src/Modules/Widgets/Controllers/AjaxPaginationController.php',
    ] as $file) {
        $source = file_get_contents($file);

        if (false === strpos($source, 'use Elemacy\\Loop\\Pagination\\PaginationRenderer;') || false === strpos($source, '->render(')) {
            return false;
        }
    }

    return true;
});

/* ── Task 7.9: PostsQuery/PostsPolicy extracted; PostsDataSource keeps ── */
/* only what needs an Elementor widget/document instance                 */

final class FixtureSentinelPostsQuery extends PostsQuery
{
    public array $get_items_calls = [];
    public array $sanitize_calls = [];

    public function get_items(array $settings): LoopResultDTO
    {
        $this->get_items_calls[] = $settings;

        return new LoopResultDTO([], 999, 1, true);
    }

    public function sanitize_ajax_settings(array $raw_settings, int $paged): array
    {
        $this->sanitize_calls[] = [$raw_settings, $paged];

        return ['sentinel' => true];
    }
}

final class FixtureSentinelPostsPolicy extends PostsPolicy
{
    public array $calls = [];

    public function get_public_settings(array $settings, array $context, int $page): array
    {
        $this->calls[] = [$settings, $context, $page];

        return ['sentinel_public' => true];
    }
}

check('PostsDataSource constructor stores injected PostsQuery/PostsPolicy collaborators instead of constructing its own', static function () {
    $query = new FixtureSentinelPostsQuery();
    $policy = new FixtureSentinelPostsPolicy();
    $source = new PostsDataSource($query, $policy);

    $query_ref = new ReflectionProperty(PostsDataSource::class, 'query');
    $query_ref->setAccessible(true);
    $policy_ref = new ReflectionProperty(PostsDataSource::class, 'policy');
    $policy_ref->setAccessible(true);

    return $query_ref->getValue($source) === $query && $policy_ref->getValue($source) === $policy;
});

check('PostsDataSource with no injected collaborators defaults to real PostsQuery/PostsPolicy instances (PostsPolicy sharing the same PostsQuery instance)', static function () {
    $source = new PostsDataSource();

    $query_ref = new ReflectionProperty(PostsDataSource::class, 'query');
    $query_ref->setAccessible(true);
    $policy_ref = new ReflectionProperty(PostsDataSource::class, 'policy');
    $policy_ref->setAccessible(true);

    $query = $query_ref->getValue($source);
    $policy = $policy_ref->getValue($source);

    $policy_query_ref = new ReflectionProperty(PostsPolicy::class, 'query');
    $policy_query_ref->setAccessible(true);

    return $query instanceof PostsQuery && $policy instanceof PostsPolicy
        && $policy_query_ref->getValue($policy) === $query;
});

check('PostsDataSource::get_items()/sanitize_ajax_settings()/get_public_settings() genuinely delegate to the injected collaborators, not a reimplementation', static function () {
    $query = new FixtureSentinelPostsQuery();
    $policy = new FixtureSentinelPostsPolicy();
    $source = new PostsDataSource($query, $policy);

    $items_result = $source->get_items(['a' => 1]);
    $sanitized = $source->sanitize_ajax_settings(['raw' => 1], 2);
    $public = $source->get_public_settings(['s' => 1], ['c' => 1], 3);

    return $items_result->total_items === 999
        && $query->get_items_calls === [['a' => 1]]
        && $sanitized === ['sentinel' => true]
        && $query->sanitize_calls === [[['raw' => 1], 2]]
        && $public === ['sentinel_public' => true]
        && $policy->calls === [[['s' => 1], ['c' => 1], 3]];
});

check('Loop\Sources\PostsQuery and Loop\Pagination\Policies\PostsPolicy do not require an Elementor widget/document instance (no Widget_Base/PreviewablePageBase reference in either source file)', static function () {
    foreach ([PostsQuery::class, PostsPolicy::class] as $class) {
        $source = file_get_contents((new ReflectionClass($class))->getFileName());

        if (false !== strpos($source, 'Widget_Base') || false !== strpos($source, 'PreviewablePageBase')) {
            return false;
        }
    }

    return true;
});

/* ── Task 7.10: TermsQuery/TermsPolicy and UsersQuery/UsersPolicy      ── */
/* extracted; TaxonomyDataSource/UsersDataSource keep only their controls */

final class FixtureSentinelTermsQuery extends TermsQuery
{
    public array $get_items_calls = [];
    public array $sanitize_calls = [];
    public int $queried_term_id = 0;

    public function get_items(array $settings): LoopResultDTO
    {
        $this->get_items_calls[] = $settings;

        return new LoopResultDTO([], 888, 1, true);
    }

    public function sanitize_ajax_settings(array $raw_settings, int $paged): array
    {
        $this->sanitize_calls[] = [$raw_settings, $paged];

        return ['sentinel_terms' => true];
    }

    public function get_current_queried_term_id(): int
    {
        return $this->queried_term_id;
    }
}

final class FixtureSentinelTermsPolicy extends TermsPolicy
{
    public array $calls = [];

    public function get_public_settings(array $settings, array $context, int $page): array
    {
        $this->calls[] = [$settings, $context, $page];

        return ['sentinel_terms_public' => true];
    }
}

check('TaxonomyDataSource constructor stores injected TermsQuery/TermsPolicy, defaults to real instances sharing one TermsQuery, and its methods genuinely delegate', static function () {
    $query = new FixtureSentinelTermsQuery();
    $query->queried_term_id = 42;
    $policy = new FixtureSentinelTermsPolicy();
    $source = new TaxonomyDataSource($query, $policy);

    $query_ref = new ReflectionProperty(TaxonomyDataSource::class, 'query');
    $query_ref->setAccessible(true);
    $policy_ref = new ReflectionProperty(TaxonomyDataSource::class, 'policy');
    $policy_ref->setAccessible(true);

    if ($query_ref->getValue($source) !== $query || $policy_ref->getValue($source) !== $policy) {
        return false;
    }

    $items_result = $source->get_items(['a' => 1]);
    $sanitized = $source->sanitize_ajax_settings(['raw' => 1], 2);
    $public = $source->get_public_settings(['s' => 1], ['c' => 1], 3);

    $default_source = new TaxonomyDataSource();
    $default_query_ref = $query_ref->getValue($default_source);
    $default_policy_query_ref = new ReflectionProperty(TermsPolicy::class, 'query');
    $default_policy_query_ref->setAccessible(true);

    return $items_result->total_items === 888
        && $query->get_items_calls === [['a' => 1]]
        && $sanitized === ['sentinel_terms' => true]
        && $public === ['sentinel_terms_public' => true]
        && $policy->calls === [[['s' => 1], ['c' => 1], 3]]
        && $default_query_ref instanceof TermsQuery
        && $default_policy_query_ref->getValue($policy_ref->getValue($default_source)) === $default_query_ref;
});

final class FixtureSentinelUsersQuery extends UsersQuery
{
    public array $get_items_calls = [];
    public array $sanitize_calls = [];

    public function get_items(array $settings): LoopResultDTO
    {
        $this->get_items_calls[] = $settings;

        return new LoopResultDTO([], 777, 1, true);
    }

    public function sanitize_ajax_settings(array $raw_settings, int $paged): array
    {
        $this->sanitize_calls[] = [$raw_settings, $paged];

        return ['sentinel_users' => true];
    }
}

final class FixtureSentinelUsersPolicy extends UsersPolicy
{
    public array $calls = [];

    public function get_public_settings(array $settings, array $context, int $page): array
    {
        $this->calls[] = [$settings, $context, $page];

        return ['sentinel_users_public' => true];
    }
}

check('UsersDataSource constructor stores injected UsersQuery/UsersPolicy, defaults to real instances sharing one UsersQuery, and its methods genuinely delegate', static function () {
    $query = new FixtureSentinelUsersQuery();
    $policy = new FixtureSentinelUsersPolicy();
    $source = new UsersDataSource($query, $policy);

    $query_ref = new ReflectionProperty(UsersDataSource::class, 'query');
    $query_ref->setAccessible(true);
    $policy_ref = new ReflectionProperty(UsersDataSource::class, 'policy');
    $policy_ref->setAccessible(true);

    if ($query_ref->getValue($source) !== $query || $policy_ref->getValue($source) !== $policy) {
        return false;
    }

    $items_result = $source->get_items(['a' => 1]);
    $sanitized = $source->sanitize_ajax_settings(['raw' => 1], 2);
    $public = $source->get_public_settings(['s' => 1], ['c' => 1], 3);

    $default_source = new UsersDataSource();
    $default_query = $query_ref->getValue($default_source);
    $default_policy_query_ref = new ReflectionProperty(UsersPolicy::class, 'query');
    $default_policy_query_ref->setAccessible(true);

    return $items_result->total_items === 777
        && $query->get_items_calls === [['a' => 1]]
        && $sanitized === ['sentinel_users' => true]
        && $public === ['sentinel_users_public' => true]
        && $policy->calls === [[['s' => 1], ['c' => 1], 3]]
        && $default_query instanceof UsersQuery
        && $default_policy_query_ref->getValue($policy_ref->getValue($default_source)) === $default_query;
});

check('Loop\Sources\{TermsQuery,UsersQuery} and Loop\Pagination\Policies\{TermsPolicy,UsersPolicy} do not require an Elementor widget/document instance', static function () {
    foreach ([TermsQuery::class, UsersQuery::class, TermsPolicy::class, UsersPolicy::class] as $class) {
        $source = file_get_contents((new ReflectionClass($class))->getFileName());

        if (false !== strpos($source, 'Widget_Base') || false !== strpos($source, 'PreviewablePageBase')) {
            return false;
        }
    }

    return true;
});

/* ── DynamicTags\Tags\Base\PostContent (Elementor loaded) ────────── */

/**
 * Returns a canned builder-content string per post id (empty for any id
 * with no entry, matching get_builder_content()'s own "not built with
 * Elementor" outcome), and records the $with_css argument it was called
 * with so tests can confirm PostContent always requests it inline.
 */
final class FakePostContentFrontend
{
    /** @var array<int, string> */
    public array $builder_content = [];

    /** @var bool[] */
    public array $with_css_seen = [];

    public function get_builder_content($post_id, $with_css = false)
    {
        $this->with_css_seen[] = $with_css;

        return $this->builder_content[$post_id] ?? '';
    }
}

/**
 * Elementor's real Styles_Renderer isn't stubbed (see the AtomicStylesRenderer
 * section above) - render_base() is overridden here with a canned value so
 * these tests can isolate PostContent's own composition of the two CSS
 * halves, without needing to fake Elementor's atomic-widgets internals.
 */
final class FixedCssAtomicStylesRenderer extends AtomicStylesRenderer
{
    /** @var string */
    public $css = '';

    public function render_base(int $post_id): string
    {
        return $this->css;
    }
}

final class TestablePostContent extends PostContent
{
    /** @var string */
    public $atomic_css = '';

    protected function atomic_styles_renderer()
    {
        $renderer = new FixedCssAtomicStylesRenderer();
        $renderer->css = $this->atomic_css;

        return $renderer;
    }
}

function reset_post_content_elementor(): void
{
    \Elementor\Plugin::$instance = new \Elementor\Plugin();
    \Elementor\Plugin::$instance->editor = new \Elementor\Core\Editor\Editor();
    \Elementor\Plugin::$instance->frontend = new FakePostContentFrontend();
}

check('PostContent renders Elementor builder content with atomic CSS when the post is built with Elementor', static function () {
    reset_post_content_elementor();
    \Elementor\Plugin::$instance->frontend->builder_content[228] = '<div class="elementor elementor-228">styled markup</div>';

    $GLOBALS['__current_post_id'] = 228;

    $tag = new TestablePostContent();
    $tag->atomic_css = '.e-38ce628{background-color:#ecd3d3}';

    ob_start();
    $tag->render();
    $output = ob_get_clean();

    return false !== strpos($output, '.e-38ce628{background-color:#ecd3d3}')
        && false !== strpos($output, '<div class="elementor elementor-228">styled markup</div>')
        && \Elementor\Plugin::$instance->frontend->with_css_seen === [true];
});

check('PostContent prints no <style> tag when the post has no atomic CSS', static function () {
    reset_post_content_elementor();
    \Elementor\Plugin::$instance->frontend->builder_content[229] = '<div class="elementor elementor-229">markup</div>';

    $GLOBALS['__current_post_id'] = 229;

    ob_start();
    (new TestablePostContent())->render();
    $output = ob_get_clean();

    return $output === '<div class="elementor elementor-229">markup</div>';
});

check('PostContent falls back to classic content unchanged when the post is not built with Elementor', static function () {
    reset_post_content_elementor();
    // No builder_content entry for 55 -> get_builder_content() returns ''.

    $GLOBALS['__current_post_id'] = 55;
    $GLOBALS['__current_post_content'] = '<p>Plain classic content</p>';

    ob_start();
    (new PostContent())->render();
    $output = ob_get_clean();

    return $output === wp_kses_post('<p>Plain classic content</p>');
});

check('PostContent restores the prior edit-mode state after rendering', static function () {
    reset_post_content_elementor();
    \Elementor\Plugin::$instance->frontend->builder_content[230] = '<div>markup</div>';
    \Elementor\Plugin::$instance->editor->set_edit_mode(true);

    $GLOBALS['__current_post_id'] = 230;

    ob_start();
    (new PostContent())->render();
    ob_get_clean();

    return true === \Elementor\Plugin::$instance->editor->is_edit_mode();
});

/**
 * Simulates the post targeted by the tag itself containing another use of
 * the same tag for the same post - the recursion case PostContent's guard
 * exists for, since Elementor's own document-switching has no cycle
 * protection of its own (see design.md).
 */
final class ReentrantPostContentFrontend
{
    /** @var int */
    public $calls = 0;

    public function get_builder_content($post_id, $with_css = false)
    {
        $this->calls++;

        if (1 === $this->calls) {
            ob_start();
            (new PostContent())->render();
            $nested_output = ob_get_clean();

            return '<div>outer[' . $nested_output . ']</div>';
        }

        return '<div>should not be reached</div>';
    }
}

check('PostContent does not recurse when its own content contains another use of itself for the same post', static function () {
    \Elementor\Plugin::$instance = new \Elementor\Plugin();
    \Elementor\Plugin::$instance->editor = new \Elementor\Core\Editor\Editor();
    $frontend = new ReentrantPostContentFrontend();
    \Elementor\Plugin::$instance->frontend = $frontend;

    $GLOBALS['__current_post_id'] = 300;

    ob_start();
    (new PostContent())->render();
    $output = ob_get_clean();

    // get_builder_content() must only ever be called once - the nested
    // re-entry is blocked by the guard before it can call it a second time.
    return 1 === $frontend->calls && '<div>outer[]</div>' === $output;
});

check('PostContent falls back to classic content when its target post is the document already being rendered', static function () {
    // Simulates the tag embedded directly in a post's own design, viewed on
    // that post's own page with no Theme Builder template involved: the
    // post's natural render is already in progress when the tag fires, so
    // embedding it again here would just duplicate the whole document
    // nested inside itself. $rendering alone cannot catch this (nothing has
    // entered this tag's own render() yet), so this is exactly what
    // is_own_document_already_rendering() exists to catch.
    reset_post_content_elementor();
    \Elementor\Plugin::$instance->frontend->builder_content[500] = '<div>should not be used</div>';
    \Elementor\Plugin::$instance->documents->current = new \Elementor\Core\Base\Document(500);

    $GLOBALS['__current_post_id'] = 500;
    $GLOBALS['__current_post_content'] = '<p>Natural content</p>';

    ob_start();
    (new PostContent())->render();
    $output = ob_get_clean();

    return $output === wp_kses_post('<p>Natural content</p>');
});

check('PostContent still embeds a different post normally when some other document is currently rendering', static function () {
    // A Theme Builder template (a genuinely different document) is what's
    // actively rendering when the tag fires - this must still work exactly
    // as the main success-path test above.
    reset_post_content_elementor();
    \Elementor\Plugin::$instance->frontend->builder_content[228] = '<div class="elementor elementor-228">styled markup</div>';
    \Elementor\Plugin::$instance->documents->current = new \Elementor\Core\Base\Document(24);

    $GLOBALS['__current_post_id'] = 228;

    ob_start();
    (new PostContent())->render();
    $output = ob_get_clean();

    return '<div class="elementor elementor-228">styled markup</div>' === $output;
});

check('PostContent allows two separate, non-nested top-level renders for the same post', static function () {
    reset_post_content_elementor();
    \Elementor\Plugin::$instance->frontend->builder_content[400] = '<div>content</div>';

    $GLOBALS['__current_post_id'] = 400;

    ob_start();
    (new PostContent())->render();
    $first = ob_get_clean();

    ob_start();
    (new PostContent())->render();
    $second = ob_get_clean();

    return '<div>content</div>' === $first && '<div>content</div>' === $second;
});

check('PostContent renders the password form instead of an Elementor-built design when the target post is password-protected', static function () {
    reset_post_content_elementor();
    \Elementor\Plugin::$instance->frontend->builder_content[600] = '<div class="elementor elementor-600">secret design</div>';

    $GLOBALS['__current_post_id'] = 600;
    $GLOBALS['__password_required_post_ids'] = [600];

    ob_start();
    (new PostContent())->render();
    $output = ob_get_clean();

    $GLOBALS['__password_required_post_ids'] = [];

    return $output === get_the_password_form(600)
        && [] === \Elementor\Plugin::$instance->frontend->with_css_seen;
});

check('PostContent renders the password form instead of classic content when the target post is password-protected', static function () {
    reset_post_content_elementor();
    // No builder_content entry for 601 -> would fall to classic content if reached.

    $GLOBALS['__current_post_id'] = 601;
    $GLOBALS['__current_post_content'] = '<p>Secret classic content</p>';
    $GLOBALS['__password_required_post_ids'] = [601];

    ob_start();
    (new PostContent())->render();
    $output = ob_get_clean();

    $GLOBALS['__password_required_post_ids'] = [];

    return $output === get_the_password_form(601)
        && false === strpos($output, 'Secret classic content');
});

check('PostContent renders normally once the target post\'s password has already been entered', static function () {
    reset_post_content_elementor();
    \Elementor\Plugin::$instance->frontend->builder_content[602] = '<div class="elementor elementor-602">unlocked design</div>';

    $GLOBALS['__current_post_id'] = 602;
    $GLOBALS['__password_required_post_ids'] = []; // post_password_required() reports false, matching a supplied password.

    ob_start();
    (new PostContent())->render();
    $output = ob_get_clean();

    return '<div class="elementor elementor-602">unlocked design</div>' === $output;
});

/* ── Task 9.4: PostContent finally-based restoration (design.md D9) ── */

/**
 * Throws instead of returning content — simulates a render failure inside
 * the exceptional same-document path SameDocumentContentRenderer owns.
 */
final class ThrowingPostContentFrontend
{
    public function get_builder_content($post_id, $with_css = false)
    {
        throw new \RuntimeException('injected render failure for post ' . $post_id);
    }
}

check('PostContent::render() releases the recursion guard via finally even when the same-document render throws, so a later render for the same post is not permanently stuck', static function () {
    \Elementor\Plugin::$instance = new \Elementor\Plugin();
    \Elementor\Plugin::$instance->editor = new \Elementor\Core\Editor\Editor();
    \Elementor\Plugin::$instance->frontend = new ThrowingPostContentFrontend();

    $GLOBALS['__current_post_id'] = 700;
    $GLOBALS['__current_post_content'] = '<p>Classic fallback after failure</p>';

    $threw = false;

    try {
        (new PostContent())->render();
    } catch (\RuntimeException $e) {
        $threw = true;
    }

    if (!$threw) {
        return false;
    }

    // If the guard were stuck, this second render for the SAME post id
    // would be silently swallowed (isset(self::$rendering[700]) === true)
    // rather than reaching the classic-content fallback below.
    \Elementor\Plugin::$instance->frontend = new FakePostContentFrontend();
    ob_start();
    (new PostContent())->render();
    $output = ob_get_clean();

    return wp_kses_post('<p>Classic fallback after failure</p>') === $output;
});

check('PostContent::render() restores the prior edit-mode state via finally even when the same-document render throws', static function () {
    \Elementor\Plugin::$instance = new \Elementor\Plugin();
    \Elementor\Plugin::$instance->editor = new \Elementor\Core\Editor\Editor();
    \Elementor\Plugin::$instance->editor->set_edit_mode(true);
    \Elementor\Plugin::$instance->frontend = new ThrowingPostContentFrontend();

    $GLOBALS['__current_post_id'] = 701;

    try {
        (new PostContent())->render();
    } catch (\RuntimeException $e) {
        // Expected - assert the restoration below regardless.
    }

    return true === \Elementor\Plugin::$instance->editor->is_edit_mode();
});

check('SameDocumentContentRenderer::render() itself propagates the underlying exception rather than swallowing it (finally restores state, it does not convert a failure into a false success)', static function () {
    \Elementor\Plugin::$instance = new \Elementor\Plugin();
    \Elementor\Plugin::$instance->editor = new \Elementor\Core\Editor\Editor();
    \Elementor\Plugin::$instance->frontend = new ThrowingPostContentFrontend();

    try {
        (new \Elemacy\Elementor\Compatibility\SameDocumentContentRenderer())->render(702);
    } catch (\RuntimeException $e) {
        return 'injected render failure for post 702' === $e->getMessage();
    }

    return false;
});

check('SameDocumentContentRenderer::available() is false, degrading render() to an empty string, when the injected Capabilities reports Elementor absent', static function () {
    $renderer = new \Elemacy\Elementor\Compatibility\SameDocumentContentRenderer(new AllCapabilitiesAbsent());

    return false === $renderer->available() && '' === $renderer->render(703);
});

/* ── Task 7.12: architecture dependency boundary check ────────────── */

check('tests/architecture/check.php passes cleanly against the real source tree (layering, Elementor boundary and planning-reference rules, no allowlist)', static function () {
    $script = dirname(__DIR__) . '/architecture/check.php';
    $output = [];
    $exit_code = 0;

    exec('php ' . escapeshellarg($script) . ' 2>&1', $output, $exit_code);

    return 0 === $exit_code;
});

check('tests/architecture/check.php actually detects a real layering violation (not a script that trivially always passes)', static function () {
    // A throwaway fixture file with a brand-new name, never an existing real
    // source file — the scanner picks up anything under src/Core/, and this
    // avoids ever mutating a tracked file (only creates, then removes, one
    // that never existed before; a leftover on interruption is harmless).
    $fixture = dirname(__DIR__, 2) . '/src/Core/ZZArchitectureCheckFixture.php';
    $script = dirname(__DIR__) . '/architecture/check.php';

    file_put_contents($fixture, "<?php\nnamespace Elemacy\\Core;\ndefined('ABSPATH') || exit;\nuse Elemacy\\Modules\\Widgets\\Services\\LoopContext;\n");

    $output = [];
    $exit_code = 0;

    try {
        exec('php ' . escapeshellarg($script) . ' 2>&1', $output, $exit_code);
    } finally {
        unlink($fixture);
    }

    return 1 === $exit_code && false !== strpos(implode("\n", $output), 'ZZArchitectureCheckFixture.php');
});

// ── Current Query: its own data source, not a Posts post type ──────────

$current_query_main = static function (array $vars, array $posts = [], int $found = 0, int $pages = 0): WP_Query {
    $query = new WP_Query($vars);
    $query->posts = $posts;
    $query->found_posts = $found;
    $query->max_num_pages = $pages;

    return $query;
};

check('Current Query lists the main query\'s own posts and its found/max pages, reusing that query object when no add-on changes its vars', static function () use ($current_query_main) {
    $GLOBALS['wp_query'] = $current_query_main(['cat' => 7], [new WP_Post(1), new WP_Post(2)], 9, 5);
    $GLOBALS['__wp_query_args'] = [];

    try {
        $result = (new \Elemacy\Loop\Sources\CurrentQueryQuery())->get_items([]);
    } finally {
        $made_new_query = [] !== $GLOBALS['__wp_query_args'];
        unset($GLOBALS['wp_query']);
    }

    return !$made_new_query
        && 2 === count($result->items)
        && $result->items[0] instanceof \Elemacy\Loop\Items\PostLoopItem
        && 9 === $result->total_items
        && 5 === $result->max_num_pages
        && true === $result->supports_pagination;
});

check('Current Query ignores every authored loop setting: nothing in the widget settings reaches the query', static function () use ($current_query_main) {
    $GLOBALS['wp_query'] = $current_query_main(['s' => 'hello'], [new WP_Post(3)], 1, 1);
    $GLOBALS['__wp_query_args'] = [];

    try {
        $result = (new \Elemacy\Loop\Sources\CurrentQueryQuery())->get_items([
            'post_type' => 'page', 'posts_per_page' => 1, 'offset' => 50, 'orderby' => 'rand', 'meta_key' => 'x',
        ]);
    } finally {
        $made_new_query = [] !== $GLOBALS['__wp_query_args'];
        unset($GLOBALS['wp_query']);
    }

    return !$made_new_query && 1 === count($result->items);
});

check('Current Query runs a new WP_Query with the filtered vars only when an add-on changed them', static function () use ($current_query_main) {
    $GLOBALS['wp_query'] = $current_query_main(['cat' => 7, 'posts_per_page' => 10], [new WP_Post(1)], 1, 1);
    $GLOBALS['__wp_actions'] = [];
    $GLOBALS['__wp_query_args'] = [];

    $unchanged = static fn ($vars) => $vars;
    add_filter(Hooks::LOOP_CURRENT_QUERY_ARGS_FILTER, $unchanged);
    (new \Elemacy\Loop\Sources\CurrentQueryQuery())->get_items([]);
    $reused = [] === $GLOBALS['__wp_query_args'];

    $GLOBALS['__wp_actions'] = [];
    add_filter(Hooks::LOOP_CURRENT_QUERY_ARGS_FILTER, static function ($vars) {
        $vars['posts_per_page'] = 3;

        return $vars;
    });
    (new \Elemacy\Loop\Sources\CurrentQueryQuery())->get_items([]);
    $filtered = end($GLOBALS['__wp_query_args']);

    $GLOBALS['__wp_actions'] = [];
    unset($GLOBALS['wp_query']);

    return $reused && 3 === $filtered['posts_per_page'] && 7 === $filtered['cat'];
});

check('Current Query with no main query yields an empty result instead of an error', static function () {
    unset($GLOBALS['wp_query']);
    $result = (new \Elemacy\Loop\Sources\CurrentQueryQuery())->get_items([]);

    return [] === $result->items && 0 === $result->total_items;
});

// ── Load More / Infinite Scroll: the incremental pagination types ──────

check('Load More and Infinite Scroll are the incremental pagination types; the link types and none are not', static function () {
    $type = \Elemacy\Loop\Pagination\PaginationType::class;

    return $type::is_incremental('load_more') && $type::is_incremental('infinite_scroll')
        && !$type::is_incremental('numbers') && !$type::is_incremental('prev_next')
        && !$type::is_incremental('numbers_and_prev_next') && !$type::is_incremental('') && !$type::is_incremental(null)
        && ['numbers', 'prev_next', 'numbers_and_prev_next'] === $type::LINKS;
});

check('A grid pages in place when its AJAX switch is on or its type is incremental, and not otherwise', static function () {
    $limits = \Elemacy\Loop\Pagination\PublicLoopLimits::class;

    return $limits::is_ajax_paginated(['pagination_ajax' => 'yes', 'pagination_type' => 'numbers'])
        && $limits::is_ajax_paginated(['pagination_ajax' => 'no', 'pagination_type' => 'load_more'])
        && $limits::is_ajax_paginated(['pagination_type' => 'infinite_scroll'])
        && !$limits::is_ajax_paginated(['pagination_ajax' => 'no', 'pagination_type' => 'numbers'])
        && !$limits::is_ajax_paginated(['pagination_type' => 'prev_next'])
        && !$limits::is_ajax_paginated([]);
});

check('An incremental grid\'s first page is bounded like an AJAX grid\'s, and a link-type grid without AJAX is left as authored', static function () {
    $limits = \Elemacy\Loop\Pagination\PublicLoopLimits::class;

    foreach (['load_more', 'infinite_scroll'] as $type) {
        $bounded = $limits::bound_ajax_page_size(['pagination_type' => $type, 'pagination_ajax' => 'no', 'posts_per_page' => 5000], 'posts_per_page');
        if (100 !== $bounded['posts_per_page']) {
            return false;
        }
    }

    return 5000 === $limits::bound_ajax_page_size(['pagination_type' => 'numbers', 'pagination_ajax' => 'no', 'posts_per_page' => 5000], 'posts_per_page')['posts_per_page'];
});

check('Current Query treats a main query that has not run yet (null posts) as an empty list', static function () {
    $query = new WP_Query(['cat' => 7]);
    $query->posts = null;
    $GLOBALS['wp_query'] = $query;

    try {
        $result = (new \Elemacy\Loop\Sources\CurrentQueryQuery())->get_items([]);
    } finally {
        unset($GLOBALS['wp_query']);
    }

    return [] === $result->items;
});

check('CurrentQueryDataSource: own key and label, a public policy that carries no setting, never endpoint-served, no sanitiser', static function () {
    $source = new \Elemacy\Modules\Widgets\DataSources\CurrentQueryDataSource();

    $sanitise_refused = false;
    try {
        $source->sanitize_ajax_settings(['post_type' => 'post'], 1);
    } catch (\Elemacy\Http\Exceptions\ValidationException $error) {
        $sanitise_refused = true;
    }

    return 'current_query' === $source->get_key()
        && 'Current Query' === $source->get_label()
        && $source instanceof \Elemacy\Loop\Contracts\PageUrlPaginatedSourceInterface
        && $source instanceof \Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface
        && [] === $source->get_public_settings(['posts_per_page' => 50, 'offset' => 4], [], 1)
        && [] === $source->get_public_settings(['posts_per_page' => 50], ['post_id' => 1], 3)
        && $sanitise_refused;
});

check('CurrentQueryDataSource delegates get_items() and the preview item to its injected collaborators', static function () {
    $query = new class extends \Elemacy\Loop\Sources\CurrentQueryQuery {
        public array $calls = [];

        public function get_items(array $settings): LoopResultDTO
        {
            $this->calls[] = $settings;

            return new LoopResultDTO([], 42, 3, true);
        }
    };
    $preview_item = new \Elemacy\Loop\Items\PostLoopItem(new WP_Post(77));
    $preview = new class($preview_item) extends \Elemacy\Modules\Widgets\DataSources\PostsDataSource {
        public array $received = [];
        private $item;

        public function __construct($item)
        {
            parent::__construct();
            $this->item = $item;
        }

        public function resolve_preview_item(array $settings): ?\Elemacy\Loop\Contracts\LoopItemInterface
        {
            $this->received[] = $settings;

            return $this->item;
        }
    };
    $source = new \Elemacy\Modules\Widgets\DataSources\CurrentQueryDataSource($query, $preview);

    $result = $source->get_items(['a' => 1]);

    return 42 === $result->total_items
        && [['a' => 1]] === $query->calls
        && $source->resolve_preview_item(['preview_post' => 5]) === $preview_item
        && [[]] === $preview->received;
});

check('Posts offers real public post types only: no Current Query entry', static function () {
    $method = new ReflectionMethod(PostsDataSource::class, 'get_public_post_types');
    $method->setAccessible(true);
    $options = $method->invoke(new PostsDataSource());

    return !isset($options['current_query']) && isset($options['post']);
});

check('Posts query, policy and source no longer mention current_query anywhere in their code', static function () {
    $root = dirname(__DIR__, 2) . '/src/';

    foreach (['Loop/Sources/PostsQuery.php', 'Loop/Pagination/Policies/PostsPolicy.php', 'Modules/Widgets/DataSources/PostsDataSource.php'] as $file) {
        if (false !== strpos(file_get_contents($root . $file), 'current_query')) {
            return false;
        }
    }

    return true;
});

check('PostsQuery rejects post_type=current_query as an invalid post type', static function () {
    try {
        (new PostsQuery())->sanitize_ajax_settings(['post_type' => 'current_query'], 1);
    } catch (\Elemacy\Http\Exceptions\ValidationException $error) {
        return true;
    }

    return false;
});

check('PublicRenderPolicy::endpoint_source() refuses a page-URL-paginated source but still returns the others', static function () {
    $sources = new LoopDataSourceRegistry();
    $sources->register(new PostsDataSource());
    $sources->register(new \Elemacy\Modules\Widgets\DataSources\CurrentQueryDataSource());
    $policy = new \Elemacy\Loop\Pagination\PublicRenderPolicy(TypeRegistry::instance(), $sources, \Elemacy\Loop\Placement\PlacementResolverRegistry::instance());

    $refused = false;
    try {
        $policy->endpoint_source('current_query');
    } catch (\Elemacy\Http\Exceptions\ValidationException $error) {
        $refused = true;
    }

    return $refused
        && $policy->endpoint_source('posts')->get_key() === 'posts'
        && $policy->source('current_query')->get_key() === 'current_query';
});

check('tests/architecture/check.php fails on a planted planning reference in production source and names the file', static function () {
    $fixture = dirname(__DIR__, 2) . '/src/Support/ZZPlanningReferenceFixture.php';
    $script = dirname(__DIR__) . '/architecture/check.php';

    file_put_contents($fixture, "<?php\nnamespace Elemacy\\Support;\ndefined('ABSPATH') || exit;\n// Kept for design.md D7.\n");

    $output = [];
    $exit_code = 0;

    try {
        exec('php ' . escapeshellarg($script) . ' 2>&1', $output, $exit_code);
    } finally {
        unlink($fixture);
    }

    return 1 === $exit_code && false !== strpos(implode("\n", $output), 'ZZPlanningReferenceFixture.php');
});

check('tests/architecture/check.php fails when a persisted identifier is renamed without a migration, and names it', static function () {
    $script = dirname(__DIR__) . '/architecture/check.php';
    $list = tempnam(sys_get_temp_dir(), 'elemacy-identifiers-');
    // As if HeaderDocument had been renamed away from a value sites stored.
    file_put_contents($list, "<?php\nreturn [['id' => 'elemacy_hedaer', 'file' => 'src/Modules/ThemeBuilder/Documents/HeaderDocument.php']];\n");

    $output = [];
    $exit_code = 0;

    try {
        exec('ELEMACY_PERSISTED_IDENTIFIERS=' . escapeshellarg($list) . ' php ' . escapeshellarg($script) . ' 2>&1', $output, $exit_code);
    } finally {
        unlink($list);
    }

    return 1 === $exit_code && false !== strpos(implode("\n", $output), "'elemacy_hedaer' is no longer declared in src/Modules/ThemeBuilder/Documents/HeaderDocument.php");
});

check('tests/architecture/check.php accepts a renamed persisted identifier once a migration names the stored value', static function () {
    $script = dirname(__DIR__) . '/architecture/check.php';
    $list = tempnam(sys_get_temp_dir(), 'elemacy-identifiers-');
    $migration = dirname(__DIR__, 2) . '/src/Core/Migrations/ZZRenameFixture.php';
    file_put_contents($list, "<?php\nreturn [['id' => 'elemacy_hedaer', 'file' => 'src/Modules/ThemeBuilder/Documents/HeaderDocument.php']];\n");
    file_put_contents($migration, "<?php\nnamespace Elemacy\\Core\\Migrations;\ndefined('ABSPATH') || exit;\nconst ZZ_RENAMED_FROM = 'elemacy_hedaer';\n");

    $output = [];
    $exit_code = 0;

    try {
        exec('ELEMACY_PERSISTED_IDENTIFIERS=' . escapeshellarg($list) . ' php ' . escapeshellarg($script) . ' 2>&1', $output, $exit_code);
    } finally {
        unlink($list);
        unlink($migration);
    }

    return 0 === $exit_code;
});

check('every persisted identifier is declared by its own file today, not only kept alive by a migration', static function () {
    foreach (require dirname(__DIR__) . '/architecture/persisted-identifiers.php' as $identifier) {
        $pattern = $identifier['declaration'] ?? '/' . preg_quote("'" . $identifier['id'] . "'", '/') . '/';

        if (1 !== preg_match($pattern, elemacy_code_without_comments(ELEMACY_PATH . $identifier['file']))) {
            return false;
        }
    }

    return true;
});

check('tests/architecture/check.php fails on an Elementor API reference outside src/Elementor and src/Modules', static function () {
    $fixture = dirname(__DIR__, 2) . '/src/Loop/ZZElementorBoundaryFixture.php';
    $script = dirname(__DIR__) . '/architecture/check.php';

    file_put_contents($fixture, "<?php\nnamespace Elemacy\\Loop;\ndefined('ABSPATH') || exit;\nuse Elementor\\Plugin;\n");

    $output = [];
    $exit_code = 0;

    try {
        exec('php ' . escapeshellarg($script) . ' 2>&1', $output, $exit_code);
    } finally {
        unlink($fixture);
    }

    return 1 === $exit_code && false !== strpos(implode("\n", $output), 'ZZElementorBoundaryFixture.php');
});

/* ── Support\ConfigList ─────────────────────────────────────────────── */

check('ConfigList::instances() instantiates class-strings, passes instances through and drops entries of another type, in order', static function () {
    $ready = new \ArrayObject(['ready']);
    $file = tempnam(sys_get_temp_dir(), 'elemacy-config-list-');

    file_put_contents($file, '<?php return [\ArrayObject::class, $GLOBALS["__config_list_ready"], \SplStack::class, 42];');
    $GLOBALS['__config_list_ready'] = $ready;

    try {
        $instances = \Elemacy\Support\ConfigList::instances($file, \ArrayObject::class);
    } finally {
        unlink($file);
        unset($GLOBALS['__config_list_ready']);
    }

    return 2 === count($instances)
        && $instances[0] instanceof \ArrayObject
        && 0 === count($instances[0])
        && $ready === $instances[1];
});

require __DIR__ . '/transport-policy.php';
require __DIR__ . '/query-pickers.php';
require __DIR__ . '/archive-title.php';
require __DIR__ . '/nav-menu-breakpoints.php';

conclude();
