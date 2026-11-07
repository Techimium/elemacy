<?php

namespace Elemacy\Tests\Integration;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Widgets are an always-on platform; Loop Builder and Form Builder are
 * switchable modules that own their widgets, endpoints, library type and form
 * contracts. A module switch and a widget switch combine, and neither rewrites
 * the other. Each scenario boots WordPress in its own process
 * (fixtures/module-features-request.php).
 */
final class ModuleFeatureAvailabilityTest extends IntegrationTestCase
{
    private const ALL = ['theme-builder', 'dynamic-tags', 'loop-builder', 'form-builder', 'custom-css', 'popups'];

    public function testWithEveryModuleOnTheirWidgetsEndpointsAndContractsAreAvailable(): void
    {
        $result = $this->request(self::ALL);

        foreach (['elemacy-site-logo', 'elemacy-nav-menu', 'elemacy-search', 'elemacy-loop-grid', 'elemacy-loop-carousel', 'elemacy-form'] as $widget) {
            self::assertContains($widget, $result['registered'], "$widget must be offered in the editor.");
            self::assertTrue($result['listed'][$widget] ?? null, "$widget must be listed and switched on.");
        }
        self::assertTrue($result['loop_type']);
        self::assertTrue($result['loop_pagination']);
        self::assertTrue($result['form_endpoint']);
        self::assertSame('resolved', $result['form_contract']);
        self::assertSame(['field_types' => 1, 'actions' => 1], $result['fired'], 'The first lookup loads each form registry and fires its hook once.');
    }

    public function testEveryFeatureModuleOffLeavesTheGeneralWidgetsWorking(): void
    {
        $result = $this->request([]);

        foreach (['elemacy-site-logo', 'elemacy-nav-menu', 'elemacy-search'] as $widget) {
            self::assertContains($widget, $result['registered'], "The platform widget $widget does not depend on any module.");
        }
        foreach (['elemacy-loop-grid', 'elemacy-loop-carousel', 'elemacy-form'] as $widget) {
            self::assertNotContains($widget, $result['registered']);
            self::assertArrayNotHasKey($widget, $result['listed'], "$widget is not listed while its module is off.");
        }
        self::assertSame('resolved', $result['shared_contract'], 'Shared-layer contracts resolve whichever modules are active.');
    }

    public function testLoopBuilderOffRemovesOnlyItsOwnFeatures(): void
    {
        $result = $this->request(array_values(array_diff(self::ALL, ['loop-builder'])));

        self::assertNotContains('elemacy-loop-grid', $result['registered']);
        self::assertNotContains('elemacy-loop-carousel', $result['registered']);
        self::assertFalse($result['loop_type'], 'The Loop Item library type belongs to Loop Builder.');
        self::assertFalse($result['loop_pagination'], 'Loop pagination requests are not handled while Loop Builder is off.');
        self::assertContains('elemacy-form', $result['registered']);
        self::assertContains('elemacy-nav-menu', $result['registered']);
        self::assertTrue($result['form_endpoint']);
    }

    public function testFormBuilderOffBuildsNoRegistryFiresNoHookAndBindsNoContract(): void
    {
        $result = $this->request(array_values(array_diff(self::ALL, ['form-builder'])));

        self::assertNotContains('elemacy-form', $result['registered']);
        self::assertArrayNotHasKey('elemacy-form', $result['listed']);
        self::assertFalse($result['form_endpoint'], 'The form submission endpoint is not registered while Form Builder is off.');
        self::assertSame(['field_types' => 0, 'actions' => 0], $result['fired'], 'No form registration hook fires while Form Builder is off.');
        self::assertStringStartsWith('failed: ', $result['form_contract'], 'A disabled module\'s contract does not resolve.');
        self::assertStringContainsString('FormActionRegistrarInterface', $result['form_contract'], 'The diagnostic names the contract.');
        self::assertSame('resolved', $result['shared_contract']);
        self::assertContains('elemacy-loop-grid', $result['registered']);
    }

    public function testAWidgetSwitchIsIndependentOfItsModuleAndOfOtherWidgets(): void
    {
        $off = ['elemacy-search' => false, 'elemacy-loop-carousel' => false];

        $on = $this->request(self::ALL, $off);
        self::assertNotContains('elemacy-search', $on['registered']);
        self::assertFalse($on['listed']['elemacy-search']);
        self::assertNotContains('elemacy-loop-carousel', $on['registered']);
        self::assertFalse($on['listed']['elemacy-loop-carousel']);
        self::assertContains('elemacy-loop-grid', $on['registered'], 'Switching one loop widget off leaves its sibling on.');

        // Loop Builder off and back on: the stored switch is untouched, so
        // Loop Grid returns and Loop Carousel stays off.
        $this->request(array_values(array_diff(self::ALL, ['loop-builder'])), $off);
        $back = $this->request(self::ALL, $off);
        self::assertContains('elemacy-loop-grid', $back['registered']);
        self::assertNotContains('elemacy-loop-carousel', $back['registered']);
        self::assertFalse($back['listed']['elemacy-loop-carousel']);
    }

    /**
     * @param string[]            $modules The active modules; every other one is switched off.
     * @param array<string, bool> $widgets
     * @return array<string, mixed>
     */
    private function request(array $modules, array $widgets = []): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/module-features-request.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        // An explicit choice per module: an empty preference list means "follow the defaults".
        $choices = [];
        foreach (self::ALL as $module) {
            $choices[$module] = in_array($module, $modules, true);
        }
        fwrite($pipes[0], wp_json_encode(['modules' => $choices, 'widgets' => (object) $widgets]));
        fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);

        $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $result['listed'] = (array) $result['listed'];

        return $result;
    }
}
