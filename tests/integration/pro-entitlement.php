<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Conditions\ConditionEvaluator;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\ConditionRepository;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Modules\Popups\Services\RuleManager;
use Elemacy\Modules\Popups\Services\TriggerManager;
use Elemacy\TemplateLibrary\ResolverCache;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * An active Pro supplies its real conditions, rules, triggers and loop sources
 * whether or not it is licensed — the license covers updates and support only.
 * Without Pro, Free's mocks hold the same identifiers and suspend dependent
 * templates rather than widening their targeting.
 *
 * This environment installs Pro without a license. The "Pro inactive"
 * direction is exercised against an isolated ConditionManager/
 * ConditionEvaluator pair rather than the process-wide singletons, which
 * already hold Pro's real conditions.
 */
final class ProEntitlementTest extends IntegrationTestCase
{
    /** Identifiers Free ships a locked mock for and Pro implements for real. */
    private const PRO_CONDITIONS = [
        'general/front_page',
        'general/blog',
        'general/not_found',
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
            \ElemacyPro\Core\License\License::client()->is_active(),
            'This scenario describes an unlicensed install; a license here would make the assertions below vacuous.'
        );
    }

    public function testEveryProConditionIsRealAndAvailableWithoutALicense(): void
    {
        $manager = ConditionManager::instance();

        foreach (self::PRO_CONDITIONS as $identifier) {
            $condition = $manager->get($identifier);

            self::assertNotNull($condition, $identifier . ' must be registered.');
            self::assertFalse($condition->is_mock(), $identifier . ' must be Pro\'s real implementation without a license.');
            self::assertTrue($manager->get_availability($identifier)->is_available(), $identifier . ' must be available.');
        }
    }

    public function testProRulesTriggersAndLoopSourceRegisterWithoutALicense(): void
    {
        self::assertFalse(RuleManager::instance()->get('devices')->is_mock());
        self::assertFalse(TriggerManager::instance()->get('scroll')->is_mock());

        if (!function_exists('get_field')) {
            self::markTestIncomplete('ACF is not active, so Pro registers no ACF Repeater source to check.');
        }

        self::assertNotNull(LoopDataSourceRegistry::instance()->get('acf_repeater'));
    }

    public function testATemplateTargetingAProConditionMatchesWithoutALicense(): void
    {
        $header = $this->documents->create('Pro condition header', 'header', 'publish');

        $this->conditions->save($header, [
            ['type' => 'singular/specific_post', 'value' => (string) $this->posts[0], 'operator' => 'include'],
        ]);

        (new ResolverCache())->invalidate();
        TemplateResolver::reset();

        $this->request->visit(get_permalink($this->posts[0]));
        TemplateResolver::reset();

        self::assertSame($header, TemplateResolver::instance()->resolve('header'), 'The Pro condition must match the page it targets.');

        $this->request->visit(home_url('/'));
        TemplateResolver::reset();

        self::assertNull(TemplateResolver::instance()->resolve('header'), 'It must match only what it targets.');
    }

    public function testATemplateOrPopupWithSpecificPostAndNoPostIsRejected(): void
    {
        $valueless = [['type' => 'singular/specific_post', 'value' => '', 'operator' => 'include']];
        $templates = \Elemacy\Core\Container::instance()->make(\Elemacy\Modules\ThemeBuilder\Services\TemplateService::class);
        $popups = \Elemacy\Core\Container::instance()->make(\Elemacy\Modules\Popups\Services\PopupService::class);
        $count = static fn(): int => (int) (new \WP_Query(['post_type' => 'elemacy_library', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => true]))->post_count;
        $before = $count();

        $attempts = [
            'template' => static fn() => $templates->create(\Elemacy\Modules\ThemeBuilder\DTO\CreateTemplateDTO::from_array([
                'title' => 'Valueless specific post header', 'type' => 'header', 'status' => 'publish', 'conditions' => $valueless,
            ])),
            'popup' => static fn() => $popups->create(\Elemacy\Modules\Popups\DTO\CreatePopupDTO::from_array([
                'title' => 'Valueless specific post popup', 'type' => 'popup', 'status' => 'publish', 'conditions' => $valueless,
            ])),
        ];

        foreach ($attempts as $kind => $attempt) {
            try {
                $created = $attempt();
                $this->documents->adopt((int) $created->id);
                self::fail("A $kind with a valueless Specific Post rule must be rejected.");
            } catch (\Elemacy\Http\Exceptions\ValidationException $error) {
                self::assertStringContainsString('post', wp_json_encode($error->get_errors()['conditions'] ?? []), 'The message asks for a post.');
            }
        }

        self::assertSame($before, $count(), 'Nothing is saved.');

        // An update that adds one is refused too, and leaves the stored rules alone.
        $popup = $popups->create(\Elemacy\Modules\Popups\DTO\CreatePopupDTO::from_array(['title' => 'Popup to update', 'type' => 'popup', 'status' => 'draft', 'conditions' => []]));
        $this->documents->adopt((int) $popup->id);
        try {
            $popups->update((int) $popup->id, \Elemacy\Modules\Popups\DTO\UpdatePopupDTO::from_array(['conditions' => $valueless]));
            self::fail('Adding a valueless Specific Post rule to a popup must be rejected.');
        } catch (\Elemacy\Http\Exceptions\ValidationException $error) {
            self::assertSame([], $this->conditions->get((int) $popup->id));
        }
    }

    public function testAStoredSpecificPostRuleWithNoPostIsKeptAndMatchesNothing(): void
    {
        $header = $this->documents->create('Legacy valueless header', 'header', 'publish');
        update_post_meta($header, ConditionRepository::META_KEY, [['id' => 'legacy', 'type' => 'singular/specific_post', 'operator' => 'include', 'value' => '']]);
        $stored = get_post_meta($header, ConditionRepository::META_KEY, true);

        $templates = \Elemacy\Core\Container::instance()->make(\Elemacy\Modules\ThemeBuilder\Services\TemplateService::class);
        $templates->update($header, \Elemacy\Modules\ThemeBuilder\DTO\UpdateTemplateDTO::from_array(['title' => 'Renamed legacy header', 'conditions' => $stored]));
        self::assertSame($stored, get_post_meta($header, ConditionRepository::META_KEY, true), 'The stored rule is accepted back unchanged.');

        (new ResolverCache())->invalidate();
        TemplateResolver::reset();
        $this->request->visit(get_permalink($this->posts[0]));
        TemplateResolver::reset();
        self::assertNull(TemplateResolver::instance()->resolve('header'), 'It matches nothing, not every singular page.');
    }

    public function testWithoutProTheSavedConditionIsSuspendedAndWorksAgainWhenProReturns(): void
    {
        $rule = ConditionRuleDTO::from_array([
            'type' => 'singular/specific_post',
            'value' => (string) $this->posts[0],
            'operator' => 'include',
        ]);

        $this->request->visit(get_permalink($this->posts[0]));

        // Pro inactive: Free's mock holds the identifier and suspends the rule.
        $without_pro = new ConditionManager();
        $without_pro->register(new \Elemacy\Conditions\Mock\SpecificPost());

        self::assertFalse(
            (new ConditionEvaluator($without_pro))->evaluate([$rule]),
            'The saved rule must not match while its implementation is unavailable.'
        );

        // Pro active: its real condition supersedes the mock under the same
        // identifier, and the very same saved rule now evaluates for real.
        $with_pro = new ConditionManager();
        $with_pro->register(new \Elemacy\Conditions\Mock\SpecificPost());
        $with_pro->register(new \ElemacyPro\Extensions\Conditions\Singular\SpecificPost());

        self::assertFalse($with_pro->get('singular/specific_post')->is_mock(), 'The real Pro condition must supersede the mock under the same identifier.');
        self::assertTrue($with_pro->get_availability('singular/specific_post')->is_available());
        self::assertTrue(
            (new ConditionEvaluator($with_pro))->evaluate([$rule]),
            'With Pro back the same saved rule must match, with no re-authoring.'
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
