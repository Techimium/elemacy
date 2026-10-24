<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Conditions\ConditionEvaluator;
use Elemacy\Conditions\ConditionInterface;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\ConditionRepository;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Core\Container;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Modules\ThemeBuilder\DTO\CreateTemplateDTO;
use Elemacy\Modules\ThemeBuilder\DTO\UpdateTemplateDTO;
use Elemacy\Modules\ThemeBuilder\Services\LocationRegistry;
use Elemacy\Modules\ThemeBuilder\Services\TemplateService;
use Elemacy\Support\ConfigList;
use Elemacy\Support\Utils;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * Search Results templates are targeted by the search conditions: the free
 * "Search Results" (search/all) and Pro's "Search Results: Post Type"
 * (search/post_type). Everything here runs on real WordPress search requests
 * and the real template resolver; "Pro absent" is a condition manager built
 * from Free's own config alone, because Pro cannot be unloaded mid-process.
 */
final class SearchTemplateConditionsTest extends IntegrationTestCase
{
    private const CPT = 'elemacy_fx_item';

    private RequestSimulator $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new RequestSimulator();
        register_post_type(self::CPT, ['public' => true, 'label' => 'Fixture Item', 'exclude_from_search' => false]);
    }

    protected function tearDown(): void
    {
        try {
            $this->request->restore();
            TemplateResolver::reset();
            unregister_post_type(self::CPT);
        } finally {
            parent::tearDown();
        }
    }

    public function testTheGlobalSearchConditionMatchesEverySearchAndNoConditionsMatchNone(): void
    {
        $with_rule = $this->searchTemplate('Search with a rule', [$this->rule('search/all')]);

        $this->visit('/?s=a&post_type=' . self::CPT);
        self::assertSame($with_rule, TemplateResolver::instance()->resolve('search'));

        $this->documents->cleanup();
        $this->searchTemplate('Search with no rules', []);

        $this->visit('/?s=a');
        self::assertNull(TemplateResolver::instance()->resolve('search'), 'A Search template with no conditions is not displayed for any search.');
    }

    public function testASearchTemplateSavedWithABlankTypeRuleIsSuspendedOnARealSearch(): void
    {
        $blank = $this->searchTemplate('Search with a blank rule', [$this->rule('')]);

        $this->visit('/?s=a');

        self::assertNull(TemplateResolver::instance()->resolve('search'), 'An unknown (blank) inclusion must suspend the template rather than match every search.');
        self::assertSame('', get_post_meta($blank, ConditionRepository::META_KEY, true)[0]['type'], 'The stored rule is left as saved so an author can repair it.');
    }

    public function testThePostTypeConditionOutranksTheGlobalOneForAMatchingSearch(): void
    {
        $this->requirePro();
        $global = $this->searchTemplate('Search global', [$this->rule('search/all')]);
        $typed  = $this->searchTemplate('Search by type', [$this->rule('search/post_type', 'include', self::CPT)]);

        $this->visit('/?s=a&post_type=' . self::CPT);
        self::assertSame($typed, TemplateResolver::instance()->resolve('search'), 'The post type template wins when the search is restricted to its type.');

        $this->visit('/?s=a&post_type=post');
        self::assertSame($global, TemplateResolver::instance()->resolve('search'), 'A search for another type falls back to the global template.');

        $this->visit('/?s=a');
        self::assertSame($global, TemplateResolver::instance()->resolve('search'), 'A search without a post type matches only the global template.');
    }

    public function testASearchAcrossSeveralPostTypesMatchesOnlyTheGlobalTemplate(): void
    {
        $this->requirePro();
        $global = $this->searchTemplate('Search global', [$this->rule('search/all')]);
        $this->searchTemplate('Search by type', [$this->rule('search/post_type', 'include', self::CPT)]);

        $this->visit('/?s=a&post_type[]=' . self::CPT . '&post_type[]=post');

        self::assertSame($global, TemplateResolver::instance()->resolve('search'));
    }

    public function testAPostTypeExcludeRuleVetoesTheTemplateForThatSearch(): void
    {
        $this->requirePro();
        $only = $this->searchTemplate('Search except type', [
            $this->rule('search/all'),
            $this->rule('search/post_type', 'exclude', self::CPT),
        ]);

        $this->visit('/?s=a&post_type=' . self::CPT);
        self::assertNull(TemplateResolver::instance()->resolve('search'));

        $this->visit('/?s=a&post_type=post');
        self::assertSame($only, TemplateResolver::instance()->resolve('search'));
    }

    public function testTheRealPostTypeConditionReplacesFreesPlaceholderUnderTheSameIdentifier(): void
    {
        $this->requirePro();

        $condition = ConditionManager::instance()->get('search/post_type');

        self::assertFalse($condition->is_mock());
        self::assertTrue(ConditionManager::instance()->get_availability('search/post_type')->is_available());
        self::assertTrue($condition->applies_to('search'));
        self::assertContains(self::CPT, array_column($condition->get_sub_values(), 'value'));
    }

    public function testWithoutProAPostTypeRuleSuspendsTheTemplateAndWorksAgainWhenProIsBack(): void
    {
        $this->visit('/?s=a&post_type=' . self::CPT);

        $rules   = ConditionRuleDTO::collection([$this->rule('search/post_type', 'include', self::CPT)]);
        $without = new ConditionEvaluator($this->freeOnlyManager());

        self::assertNull($without->match($rules), 'Without Pro the saved rule must suspend the template, never broaden it.');
        self::assertTrue($without->match(ConditionRuleDTO::collection([$this->rule('search/all')])) !== null, 'The free global condition keeps working.');

        if (ConditionManager::instance()->get('search/post_type') && !ConditionManager::instance()->get('search/post_type')->is_mock()) {
            self::assertNotNull(
                ConditionEvaluator::instance()->match($rules),
                'With Pro present the same saved rule matches again without being re-saved.'
            );
        }
    }

    public function testFreeAloneOffersSearchResultsToSearchHeaderAndFooterTemplatesOnly(): void
    {
        $manager = $this->freeOnlyManager();
        $names   = static function (string $template_type) use ($manager): array {
            $found = [];
            foreach ($manager->get_all() as $name => $condition) {
                if (0 === strpos($name, 'search/') && $condition->applies_to($template_type)) {
                    $found[] = $name;
                }
            }
            sort($found);

            return $found;
        };

        self::assertSame(['search/all', 'search/post_type'], $names('search'));
        self::assertSame([], $names('single'));
        self::assertSame([], $names('archive'));
        self::assertSame([], $names('404'));
        self::assertSame(['search/all'], $names('header'), 'Free\'s Search Results is offered on Header templates.');
        self::assertSame(['search/all'], $names('footer'), 'And on Footer templates.');
        self::assertTrue($manager->get('search/post_type')->is_mock());
    }

    public function testFreeAloneDisplaysAHeaderWithSearchResultsOnEverySearchPage(): void
    {
        $header = $this->service()->create(CreateTemplateDTO::from_array([
            'title' => 'Search header', 'type' => 'header', 'status' => 'publish',
            'conditions' => [$this->rule('search/all')],
        ]))->id;
        $this->documents->adopt((int) $header);

        $this->visit('/?s=anything');
        self::assertSame((int) $header, TemplateResolver::instance()->resolve('header'), 'The header is displayed on a search results page.');

        $this->request->visit(home_url('/'));
        TemplateResolver::reset();
        self::assertNull(TemplateResolver::instance()->resolve('header'), 'And nowhere else.');
    }

    public function testThereIsOneSearchResultsConditionAndNoAllArchivesCondition(): void
    {
        foreach (['free-only' => $this->freeOnlyManager(), 'active' => ConditionManager::instance()] as $profile => $manager) {
            self::assertNull($manager->get('general/search_results'), "$profile: general/search_results is not registered.");
            self::assertNull($manager->get('archive/all'), "$profile: archive/all is not registered.");

            foreach (['header', 'footer', 'popup', 'search'] as $context) {
                $search_results = array_keys(array_filter(
                    $manager->get_all(),
                    static fn($condition): bool => 'Search Results' === $condition->get_label() && $condition->applies_to($context)
                ));
                self::assertSame(['search/all'], $search_results, "$profile: exactly one Search Results option for $context.");
            }
        }
    }

    public function testCreatingATemplateWithABlankTypeRuleIsRejectedAndNothingIsSaved(): void
    {
        $before = $this->templateCount();

        try {
            $this->service()->create(CreateTemplateDTO::from_array([
                'title' => 'Blank rule on create', 'type' => 'search', 'status' => 'publish',
                'conditions' => [$this->rule('')],
            ]));
            self::fail('A blank-type rule on create must be rejected.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('conditions', $error->get_errors());
        }

        self::assertSame($before, $this->templateCount(), 'A rejected create must not leave a template behind.');
    }

    public function testUpdatingATemplateToAddABlankTypeRuleIsRejectedAndTheStoredRulesAreUntouched(): void
    {
        $id = $this->searchTemplate('Search to update', [$this->rule('search/all')]);
        $stored = get_post_meta($id, ConditionRepository::META_KEY, true);

        try {
            $this->service()->update($id, UpdateTemplateDTO::from_array([
                'title' => 'Renamed by a rejected update',
                'conditions' => [$stored[0], $this->rule('')],
            ]));
            self::fail('A new blank-type rule on update must be rejected.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('conditions', $error->get_errors());
        }

        self::assertSame($stored, get_post_meta($id, ConditionRepository::META_KEY, true));
        self::assertSame('Search to update', get_the_title($id), 'A rejected update must not change other fields either.');
    }

    public function testAnUnrelatedUpdateOfATemplateThatAlreadyStoresABlankRuleIsAcceptedAndLeavesTheRuleIdentical(): void
    {
        $id = $this->searchTemplate('Search with legacy rule', [$this->rule('')]);
        $stored = get_post_meta($id, ConditionRepository::META_KEY, true);

        $this->service()->update($id, UpdateTemplateDTO::from_array([
            'title' => 'Renamed',
            'conditions' => $stored,
        ]));

        self::assertSame('Renamed', get_the_title($id));
        self::assertSame($stored, get_post_meta($id, ConditionRepository::META_KEY, true), 'The stored rule must be byte-identical.');
    }

    /**
     * @param array<int, array<string, string>> $rules
     */
    private function searchTemplate(string $title, array $rules): int
    {
        $id = $this->documents->create($title, 'search');

        if ($rules) {
            update_post_meta($id, ConditionRepository::META_KEY, $rules);
        }

        return $id;
    }

    /** @return array{id: string, type: string, operator: string, value: string} */
    private function rule(string $type, string $operator = 'include', string $value = ''): array
    {
        return ['id' => 'r-' . wp_generate_uuid4(), 'type' => $type, 'operator' => $operator, 'value' => $value];
    }

    private function visit(string $url): void
    {
        $this->request->visit($url);
        TemplateResolver::reset();

        self::assertSame('search', LocationRegistry::instance()->current_type(), 'The simulated request must be a search location.');
    }

    private function requirePro(): void
    {
        if (!class_exists(\ElemacyPro\Core\ElemacyPro::class)) {
            self::markTestSkipped('Elemacy Pro is not installed in this environment (provision with --with-pro).');
        }

        $condition = ConditionManager::instance()->get('search/post_type');

        self::assertNotNull($condition, 'Pro is active, so search/post_type must be registered.');
        self::assertFalse($condition->is_mock(), 'Pro is active, so its real search/post_type must replace the placeholder.');
    }

    private function freeOnlyManager(): ConditionManager
    {
        $manager = new ConditionManager();

        foreach (ConfigList::instances(Utils::get_plugin_path('src/Config/conditions.php'), ConditionInterface::class) as $condition) {
            $manager->register($condition);
        }

        return $manager;
    }

    private function service(): TemplateService
    {
        return Container::instance()->make(TemplateService::class);
    }

    private function templateCount(): int
    {
        return (int) (new \WP_Query([
            'post_type' => 'elemacy_library', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => true,
        ]))->post_count;
    }
}
