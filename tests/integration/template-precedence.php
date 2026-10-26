<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Conditions\BaseCondition;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\ConditionRepository;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Conditions\Specificity;
use Elemacy\TemplateLibrary\ResolverCache;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * A condition an extension could contribute: it targets one identified post,
 * declares the matching specificity, and the resolver has never heard of it.
 */
final class PrecedenceSpecificPostCondition extends BaseCondition
{
    public function get_name(): string
    {
        return 'test/precedence_specific_post';
    }

    public function get_type(): string
    {
        return 'singular';
    }

    public function get_label(): string
    {
        return 'Precedence — Specific Post';
    }

    public function get_applicable_template_types(): array
    {
        return ['*'];
    }

    public function check(ConditionRuleDTO $rule): bool
    {
        return is_singular() && (int) $rule->value === (int) get_the_ID();
    }

    public function get_specificity(ConditionRuleDTO $rule): int
    {
        return Specificity::narrowed($rule->value, Specificity::OBJECT, Specificity::SECTION);
    }
}

/**
 * Of several header templates matching one request, the one that matched most
 * specifically is the one that resolves — against real stored templates, real
 * stored conditions and a real WordPress query.
 */
final class TemplatePrecedenceTest extends IntegrationTestCase
{
    private RequestSimulator $request;
    private ConditionRepository $conditions;
    private array $posts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new RequestSimulator();
        $this->conditions = new ConditionRepository();

        ConditionManager::instance()->register(new PrecedenceSpecificPostCondition());

