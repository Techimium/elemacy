<?php

namespace Elemacy\Tests\Integration;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Elemacy\Modules\Widgets\Widgets\NavMenu;
use Elemacy\Modules\Widgets\Widgets\NavMenu\BreakpointStyles;
use Elemacy\Tests\Integration\Fixtures\NavMenuControlCatalog;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';
require_once __DIR__ . '/fixtures/NavMenuControlCatalog.php';

/**
 * The Nav Menu widget (openspec widgets/nav-menu) rendering real WordPress menus
 * through real Elementor: markup, states, isolation from other menus, breakpoint
 * CSS, kit import/export, and control parity with the Elementor Pro reference.
 * Browser behaviour is tests/e2e/run-nav-menu.mjs.
 */
final class NavMenuTest extends IntegrationTestCase
{
    private const MENU = 'elemacy-nm-fixture';

    private RequestSimulator $request;
    private int $menu_id;
    private int $page_id;

    /** @var int[] Menu item IDs of the fixture menu. */
    private array $items = [];

    /** @var int[] Extra menus created by a test. */
    private array $menus = [];

    /** @var array<int, array{0: string, 1: callable, 2: int}> Filters added by a test (hook, callback, priority). */
    private array $filters = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new RequestSimulator();

        $this->page_id = (int) wp_insert_post([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'post_title'  => 'Nav Menu Fixture Page',
        ]);

