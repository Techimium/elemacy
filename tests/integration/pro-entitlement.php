<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Conditions\ConditionEvaluator;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\ConditionRepository;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\TemplateLibrary\ResolverCache;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * Pro display conditions are entitled by the same active-license check as
 * every other Pro capability, and losing entitlement suspends the templates
 * that depend on them rather than widening their targeting.
 *
 * This environment installs Pro without a license, which is exactly the state
 * under test. The licensed direction is exercised against an isolated
 * ConditionManager/ConditionEvaluator pair rather than the process-wide
 * singletons: a real condition superseding a mock is deliberately one-way
 * (RegistryIdentifierGuard refuses the reverse), so registering the real Pro
 * conditions into the shared registry could not be undone for later scenarios.
 */
final class ProEntitlementTest extends IntegrationTestCase
{
    /** Identifiers Free ships a locked mock for and Pro implements for real. */
    private const PRO_CONDITIONS = [
        'general/front_page',
        'general/blog',
        'general/not_found',
        'general/search_results',
        'archive/all',
        'archive/author',
        'archive/date',
        'singular/specific_post',
        'singular/by_author',
        'singular/child_of',
    ];

    private RequestSimulator $request;
    private ConditionRepository $conditions;
    private array $posts = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\ElemacyPro\Core\ElemacyPro::class)) {
            self::markTestSkipped('Elemacy Pro is not active in this environment; provision with --with-pro.');
        }

        $this->request = new RequestSimulator();
        $this->conditions = new ConditionRepository();

        $id = wp_insert_post([
            'post_title' => 'Pro entitlement post',
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_content' => 'Pro entitlement fixture.',
        ], true);

        self::assertIsInt($id, is_wp_error($id) ? $id->get_error_message() : '');
        $this->posts[] = $id;
    }

    protected function tearDown(): void
    {
        try {
            $this->request->restore();
        } finally {
            foreach ($this->posts as $id) {
                wp_delete_post($id, true);
            }

            $this->posts = [];
            (new ResolverCache())->invalidate();
            TemplateResolver::reset();
            parent::tearDown();
        }
    }

    public function testTheLicenseIsInactiveInThisEnvironment(): void
    {
        self::assertFalse(
            \ElemacyPro\Core\License\LicenseManager::instance()->is_active(),
            'This scenario describes the unlicensed state; a license here would invalidate every assertion below.'
        );
    }

    public function testNoProConditionIsFunctionalWithoutAnActiveLicense(): void
    {
        $manager = ConditionManager::instance();

        foreach (self::PRO_CONDITIONS as $identifier) {
            $condition = $manager->get($identifier);

            self::assertNotNull($condition, $identifier . ' must still be listed, so the UI can show it locked.');
            self::assertTrue($condition->is_mock(), $identifier . ' must resolve to Free\'s locked mock without a license.');
            self::assertTrue(
                $manager->get_availability($identifier)->is_unavailable(),
                $identifier . ' must report as unavailable without a license.'
            );
        }
    }

    public function testATemplateWhoseOnlyInclusionIsAProConditionIsSuspendedRatherThanShownEverywhere(): void
    {
        $header = $this->documents->create('Pro gated header', 'header', 'publish');

        $this->conditions->save($header, [
            ['type' => 'singular/specific_post', 'value' => (string) $this->posts[0], 'operator' => 'include'],
        ]);

        (new ResolverCache())->invalidate();
        TemplateResolver::reset();

        $this->request->visit(get_permalink($this->posts[0]));
        TemplateResolver::reset();

        self::assertNull(
            TemplateResolver::instance()->resolve('header'),
            'An unavailable Pro condition must suspend the template, not match it.'
        );

        $this->request->visit(home_url('/'));
        TemplateResolver::reset();

        self::assertNull(
            TemplateResolver::instance()->resolve('header'),
            'A suspended template must not fall back to matching every page.'
        );
    }

    public function testASuspendedTemplateKeepsItsSavedConditionForRepair(): void
    {
        $header = $this->documents->create('Pro gated header keeps config', 'header', 'publish');

        $saved = [
            ['type' => 'singular/specific_post', 'value' => (string) $this->posts[0], 'operator' => 'include'],
        ];

        $this->conditions->save($header, $saved);

        (new ResolverCache())->invalidate();
        TemplateResolver::reset();
        $this->request->visit(get_permalink($this->posts[0]));
        TemplateResolver::reset();
        TemplateResolver::instance()->resolve('header');

        $stored = $this->conditions->get($header);

        self::assertCount(1, $stored);
        self::assertSame('singular/specific_post', $stored[0]->type);
        self::assertSame((string) $this->posts[0], $stored[0]->value);
        self::assertSame('include', $stored[0]->operator);
    }

    public function testRestoringEntitlementMakesTheSameSavedConditionWorkAgain(): void
    {
        $rule = ConditionRuleDTO::from_array([
            'type' => 'singular/specific_post',
            'value' => (string) $this->posts[0],
            'operator' => 'include',
        ]);

        $this->request->visit(get_permalink($this->posts[0]));

        // Unlicensed: Free's mock holds the identifier and suspends the rule.
        $unlicensed = new ConditionManager();
        $unlicensed->register(new \Elemacy\Conditions\Mock\SpecificPost());

        self::assertFalse(
            (new ConditionEvaluator($unlicensed))->evaluate([$rule]),
            'The saved rule must not match while its implementation is unavailable.'
        );

        // Licensed: Pro's real condition supersedes the mock under the same
        // identifier, and the very same saved rule now evaluates for real.
        $licensed = new ConditionManager();
        $licensed->register(new \Elemacy\Conditions\Mock\SpecificPost());
        $licensed->register(new \ElemacyPro\Extensions\Conditions\Singular\SpecificPost());

        $real = $licensed->get('singular/specific_post');

        self::assertFalse($real->is_mock(), 'The real Pro condition must supersede the mock under the same identifier.');
        self::assertTrue($licensed->get_availability('singular/specific_post')->is_available());
        self::assertTrue(
            (new ConditionEvaluator($licensed))->evaluate([$rule]),
            'With entitlement restored the same saved rule must match, with no re-authoring.'
        );
    }

    public function testAMockCannotDisplaceTheRealProConditionOnceItHolds(): void
    {
        $registry = new ConditionManager();
        $registry->register(new \Elemacy\Conditions\Mock\SpecificPost());
        $registry->register(new \ElemacyPro\Extensions\Conditions\Singular\SpecificPost());

        try {
            $registry->register(new \Elemacy\Conditions\Mock\SpecificPost());
            self::fail('A mock must not be able to downgrade a live Pro condition.');
        } catch (\Throwable $error) {
            self::assertFalse($registry->get('singular/specific_post')->is_mock());
        }
    }

    public function testEveryProConditionDeclaresTheSameSpecificityAsTheMockItReplaces(): void
    {
        $empty = ConditionRuleDTO::from_array(['value' => '']);
        $value = ConditionRuleDTO::from_array(['value' => (string) $this->posts[0]]);

        $pairs = [
            [\Elemacy\Conditions\Mock\SpecificPost::class, \ElemacyPro\Extensions\Conditions\Singular\SpecificPost::class],
            [\Elemacy\Conditions\Mock\FrontPage::class, \ElemacyPro\Extensions\Conditions\General\FrontPage::class],
            [\Elemacy\Conditions\Mock\AllArchives::class, \ElemacyPro\Extensions\Conditions\Archive\AllArchives::class],
            [\Elemacy\Conditions\Mock\ByAuthor::class, \ElemacyPro\Extensions\Conditions\Singular\ByAuthor::class],
        ];

        foreach ($pairs as [$mock, $real]) {
            foreach ([$empty, $value] as $rule) {
                self::assertSame(
                    (new $mock())->get_specificity($rule),
                    (new $real())->get_specificity($rule),
                    $real . ' must declare the same specificity as the mock it replaces.'
                );
            }
        }
    }
}