        foreach (['Precedence target', 'Precedence other'] as $title) {
            $id = wp_insert_post([
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'post_content' => 'Precedence fixture.',
            ], true);

            if (is_wp_error($id)) {
                throw new \RuntimeException($id->get_error_message());
            }

            $this->posts[] = (int) $id;
        }
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
            $this->refresh_index();
            parent::tearDown();
        }
    }

    /**
     * Creates a published header carrying $rules, oldest first, so the index's
     * newest-first ordering is the reverse of creation order.
     *
     * @param array<int, array{0: string, 1?: string, 2?: string}> $rules
     */
    private function header(string $title, array $rules): int
    {
        $id = $this->documents->create($title, 'header', 'publish');

        $this->conditions->save($id, array_map(
            static fn(array $rule): array => [
                'type' => $rule[0],
                'value' => $rule[1] ?? '',
                'operator' => $rule[2] ?? 'include',
            ],
            $rules
        ));

        $this->refresh_index();

        return $id;
    }

    private function refresh_index(): void
    {
        (new ResolverCache())->invalidate();
        TemplateResolver::reset();
    }

    private function resolve_header_on(int $post_id): ?int
    {
        $this->request->visit(get_permalink($post_id));
        $this->refresh_index();

        return TemplateResolver::instance()->resolve('header');
    }

    public function testASiteWideHeaderDoesNotOverrideOneTargetingSingularPosts(): void
    {
        // Narrow first, broad second: the index is newest-first, so only
        // specificity — never index order — can produce the right answer.
        $singular = $this->header('Precedence singular post', [['singular/post_type', 'post']]);
        $site_wide = $this->header('Precedence site wide', [['general/entire_site']]);

        self::assertSame(
            $singular,
            $this->resolve_header_on($this->posts[0]),
            'The header targeting singular posts must win over the site-wide one (site-wide id ' . $site_wide . ').'
        );
    }

    public function testAHeaderTargetingOneSpecificPostBeatsOneTargetingItsPostType(): void
    {
        $specific = $this->header('Precedence specific post', [['test/precedence_specific_post', (string) $this->posts[0]]]);
        $this->header('Precedence post type', [['singular/post_type', 'post']]);

        self::assertSame($specific, $this->resolve_header_on($this->posts[0]));
    }

    public function testTheNewerButBroaderHeaderStillLosesToTheOlderNarrowerOne(): void
    {
        $narrow = $this->header('Precedence narrow, created first', [['test/precedence_specific_post', (string) $this->posts[0]]]);
        $this->header('Precedence broad, created second', [['general/entire_site']]);

        self::assertSame(
            $narrow,
            $this->resolve_header_on($this->posts[0]),
            'Creation order must not decide the winner when one candidate is more specific.'
        );
    }

    public function testWhenOnlyTheBroaderHeaderMatchesItIsTheOneResolved(): void
    {
        $site_wide = $this->header('Precedence site wide', [['general/entire_site']]);
        $this->header('Precedence other post only', [['test/precedence_specific_post', (string) $this->posts[1]]]);

        self::assertSame($site_wide, $this->resolve_header_on($this->posts[0]));
    }

    public function testAnExclusionNeverRaisesATemplateAboveTheInclusionsItMatchedOn(): void
    {
        $singular = $this->header('Precedence singular post', [['singular/post_type', 'post']]);
        $this->header('Precedence site wide, excluding another post', [
            ['general/entire_site'],
            ['test/precedence_specific_post', (string) $this->posts[1], 'exclude'],
        ]);

        self::assertSame(
            $singular,
            $this->resolve_header_on($this->posts[0]),
            'A narrow exclusion that did not match must not make a site-wide template outrank a type-level one.'
        );
    }

    public function testAMatchingExclusionStillVetoesTheTemplateEntirely(): void
    {
        $this->header('Precedence specific but excluded', [
            ['test/precedence_specific_post', (string) $this->posts[0]],
            ['general/entire_site', '', 'exclude'],
        ]);
        $site_wide = $this->header('Precedence site wide', [['general/entire_site']]);

        self::assertSame($site_wide, $this->resolve_header_on($this->posts[0]));
    }

    public function testTwoHeadersAtTheSameLevelResolveToOneIdenticallyOnRepeatedRequests(): void
    {
        $this->header('Precedence tie, created first', [['singular/post_type', 'post']]);
        $newer = $this->header('Precedence tie, created second', [['singular/post_type', 'post']]);

        $first = $this->resolve_header_on($this->posts[0]);

        self::assertSame($newer, $first, 'Within one specificity level the newest template wins.');
        self::assertSame($first, $this->resolve_header_on($this->posts[0]));
        self::assertSame($first, $this->resolve_header_on($this->posts[0]));
    }

    public function testAnExtensionsConditionDecidesTheWinnerWithoutTheResolverKnowingIt(): void
    {
        $extension = $this->header('Precedence extension condition', [['test/precedence_specific_post', (string) $this->posts[0]]]);
        $this->header('Precedence site wide', [['general/entire_site']]);

        self::assertSame($extension, $this->resolve_header_on($this->posts[0]));

        $resolver = file_get_contents(ELEMACY_PATH . 'src/TemplateLibrary/TemplateResolver.php');
        self::assertStringNotContainsString(
            'test/precedence_specific_post',
            $resolver,
            'The resolver must decide by declared specificity, never by enumerating condition identifiers.'
        );
    }

    public function testAConditionlessHeaderIsNeverDisplayed(): void
    {
        $this->header('Precedence no conditions', []);

        self::assertNull(
            $this->resolve_header_on($this->posts[0]),
            'A header with no inclusion condition names nowhere to display, so nothing resolves.'
        );

        $site_wide = $this->header('Precedence site wide', [['general/entire_site']]);

        self::assertSame($site_wide, $this->resolve_header_on($this->posts[0]));
    }

    public function testAHeaderWhoseRulesOnlyExcludeIsNeverDisplayed(): void
    {
        $this->header('Precedence exclusion only', [['test/precedence_specific_post', (string) $this->posts[1], 'exclude']]);

        self::assertNull($this->resolve_header_on($this->posts[0]));
    }

    public function testTheNewest404TemplateCoversEveryNotFoundRequest(): void
    {
        $older = $this->documents->create('Precedence 404 older', '404', 'publish');
        $newer = $this->documents->create('Precedence 404 newer', '404', 'publish');
        $this->documents->create('Precedence 404 draft', '404', 'draft');

        $this->request->visit(home_url('/?p=999999999'));
        $this->refresh_index();

        self::assertTrue(is_404(), 'Sanity: the request must be a not-found one.');
        self::assertSame($newer, TemplateResolver::instance()->resolve('404'), 'Older 404 id ' . $older . ' must lose to the newer one.');
    }

    public function testPopupsStillCollectEveryMatchingItem(): void
    {
        $everywhere = $this->documents->create('Precedence popup without conditions', 'popup', 'publish');

        $site_wide = $this->documents->create('Precedence popup site wide', 'popup', 'publish');
        $this->conditions->save($site_wide, [['type' => 'general/entire_site', 'value' => '', 'operator' => 'include']]);

        $specific = $this->documents->create('Precedence popup specific', 'popup', 'publish');
        $this->conditions->save($specific, [[
            'type' => 'test/precedence_specific_post',
            'value' => (string) $this->posts[0],
            'operator' => 'include',
        ]]);

        $this->request->visit(get_permalink($this->posts[0]));
        $this->refresh_index();

        $matched = TemplateResolver::instance()->resolve_group('popup');

        self::assertContains($site_wide, $matched, 'Collect-all must not be filtered by specificity.');
        self::assertContains($specific, $matched);
        self::assertContains($everywhere, $matched, 'A popup without conditions keeps displaying everywhere.');
    }
}