        // Home, Services > (Design > Branding), About (#about), the fixture page.
        $this->menu_id = (int) wp_create_nav_menu(self::MENU);
        $this->item('Home', home_url('/'));
        $services = $this->item('Services', home_url('/services/'));
        $design = $this->item('Design', home_url('/services/design/'), $services);
        $this->item('Branding', home_url('/services/design/branding/'), $design);
        $this->item('About', home_url('/#about'));
        $this->items[] = (int) wp_update_nav_menu_item($this->menu_id, 0, [
            'menu-item-object-id' => $this->page_id,
            'menu-item-object'    => 'page',
            'menu-item-type'      => 'post_type',
            'menu-item-status'    => 'publish',
        ]);
    }

    protected function tearDown(): void
    {
        try {
            $this->request->restore();
            foreach ($this->filters as [$hook, $callback, $priority]) {
                remove_filter($hook, $callback, $priority);
            }
            foreach (array_merge([$this->menu_id], $this->menus) as $menu) {
                wp_delete_nav_menu($menu);
            }
            wp_delete_post($this->page_id, true);
            $this->filters = $this->menus = $this->items = [];
        } finally {
            parent::tearDown();
        }
    }

    /* ── Structure per layout ─────────────────────────────────────────── */

    public function testTheDefaultWidgetRendersTheMainMenuTheToggleAndAClosedDropdown(): void
    {
        $xpath = $this->render();

        $main = $this->one($xpath, '//nav[contains(@class, "elemacy-nav-menu__main")]');
        self::assertSame('Menu', $main->getAttribute('aria-label'));
        foreach (['elemacy-nav-menu__container', 'elemacy-nav-menu__main--horizontal', 'elemacy-nav-menu__main--pointer-underline', 'elemacy-nav-menu__main--animation-fade'] as $class) {
            self::assertTrue($this->has_class($main, $class), "Main menu has $class.");
        }

        $toggle = $this->one($xpath, '//div[contains(@class, "elemacy-nav-menu__toggle")]');
        self::assertSame('button', $toggle->getAttribute('role'));
        self::assertSame('0', $toggle->getAttribute('tabindex'));
        self::assertSame('Menu Toggle', $toggle->getAttribute('aria-label'));
        self::assertSame('false', $toggle->getAttribute('aria-expanded'));

        $dropdown = $this->one($xpath, '//nav[contains(@class, "elemacy-nav-menu__dropdown")]');
        self::assertSame('true', $dropdown->getAttribute('aria-hidden'));
        self::assertTrue($this->has_class($dropdown, 'elemacy-nav-menu__container'));
        self::assertSame($toggle, $this->previous_element($dropdown), 'The dropdown directly follows the toggle, which the open/closed CSS relies on.');

        foreach ($xpath->query('.//a', $dropdown) as $link) {
            self::assertSame('-1', $link->getAttribute('tabindex'), 'Closed dropdown links are out of the tab order.');
        }
        foreach ($xpath->query('.//a', $main) as $link) {
            self::assertFalse($link->hasAttribute('tabindex'), 'Main menu links keep their natural tab order.');
        }
    }

    public function testTheDropdownLayoutRendersOnlyTheToggleAndTheDropdown(): void
    {
        $xpath = $this->render(['layout' => NavMenu::LAYOUT_DROPDOWN]);

        self::assertSame(0, $xpath->query('//nav[contains(@class, "elemacy-nav-menu__main")]')->length);
        $this->one($xpath, '//div[contains(@class, "elemacy-nav-menu__toggle")]');
        $this->one($xpath, '//nav[contains(@class, "elemacy-nav-menu__dropdown")]');
    }

    public function testBreakpointNoneRendersOnlyTheMainMenu(): void
    {
        $xpath = $this->render(['dropdown' => NavMenu::BREAKPOINT_NONE]);

        $this->one($xpath, '//nav[contains(@class, "elemacy-nav-menu__main")]');
        self::assertSame(0, $xpath->query('//*[contains(@class, "elemacy-nav-menu__toggle")]')->length);
        self::assertSame(0, $xpath->query('//nav[contains(@class, "elemacy-nav-menu__dropdown")]')->length);
    }

    public function testWithoutAToggleTheDropdownStaysReadableAndReachable(): void
    {
        $xpath = $this->render(['toggle' => '']);

        self::assertSame(0, $xpath->query('//*[contains(@class, "elemacy-nav-menu__toggle")]')->length);
        $dropdown = $this->one($xpath, '//nav[contains(@class, "elemacy-nav-menu__dropdown")]');
        self::assertFalse($dropdown->hasAttribute('aria-hidden'));
        self::assertSame(0, $xpath->query('.//a[@tabindex]', $dropdown)->length);
    }

    public function testTheVerticalLayoutAndEachPointerUseTheirOwnAnimationControl(): void
    {
        $cases = [
            ['underline', 'animation_line', 'drop-in'],
            ['framed', 'animation_framed', 'corners'],
            ['background', 'animation_background', 'shutter-in-vertical'],
            ['text', 'animation_text', 'skew'],
        ];

        foreach ($cases as [$pointer, $control, $animation]) {
            $main = $this->one($this->render(['layout' => NavMenu::LAYOUT_VERTICAL, 'pointer' => $pointer, $control => $animation]), '//nav[contains(@class, "elemacy-nav-menu__main")]');

            self::assertTrue($this->has_class($main, 'elemacy-nav-menu__main--vertical'));
            self::assertTrue($this->has_class($main, 'elemacy-nav-menu__main--pointer-' . $pointer));
            self::assertTrue($this->has_class($main, 'elemacy-nav-menu__main--animation-' . $animation), "$pointer uses $control.");
        }

        $none = $this->one($this->render(['pointer' => 'none']), '//nav[contains(@class, "elemacy-nav-menu__main")]');
        self::assertStringNotContainsString('--animation-', $none->getAttribute('class'), 'Pointer None has no animation.');
    }

    /* ── Links, submenus and the indicator ─────────────────────────────── */

    public function testLinksCarryTheirLevelAndStateClasses(): void
    {
        $this->request->visit(get_permalink($this->page_id));
        $main = $this->one($this->render(), '//nav[contains(@class, "elemacy-nav-menu__main")]');
        $xpath = new DOMXPath($main->ownerDocument);

        $home = $this->link($xpath, $main, 'Home');
        self::assertTrue($this->has_class($home, 'elemacy-nav-menu__link'));
        self::assertTrue($this->has_class($home, 'elemacy-nav-menu__item'));

        $design = $this->link($xpath, $main, 'Design');
        self::assertTrue($this->has_class($design, 'elemacy-nav-menu__sub-item'));
        self::assertFalse($this->has_class($design, 'elemacy-nav-menu__item'));

        $about = $this->link($xpath, $main, 'About');
        self::assertTrue($this->has_class($about, 'elemacy-nav-menu__link--anchor'));
        self::assertFalse($this->has_class($about, 'elemacy-nav-menu__link--active'));

        $page = $this->link($xpath, $main, 'Nav Menu Fixture Page');
        self::assertTrue($this->has_class($page, 'elemacy-nav-menu__link--active'), 'The current page is active.');
        self::assertSame(1, $xpath->query('.//a[contains(@class, "elemacy-nav-menu__link--active")]', $main)->length);
    }

    public function testAnAnchorLinkIsNeverMarkedCurrentEvenOnItsOwnPage(): void
    {
        $this->request->visit(home_url('/'));
        $main = $this->one($this->render(), '//nav[contains(@class, "elemacy-nav-menu__main")]');

        self::assertFalse($this->has_class($this->link(new DOMXPath($main->ownerDocument), $main, 'About'), 'elemacy-nav-menu__link--active'));
    }

    public function testSubmenusAreStyledAsDropdownsAndParentsGetTheIndicator(): void
    {
        $xpath = $this->render();
        $main = $this->one($xpath, '//nav[contains(@class, "elemacy-nav-menu__main")]');

        $submenus = $xpath->query('.//ul[contains(@class, "sub-menu")]', $main);
        self::assertSame(2, $submenus->length);
        foreach ($submenus as $submenu) {
            self::assertTrue($this->has_class($submenu, 'elemacy-nav-menu__submenu'));
            self::assertTrue($this->has_class($submenu, 'elemacy-nav-menu__dropdown'));
        }

        foreach (['Services' => 1, 'Design' => 1, 'Branding' => 0, 'Home' => 0] as $title => $count) {
            $indicators = $xpath->query('.//span[contains(@class, "elemacy-nav-menu__indicator")]', $this->link($xpath, $main, $title));
            self::assertSame($count, $indicators->length, "$title indicator count.");
        }

        // A font icon (`fas fa-caret-down`) or, with inline SVG icons, `e-fas-caret-down`.
        self::assertMatchesRegularExpression('/fas?-caret-down/', $main->ownerDocument->saveHTML($main), 'The default indicator is caret-down.');
    }

    public function testClearingTheIndicatorRemovesItButKeepsTheSubmenus(): void
    {
        $xpath = $this->render(['submenu_icon' => ['value' => '', 'library' => '']]);

        self::assertSame(0, $xpath->query('//span[contains(@class, "elemacy-nav-menu__indicator")]')->length);
        self::assertSame(4, $xpath->query('//ul[contains(@class, "sub-menu")]')->length, 'Both trees keep both submenus.');
    }

    public function testMenuItemIdsAreDroppedAndMenuIdsAreUniqueAcrossWidgets(): void
    {
        $html = $this->html() . $this->html() . $this->html([], 'nm00002');
        $xpath = $this->xpath($html);

        self::assertSame(0, $xpath->query('//li[@id]')->length, 'The duplicated trees print no item IDs.');

        $ids = [];
        foreach ($xpath->query('//ul[contains(@class, "elemacy-nav-menu__list")]') as $list) {
            $ids[] = $list->getAttribute('id');
        }
        self::assertCount(6, $ids);
        self::assertSame($ids, array_unique($ids), 'Every rendered tree has its own ID, even for the same widget twice.');
    }

    /* ── Nothing to render ─────────────────────────────────────────────── */

    public function testAMissingDeletedOrEmptyMenuRendersNothing(): void
    {
        $empty = (int) wp_create_nav_menu('elemacy-nm-empty');
        $this->menus[] = $empty;

        self::assertSame('', $this->widget_content(['menu' => 'elemacy-nm-missing']), 'A deleted menu does not fall back to another menu.');
        self::assertSame('', $this->widget_content(['menu' => '']));
        self::assertSame('', $this->widget_content(['menu' => 'elemacy-nm-empty']));
    }

    /* ── Isolation ─────────────────────────────────────────────────────── */

    /**
     * WordPress prints each menu item's ID only in the first menu that renders
     * the item. The widget prints none, so it must not use them up: a theme
     * menu rendered after it still gets every ID, and none of the widget's classes.
     */
    public function testAThemeMenuRenderedAfterTheWidgetKeepsItsOwnMarkup(): void
    {
        $this->html();
        $theme = $this->xpath((string) wp_nav_menu(['menu' => self::MENU, 'echo' => false, 'fallback_cb' => '__return_empty_string']));

        foreach ($this->items as $item) {
            self::assertSame(1, $theme->query('//li[@id="menu-item-' . $item . '"]')->length, "Theme menu keeps the ID of item $item.");
        }
        self::assertSame(0, $theme->query('//*[contains(@class, "elemacy-nav-menu")]')->length, 'No widget class leaks into another menu.');
        self::assertSame(0, $theme->query('//a[@tabindex]')->length);
        self::assertSame(0, $theme->query('//span[contains(@class, "indicator")]')->length);
    }

    public function testAMenuRenderedInsideTheWidgetsRenderIsLeftAlone(): void
    {
        $nested = '';
        $this->filter('nav_menu_item_title', function (string $title, $item, $args) use (&$nested): string {
            if ($nested === '' && isset($args->elemacy_nav_menu)) {
                $nested = ' '; // Guard against recursion before rendering.
                $nested = (string) wp_nav_menu(['menu' => self::MENU, 'echo' => false, 'menu_id' => 'nested-menu']);
            }

            return $title;
        }, 20, 3);

        $this->html();

        self::assertStringContainsString('nested-menu', $nested, 'Sanity: a menu rendered from inside the widget\'s own render.');
        self::assertStringNotContainsString('elemacy-nav-menu', $nested);
    }

    public function testOtherPluginsMenuFiltersStillApplyToTheWidget(): void
    {
        $this->filter('nav_menu_link_attributes', static function (array $atts): array {
            $atts['data-third-party'] = 'yes';
            $atts['class'] = trim(($atts['class'] ?? '') . ' third-party');

            return $atts;
        }, 5);

        $xpath = $this->render();
        $home = $this->link($xpath, $this->one($xpath, '//nav[contains(@class, "elemacy-nav-menu__main")]'), 'Home');

        self::assertSame('yes', $home->getAttribute('data-third-party'));
        self::assertTrue($this->has_class($home, 'third-party'));
        self::assertTrue($this->has_class($home, 'elemacy-nav-menu__item'));
    }

    /* ── Breakpoints, markup mode and import/export ───────────────────── */

    public function testBreakpointRulesAndOptionLabelsFollowCustomBreakpoints(): void
    {
        $kit = Plugin::$instance->kits_manager->get_active_kit_for_frontend();
        $previous = $kit->get_settings('viewport_tablet');

        try {
            $kit->set_settings('viewport_tablet', 900);
            Plugin::$instance->breakpoints->refresh();

            $css = BreakpointStyles::css();
            self::assertStringContainsString('@media (max-width:900px){.elemacy-nav-menu--breakpoint-tablet .elemacy-nav-menu__main{display:none}}', $css);
            self::assertStringContainsString('@media (min-width:901px){.elemacy-nav-menu--breakpoint-tablet .elemacy-nav-menu__toggle', $css);

            $options = NavMenuControlCatalog::controls(Plugin::$instance->widgets_manager->get_widget_types('elemacy_nav_menu'))['dropdown']['options'];
            self::assertStringContainsString('(> 900px)', $options['tablet']);
            self::assertSame('none', array_key_last($options));
        } finally {
            $kit->set_settings('viewport_tablet', $previous);
            Plugin::$instance->breakpoints->refresh();
        }
    }

    public function testTheInnerWrapperFollowsElementorsOptimizedMarkup(): void
    {
        $widget = Plugin::$instance->widgets_manager->get_widget_types('elemacy_nav_menu');
        $optimized = Plugin::$instance->experiments->is_feature_active('e_optimized_markup');

        self::assertSame(!$optimized, $widget->has_widget_inner_wrapper());
    }

    public function testKitExportAndImportFollowTheImportedMenu(): void
    {
        $element = ['settings' => ['menu' => self::MENU]];
        $exported = (new NavMenu(['id' => 'nm00003', 'settings' => $element['settings']], []))->on_export($element);
        self::assertSame($this->menu_id, $exported['settings']['menu_id']);

        $imported = (int) wp_create_nav_menu(self::MENU . '-duplicate');
        $this->menus[] = $imported;

        $config = NavMenu::on_import_update_dynamic_content($exported, ['term_ids' => [$this->menu_id => $imported]]);
        self::assertSame(self::MENU . '-duplicate', $config['settings']['menu']);
        self::assertArrayNotHasKey('menu_id', $config['settings']);

        $gone = (new NavMenu(['id' => 'nm00004', 'settings' => []], []))->on_export(['settings' => ['menu' => 'elemacy-nm-missing']]);
        self::assertArrayNotHasKey('menu', $gone['settings'], 'An export drops a menu that no longer exists.');
    }

    /* ── Parity with the Elementor Pro reference ──────────────────────── */

    /**
     * Every control matches the reference snapshot (tools/dump-nav-menu-reference.php):
     * same order, types, labels, defaults, options, units, ranges, conditions,
     * tabs and selector counts. Site-dependent values are compared against this
     * site instead: the menu list, and the active breakpoints.
     */
    public function testControlsMatchTheElementorProReference(): void
    {
        $reference = json_decode((string) file_get_contents(__DIR__ . '/fixtures/nav-menu-reference-controls.json'), true);
        $ours = NavMenuControlCatalog::describe(Plugin::$instance->widgets_manager->get_widget_types('elemacy_nav_menu'));

        self::assertSame('4.2.2', $reference['reference']['elementor_pro']);
        self::assertSame(array_column($reference['controls'], 'name'), array_column($ours, 'name'), 'Same controls in the same order.');

        $site_breakpoints = array_merge(array_keys(BreakpointStyles::breakpoints()), [NavMenu::BREAKPOINT_NONE]);

        foreach ($reference['controls'] as $index => $expected) {
            $actual = $ours[$index];

            if ($expected['name'] === 'dropdown') {
                self::assertSame($site_breakpoints, $actual['options'], 'Breakpoint options are this site\'s eligible breakpoints, then None.');
                unset($expected['options'], $actual['options']);
            }

            self::assertSame($expected, $actual, 'Control ' . $expected['name']);
        }
    }

    /* ── Helpers ──────────────────────────────────────────────────────── */

    private function item(string $title, string $url, int $parent = 0): int
    {
        return $this->items[] = (int) wp_update_nav_menu_item($this->menu_id, 0, [
            'menu-item-title'     => $title,
            'menu-item-url'       => $url,
            'menu-item-type'      => 'custom',
            'menu-item-status'    => 'publish',
            'menu-item-parent-id' => $parent,
        ]);
    }

    private function filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        add_filter($hook, $callback, $priority, $accepted_args);
        $this->filters[] = [$hook, $callback, $priority];
    }

    /** The widget's full element markup. */
    private function html(array $settings = [], string $id = 'nm00001'): string
    {
        $widget = Plugin::$instance->elements_manager->create_element_instance([
            'id'         => $id,
            'elType'     => 'widget',
            'widgetType' => 'elemacy_nav_menu',
            'settings'   => $settings + ['menu' => self::MENU],
        ]);

        ob_start();
        $widget->print_element();

        return (string) ob_get_clean();
    }

    /** What the widget itself prints, without Elementor's element wrapper. */
    private function widget_content(array $settings): string
    {
        $xpath = $this->xpath($this->html($settings));
        $element = $xpath->query('//*[contains(@class, "elementor-widget-elemacy_nav_menu")]')->item(0);

        if (!$element instanceof DOMElement) {
            return '';
        }

        $content = '';

        foreach ($element->childNodes as $child) {
            $content .= $element->ownerDocument->saveHTML($child);
        }

        return trim(preg_replace('/<div class="elementor-widget-container">\s*<\/div>/', '', $content));
    }

    private function render(array $settings = []): DOMXPath
    {
        return $this->xpath($this->html($settings));
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function one(DOMXPath $xpath, string $query, ?DOMElement $context = null): DOMElement
    {
        $nodes = $xpath->query($query, $context);
        self::assertSame(1, $nodes->length, "Exactly one $query");

        return $nodes->item(0);
    }

    private function link(DOMXPath $xpath, DOMElement $tree, string $title): DOMElement
    {
        return $this->one($xpath, './/a[starts-with(normalize-space(.), "' . $title . '")]', $tree);
    }

    private function has_class(DOMElement $element, string $class): bool
    {
        return in_array($class, preg_split('/\s+/', $element->getAttribute('class')), true);
    }

    private function previous_element(DOMElement $element): ?DOMElement
    {
        for ($node = $element->previousSibling; $node; $node = $node->previousSibling) {
            if ($node instanceof DOMElement) {
                return $node;
            }
        }

        return null;
    }
}
