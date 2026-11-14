<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Loop\Access\PaginationContextSigner;
use Elemacy\Loop\Access\PublicRenderPolicy;
use Elemacy\Loop\Access\PublishedLoopDefinitionResolver;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\Loop\Support\LoopLimits;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardDefinition;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Tests\Integration\Fixtures\NativeAjaxRequest;
use Elemacy\Tests\Integration\Fixtures\EditorControls;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/NativeAjaxRequest.php';
require_once __DIR__ . '/fixtures/EditorControls.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * Post Grid and Post Carousel on real saved documents: first-page rendering,
 * the real pagination endpoint, public eligibility and empty states.
 */
final class PostWidgetsTest extends IntegrationTestCase
{
    private const WIDGET_ID = 'eac9001';
    private const TYPE = 'fx_listing';

    private RequestSimulator $request;
    /** @var int[] */
    private array $posts = [];
    /** @var int[] */
    private array $terms = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new RequestSimulator();
        register_post_type(self::TYPE, ['public' => true, 'label' => 'Fx Listings', 'supports' => ['title', 'editor']]);
    }

    protected function tearDown(): void
    {
        try {
            $this->request->restore();
            foreach (array_reverse($this->posts) as $id) {
                wp_delete_post($id, true);
            }
            foreach ($this->terms as $id) {
                wp_delete_term($id, 'category');
            }
            unregister_post_type(self::TYPE);
        } finally {
            parent::tearDown();
        }
    }

    public function testNoCardControlOffersDynamicTags(): void
    {
        foreach (['elemacy-post-grid', 'elemacy-post-carousel'] as $name) {
            $control = Plugin::instance()->widgets_manager->get_widget_types($name)->get_controls(PostCardDefinition::PARTS);

            self::assertNotEmpty($control['fields'], "$name must expose its card rows.");
            foreach ($control['fields'] as $id => $field) {
                self::assertEmpty($field['dynamic']['active'] ?? false, "$name row control $id must not offer dynamic tags.");
            }
        }
    }

    public function testPostWidgetsOnlyOfferPostSources(): void
    {
        self::assertEqualsCanonicalizing(['posts', 'current_query', 'manual_posts', 'related_posts'], $this->offered_sources('elemacy-post-grid'));
        self::assertEqualsCanonicalizing(['posts', 'current_query', 'manual_posts', 'related_posts'], $this->offered_sources('elemacy-post-carousel'));
    }

    public function testLoopWidgetsStillOfferEverySource(): void
    {
        self::assertEqualsCanonicalizing(array_keys(LoopDataSourceRegistry::instance()->all()), $this->offered_sources('elemacy-loop-grid'));
    }

    public function testPostGridListsACustomPostTypeWithoutATemplate(): void
    {
        $listing = $this->listing('Listing one');
        $owner = $this->host($this->settings());
        $this->request->visit(get_permalink($owner));

        $html = $this->renderWidget($owner);

        self::assertStringContainsString('elemacy-post-card--type-' . self::TYPE, $html);
        self::assertStringContainsString('data-elemacy-item="' . $listing . '"', $html);
        self::assertStringContainsString('elemacy-post-grid--grid', $html);
    }

    /** @dataProvider layouts */
    public function testLayoutClass(string $layout): void
    {
        $this->listing('Layout');
        $owner = $this->host($this->settings([PostCardDefinition::LAYOUT => $layout]));
        $this->request->visit(get_permalink($owner));

        self::assertStringContainsString('elemacy-post-grid--' . $layout, $this->renderWidget($owner));
    }

    public static function layouts(): array
    {
        return [['grid'], ['list'], ['masonry']];
    }

    public function testDraftsAndPasswordPostsAreNotListed(): void
    {
        $public = $this->listing('Public');
        $draft = $this->listing('Draft', ['post_status' => 'draft']);
        $locked = $this->listing('Locked', ['post_password' => 'pw']);
        $owner = $this->host($this->settings());
        $this->request->visit(get_permalink($owner));
        wp_set_current_user(0);

        $html = $this->renderWidget($owner);

        self::assertStringContainsString('data-elemacy-item="' . $public . '"', $html);
        self::assertStringNotContainsString('data-elemacy-item="' . $draft . '"', $html);
        self::assertStringNotContainsString('data-elemacy-item="' . $locked . '"', $html);
    }

    public function testEndpointServesPostGridAndCardsMatchTheFirstPage(): void
    {
        $settings = $this->stored_posts(4, 'Listing');
        $owner = $this->host($settings + $this->settings(['pagination_type' => 'load_more', 'posts_per_page' => 2]));

        $this->request->visit(add_query_arg('paged', 2, get_permalink($owner)));
        $classic = $this->itemHtml($this->renderWidget($owner));

        $this->request->visit(get_permalink($owner));
        $endpoint = $this->endpointPage($owner, 2);

        self::assertCount(2, $classic);
        self::assertSame($classic, $this->itemHtml($endpoint['items']), 'A later page renders the same cards as the same page opened directly.');
        self::assertSame(0, $endpoint['next_page']);
    }

    public function testPerRowOverridesAreKeptOnLaterPages(): void
    {
        $settings = $this->stored_posts(3, 'Row');
        $parts = [['_id' => 'fx12345', 'part' => 'title', 'zone' => 'body', 'row_color' => '#123456']];
        $owner = $this->host($settings + $this->settings(['pagination_type' => 'load_more', 'posts_per_page' => 2, PostCardDefinition::PARTS => $parts]));
        $this->request->visit(add_query_arg('paged', 2, get_permalink($owner)));
        $classic = $this->itemHtml($this->renderWidget($owner));
        $this->request->visit(get_permalink($owner));
        $endpoint = $this->endpointPage($owner, 2)['items'];

        self::assertStringContainsString('elementor-repeater-item-fx12345', $endpoint);
        self::assertSame($classic, $this->itemHtml($endpoint), 'Saved rows render the same on a later page.');
    }

    public function testARowOverrideOutranksTheStyleTabForThatRowOnly(): void
    {
        $settings = $this->stored_posts(3, 'Outrank');
        $parts = [
            ['_id' => 'fx80001', 'part' => 'title', 'zone' => 'body', 'row_style' => 'yes', 'row_color' => '#123456', 'row_typography_typography' => 'custom', 'row_typography_font_size' => ['unit' => 'px', 'size' => 33]],
            ['_id' => 'fx80002', 'part' => 'title', 'zone' => 'body'],
        ];
        $owner = $this->host($settings + $this->settings([
            'pagination_type' => 'load_more', 'posts_per_page' => 2, PostCardDefinition::PARTS => $parts,
            'card_title_color' => '#aa0000', 'card_title_hover_color' => '#00aa00',
            'card_title_typography_typography' => 'custom', 'card_title_typography_font_size' => ['unit' => 'px', 'size' => 20],
        ]));
        $rules = $this->cssRules($this->css($owner));

        foreach (['color' => ['#123456', '#aa0000'], 'font-size' => ['33px', '20px']] as $property => [$override, $style]) {
            $overriding = $this->selectorsSetting($rules, $property, $override);
            $styled = $this->selectorsSetting($rules, $property, $style);

            self::assertNotSame([], $overriding, 'The row override prints its ' . $property . '.');
            self::assertNotSame([], $styled, 'The Style tab prints its ' . $property . '.');
            foreach ($overriding as $selector) {
                self::assertStringContainsString('elementor-repeater-item-fx80001', $selector);
                self::assertStringNotContainsString('fx80002', $selector, 'Only the overridden row changes.');
                foreach (array_merge($styled, $this->selectorsSetting($rules, 'color', '#00aa00')) as $against) {
                    self::assertGreaterThan(0, $this->compareSpecificity($selector, $against), $selector . ' must beat ' . $against . '.');
                }
            }
        }

        self::assertStringNotContainsString('fx80002', implode('', array_keys($rules)), 'A row without overrides prints nothing of its own.');

        $this->request->visit(get_permalink($owner));
        $later = $this->endpointPage($owner, 2)['items'];
        self::assertStringContainsString('elementor-repeater-item-fx80001', $later);
        self::assertStringContainsString('elementor-repeater-item-fx80002', $later);
    }

    public function testARowOverrideIsIgnoredUntilItsRowIsStyled(): void
    {
        $this->listing('Unstyled');
        $off = ['_id' => 'fx80003', 'part' => 'title', 'row_color' => '#123456', 'row_typography_typography' => 'custom', 'row_typography_font_size' => ['unit' => 'px', 'size' => 33]];
        $owner = $this->host($this->settings([PostCardDefinition::PARTS => [$off]]));
        $unstyled = $this->css($owner);

        self::assertStringNotContainsString('fx80003', $unstyled, 'Saved overrides stay quiet while Style This Row is off.');

        $owner = $this->host($this->settings([PostCardDefinition::PARTS => [$off + ['row_style' => 'yes']]]));
        $styled = $this->css($owner);

        self::assertStringContainsString('fx80003', $styled);
        self::assertStringContainsString('#123456', $styled);
        self::assertStringContainsString('font-size:33px', $styled);
    }

    public function testARowSavedAtItsDefaultPartRendersOnLaterPages(): void
    {
        $settings = $this->stored_posts(3, 'Stripped');
        // A Title row as the editor saves it: Elementor drops every value equal to its control's default.
        $parts = [['_id' => 'fx67890'], ['_id' => 'fx67891', 'part' => 'button', 'zone' => 'footer']];
        $owner = $this->host($settings + $this->settings(['pagination_type' => 'load_more', 'posts_per_page' => 2, PostCardDefinition::PARTS => $parts]));
        $this->request->visit(add_query_arg('paged', 2, get_permalink($owner)));
        $classic = $this->itemHtml($this->renderWidget($owner));
        $this->request->visit(get_permalink($owner));
        $endpoint = $this->endpointPage($owner, 2)['items'];

        self::assertStringContainsString('elemacy-post-card__title', $endpoint);
        self::assertSame($classic, $this->itemHtml($endpoint), 'A later page renders the same Title row as the first page.');
    }

    public function testAWholeCardLinkNeedsNoVisibleTitleOnTheFirstPageOrLater(): void
    {
        $settings = $this->stored_posts(3, 'Linked');
        $parts = [['_id' => 'fx70001', 'row_hide_on' => ['mobile']], ['_id' => 'fx70002', 'part' => 'button', 'zone' => 'footer']];
        $owner = $this->host($settings + $this->settings([
            'pagination_type' => 'load_more', 'posts_per_page' => 2, PostCardDefinition::PARTS => $parts, PostCardDefinition::WHOLE_CARD_LINK => 'yes',
        ]));
        $this->request->visit(add_query_arg('paged', 2, get_permalink($owner)));
        $classic = $this->itemHtml($this->renderWidget($owner));
        $this->request->visit(get_permalink($owner));
        $endpoint = $this->endpointPage($owner, 2)['items'];

        self::assertSame($classic, $this->itemHtml($endpoint));
        self::assertSame(1, substr_count($endpoint, 'class="elemacy-post-card__overlay"'));
        self::assertStringNotContainsString('tabindex="-1" aria-hidden="true"></a></article>', $endpoint, 'A Title hidden on a device is no keyboard stop, so the overlay is the focusable link.');
    }

    public function testAnUntitledPostsLinksAreNamedOnTheFirstPageAndLater(): void
    {
        $settings = $this->stored_posts(1, 'Titled');
        $this->listing('', ['post_type' => 'post', 'post_content' => 'Untitled body', 'post_date' => '2024-02-01 10:00:00', 'post_category' => [end($this->terms)]]);
        $parts = [['part' => 'title'], ['part' => 'button', 'zone' => 'footer']];
        $owner = $this->host($settings + $this->settings([
            'pagination_type' => 'load_more', 'posts_per_page' => 1, PostCardDefinition::PARTS => $parts, PostCardDefinition::WHOLE_CARD_LINK => 'yes',
        ]));
        $this->request->visit(get_permalink($owner));
        $first = $this->renderWidget($owner);
        $later = $this->endpointPage($owner, 2)['items'];

        // The newest post is the untitled one, so it opens the first page; the titled one follows on page two.
        self::assertStringContainsString('<span class="elemacy-post-card__sr">(no title)</span></a>', $first);
        self::assertStringContainsString('tabindex="-1" aria-hidden="true"></a></article>', $later, 'A titled post\'s Title link is its keyboard stop.');
        self::assertStringContainsString('<span class="elemacy-post-card__sr">: (no title)</span>', $first);
        self::assertStringContainsString('<span class="elemacy-post-card__sr">: Titled 1</span>', $later);
    }

    public function testACardWithNoPartsRendersNoListForVisitorsAndANoticeInTheEditor(): void
    {
        $this->listing('Partless');
        $owner = $this->host($this->settings([PostCardDefinition::PARTS => []]));
        $this->request->visit(get_permalink($owner));
        wp_set_current_user(0);

        self::assertSame('', trim($this->renderWidget($owner)));

        Plugin::$instance->editor->set_edit_mode(true);

        try {
            $html = $this->renderWidget($owner);
        } finally {
            Plugin::$instance->editor->set_edit_mode(false);
        }

        self::assertStringContainsString('Add at least one part to the card.', $html);
        self::assertStringNotContainsString('elemacy-post-card', $html);
    }

    public function testACardOfPartsThatShowNothingForAPostRendersNoCardForIt(): void
    {
        $this->listing('', ['post_content' => 'No title here']);
        $owner = $this->host($this->settings([PostCardDefinition::PARTS => [['part' => 'title']]]));
        $this->request->visit(get_permalink($owner));
        wp_set_current_user(0);

        $html = $this->renderWidget($owner);

        self::assertStringNotContainsString('<article', $html);
        self::assertStringNotContainsString('elemacy-loop-item', $html);
    }

    public function testADefaultSavedManualSelectionPagesTheSameOnTheFirstPageAndTheEndpoint(): void
    {
        $this->stored_posts(8, 'Pick');
        $picked = array_reverse(array_slice($this->posts, -8));
        $owner = $this->host(['data_source' => 'manual_posts', 'manual_posts_ids' => $picked, 'pagination_type' => 'load_more']);
        $this->request->visit(get_permalink($owner));

        $first = $this->itemIds($this->renderWidget($owner));
        $second = $this->endpointPage($owner, 2);

        self::assertSame(array_slice($picked, 0, 6), $first, 'The unsaved default page size shows six picks, in the chosen order.');
        self::assertSame(array_slice($picked, 6), $this->itemIds($second['items']), 'The endpoint continues from the same page size.');
        self::assertSame(0, $second['next_page']);
    }

    public function testASavedPageSizeAppliesToManualSelectionOnEveryPage(): void
    {
        $this->stored_posts(5, 'Sized');
        $picked = array_slice($this->posts, -5);
        $owner = $this->host(['data_source' => 'manual_posts', 'manual_posts_ids' => $picked, 'posts_per_page' => 2, 'pagination_type' => 'load_more']);
        $this->request->visit(get_permalink($owner));

        self::assertSame(array_slice($picked, 0, 2), $this->itemIds($this->renderWidget($owner)));
        self::assertSame(array_slice($picked, 2, 2), $this->itemIds($this->endpointPage($owner, 2)['items']));
        self::assertSame(array_slice($picked, 4), $this->itemIds($this->endpointPage($owner, 3)['items']));
    }

    /** @dataProvider savedOffsets */
    public function testAnOffsetListContinuesOnLaterPagesWithoutRepeatingOrSkipping(int $offset): void
    {
        $query = $this->stored_posts(10, 'Offset');
        $newest_first = array_reverse(array_slice($this->posts, -10));
        $owner = $this->host($query + ['data_source' => 'posts', 'offset' => $offset, 'posts_per_page' => 3, 'pagination_type' => 'load_more']);
        $this->request->visit(get_permalink($owner));

        $first_html = $this->renderWidget($owner);
        $second = $this->endpointPage($owner, 2);

        self::assertSame(array_slice($newest_first, 4, 3), $this->itemIds($first_html), 'The first page skips four posts, then shows the fifth to seventh.');
        self::assertStringContainsString('elemacy-load-more', $first_html, 'Ten posts less four skipped, three a page, is two pages: the first offers a next one.');
        self::assertSame(2, $this->endpointPage($owner, 1)['next_page'], 'The first page points at the second.');
        self::assertSame(array_slice($newest_first, 7, 3), $this->itemIds($second['items']), 'The second page continues with the eighth to tenth, none repeated or skipped.');
        self::assertSame(0, $second['next_page'], 'The list has exactly two pages.');
        self::assertSame('', trim($second['pagination']), 'The last page offers no further link.');

        $third = (array) json_decode($this->endpointResponse($owner, 3), true);
        self::assertFalse($third['success'] ?? true, 'A third page is refused: ' . wp_json_encode($third));
    }

    public static function savedOffsets(): array
    {
        return ['positive offset' => [4], 'negative offset behaves as its absolute value' => [-4]];
    }

    /** @dataProvider savedPageSizes */
    public function testAMalformedPageSizeIsNormalisedAlikeOnTheFirstPageAndTheEndpoint(string $source, $saved, int $first, int $second): void
    {
        $query = $this->stored_posts(8, 'Normalised');
        $picked = array_reverse(array_slice($this->posts, -8));
        $settings = 'manual_posts' === $source
            ? ['data_source' => 'manual_posts', 'manual_posts_ids' => $picked]
            : $query + ['data_source' => 'posts'];
        $owner = $this->host($settings + ['posts_per_page' => $saved, 'pagination_type' => 'load_more']);
        $this->request->visit(get_permalink($owner));

        self::assertCount($first, $this->itemIds($this->renderWidget($owner)), 'The first page.');
        self::assertCount($second, $this->itemIds($this->endpointPage($owner, 2)['items']), 'The next page, from the saved settings alone.');

        Plugin::$instance->editor->set_edit_mode(true);

        try {
            self::assertCount($first, $this->itemIds($this->renderWidget($owner)), 'The editor preview.');
        } finally {
            Plugin::$instance->editor->set_edit_mode(false);
        }
    }

    public static function savedPageSizes(): array
    {
        return [
            'posts, empty' => ['posts', '', 6, 2],
            'posts, zero' => ['posts', 0, 6, 2],
            'posts, negative' => ['posts', -1, 6, 2],
            'posts, text' => ['posts', 'many', 6, 2],
            'manual, empty' => ['manual_posts', '', 6, 2],
            'manual, negative' => ['manual_posts', -1, 6, 2],
        ];
    }

    /** @dataProvider pageSizePlacements */
    public function testThePageSizeControlIsRegisteredOnceInItsSection(string $widget, string $section, string $label, string $after, bool $has_notice, string $description): void
    {
        $controls = EditorControls::of(Plugin::instance()->widgets_manager->get_widget_types($widget));
        $names = array_keys($controls);
        $before = $names[array_search('posts_per_page', $names, true) - 1];

        self::assertSame($section, $controls['posts_per_page']['section']);
        self::assertSame($label, $controls['posts_per_page']['label']);
        self::assertSame(6, $controls['posts_per_page']['default']);
        self::assertSame(1, $controls['posts_per_page']['min']);
        self::assertSame(100, $controls['posts_per_page']['max']);
        self::assertSame($description, $controls['posts_per_page']['description'] ?? '');
        self::assertEqualsCanonicalizing(['posts', 'manual_posts', 'related_posts'], $controls['posts_per_page']['condition']['data_source']);
        self::assertSame($after, 0 === strpos($before, 'columns_') ? 'columns' : $before, 'The control sits directly under this one.');
        self::assertSame([], preg_grep('/per_page$/', array_diff($names, ['posts_per_page'])), 'One page size setting for every post source.');
        self::assertSame($has_notice, isset($controls['page_size_notice']));

        if ($has_notice) {
            self::assertSame($section, $controls['page_size_notice']['section']);
            self::assertSame(['data_source' => 'current_query'], $controls['page_size_notice']['condition']);
            self::assertStringContainsString('Settings &gt; Reading', $controls['page_size_notice']['raw']);
        }
    }

    public static function pageSizePlacements(): array
    {
        return [
            ['elemacy-post-grid', 'section_layout', 'Posts Per Page', 'columns', true, 'Also the most picks a Manual Selection shows.'],
            ['elemacy-post-carousel', 'section_layout', 'Posts to Show', 'section_layout', true, 'Also the most picks a Manual Selection shows.'],
            ['elemacy-loop-grid', 'section_query', 'Posts Per Page', 'data_source', false, ''],
            ['elemacy-loop-carousel', 'section_query', 'Posts Count', 'data_source', false, ''],
        ];
    }

    public function testTheCardOffersOnlyItsReleasedOptions(): void
    {
        $controls = EditorControls::of(Plugin::instance()->widgets_manager->get_widget_types('elemacy-post-grid'));
        $rows = $controls[PostCardDefinition::PARTS]['fields'];

        self::assertSame(['top', 'left', 'right', 'background'], array_keys($controls['card_image_position']['options']));
        self::assertSame(['image', 'title', 'meta', 'excerpt', 'terms', 'author', 'button', 'custom_field'], array_keys($rows['part']['options']));
        self::assertSame(
            ['row_style', 'row_color', 'row_typography_typography', 'row_hide_on'],
            array_values(preg_grep('/^row_(?!typography_(?!typography$))/', array_keys($rows))),
            'A row overrides its color, typography and visibility only.'
        );
        self::assertSame('switcher', $rows['row_style']['type']);
        self::assertSame('Style This Row', $rows['row_style']['label']);
        self::assertSame('', $rows['row_style']['default']);
        self::assertArrayNotHasKey('condition', $rows['row_style']);
        self::assertArrayNotHasKey('condition', $rows['row_hide_on'], 'Hiding a row is not a style override.');

        foreach (preg_grep('/^row_(color|typography_.+)$/', array_keys($rows)) as $name) {
            self::assertSame('yes', $rows[$name]['condition']['row_style'] ?? null, $name . ' only applies when the row is styled.');
        }
        self::assertSame(['author_avatar', 'author_name', 'author_link', 'author_avatar_size'], array_values(preg_grep('/^author_/', array_keys($rows))));
        self::assertSame('Images to Load Right Away', $rows['image_eager_count']['label']);
        self::assertSame(0, $rows['image_eager_count']['default']);
        self::assertArrayNotHasKey('description', $rows['button_text']);
        self::assertSame('Number of posts to skip.', $controls['offset']['description']);
    }

    public function testASavedImagePositionThatIsNoLongerOfferedLeavesTheDefaultLayout(): void
    {
        $this->listing('Position');
        $offered = $this->host($this->settings(['card_image_position' => 'left']));
        $removed = $this->host($this->settings(['card_image_position' => 'alternate', 'card_image_position_tablet' => 'bottom']));
        $this->request->visit(get_permalink($removed));

        self::assertStringContainsString('--elemacy-card-direction', $this->css($offered));
        self::assertStringNotContainsString('--elemacy-card-direction', $this->css($removed), 'The stylesheet\'s own defaults, which are Top, apply.');
        self::assertStringContainsString('elemacy-post-card', $this->renderWidget($removed));
    }

    public function testEndpointRefusesAPostCarousel(): void
    {
        $this->listing('Carousel');
        $owner = $this->host($this->settings(), 'elemacy-post-carousel');
        $this->request->visit(get_permalink($owner));

        $response = (array) json_decode(NativeAjaxRequest::loop(wp_json_encode([
            'version' => 1, 'owner_id' => $owner, 'widget_id' => self::WIDGET_ID, 'page' => 2,
            'fingerprint' => str_repeat('0', 64), 'context' => '',
        ]), ''), true);

        self::assertFalse($response['success'] ?? true);

        $this->expectException(\Elemacy\Http\Exceptions\ValidationException::class);
        $this->resolver()->find($owner, self::WIDGET_ID);
    }

    public function testPostCarouselRendersSlidesOfCards(): void
    {
        $listing = $this->listing('Slide');
        $owner = $this->host($this->settings(), 'elemacy-post-carousel');
        $this->request->visit(get_permalink($owner));

        $html = $this->renderWidget($owner);

        self::assertStringContainsString('swiper-slide', $html);
        self::assertStringContainsString('elemacy-post-card', $html);
        self::assertStringContainsString('data-elemacy-item="' . $listing . '"', $html);
    }

    public function testACarouselPageDoesNotShareThePaginationEndpointAttributes(): void
    {
        $this->listing('Slide');
        $owner = $this->host($this->settings(), 'elemacy-post-carousel');
        $this->request->visit(get_permalink($owner));

        self::assertStringNotContainsString('data-elemacy-loop-request', $this->renderWidget($owner));
    }

    public function testNothingFoundMessageForVisitors(): void
    {
        $owner = $this->host($this->settings(['nothing_found_message' => 'Nothing here yet']));
        $this->request->visit(get_permalink($owner));
        wp_set_current_user(0);

        self::assertStringContainsString('Nothing here yet', $this->renderWidget($owner));
    }

    public function testNothingFoundWithoutAMessageIsEmptyForVisitors(): void
    {
        $owner = $this->host($this->settings());
        $this->request->visit(get_permalink($owner));
        wp_set_current_user(0);

        self::assertSame('', trim($this->renderWidget($owner)));
    }

    public function testTheEditorExplainsAnEmptyList(): void
    {
        $owner = $this->host($this->settings());
        $this->request->visit(get_permalink($owner));
        Plugin::$instance->editor->set_edit_mode(true);

        try {
            $html = $this->renderWidget($owner);
        } finally {
            Plugin::$instance->editor->set_edit_mode(false);
        }

        self::assertStringContainsString('elemacy-alert-info', $html);
    }

    public function testASavedTermSourceIsRefusedOnAPostWidget(): void
    {
        $owner = $this->host($this->settings(['data_source' => 'terms']));
        $this->request->visit(get_permalink($owner));
        wp_set_current_user(0);

        self::assertSame('', trim($this->renderWidget($owner)));
    }

    public function testAWidgetNestedTooDeepRendersNothingForVisitors(): void
    {
        $listing = $this->listing('Deep');
        $owner = $this->host($this->settings());
        $this->request->visit(get_permalink($owner));
        wp_set_current_user(0);

        for ($i = 0; $i < LoopLimits::MAX_DEPTH; $i++) {
            LoopContext::push(new PostLoopItem(get_post($listing)));
        }

        try {
            self::assertSame('', trim($this->renderWidget($owner)));
        } finally {
            for ($i = 0; $i < LoopLimits::MAX_DEPTH; $i++) {
                LoopContext::pop();
            }
        }
    }

    /** @return string[] */
    private function offered_sources(string $widget): array
    {
        $method = new \ReflectionMethod(Plugin::instance()->widgets_manager->get_widget_types($widget), 'available_sources');
        $method->setAccessible(true);

        return array_keys($method->invoke(Plugin::instance()->widgets_manager->get_widget_types($widget)));
    }

    private function settings(array $extra = []): array
    {
        return $extra + ['data_source' => 'posts', 'post_type' => self::TYPE, 'exclude_current_post' => ''];
    }

    /**
     * Posts of the built-in type in their own category. The endpoint runs in a
     * fresh process, which cannot see a post type this test registered.
     *
     * @return array<string, mixed> Query settings selecting exactly these posts.
     */
    private function stored_posts(int $count, string $title): array
    {
        $term = wp_insert_term($title . ' ' . bin2hex(random_bytes(3)), 'category');
        self::assertIsArray($term);
        $this->terms[] = (int) $term['term_id'];

        foreach (range(1, $count) as $i) {
            $this->listing($title . ' ' . $i, [
                'post_type' => 'post', 'post_content' => 'Body ' . $i,
                'post_date' => sprintf('2024-01-%02d 10:00:00', $i), 'post_category' => [(int) $term['term_id']],
            ]);
        }

        return ['post_type' => 'post', 'posts_include_terms' => [(int) $term['term_id']]];
    }

    private function listing(string $title, array $post = []): int
    {
        $id = wp_insert_post($post + ['post_type' => self::TYPE, 'post_status' => 'publish', 'post_title' => $title], true);
        self::assertIsInt($id);
        $this->posts[] = $id;

        return $id;
    }

    private function host(array $settings, string $widget = 'elemacy-post-grid'): int
    {
        $page = Plugin::instance()->documents->create('wp-page', [
            'post_type' => 'page', 'post_title' => 'Post widgets host ' . bin2hex(random_bytes(3)), 'post_status' => 'draft',
        ]);
        if (is_wp_error($page)) {
            throw new \RuntimeException($page->get_error_message());
        }
        $this->posts[] = $page->get_main_id();
        $elements = [[
            'id' => 'eac9000', 'elType' => 'container', 'settings' => [],
            'elements' => [['id' => self::WIDGET_ID, 'elType' => 'widget', 'widgetType' => $widget, 'settings' => $settings, 'elements' => []]],
        ]];
        if (!$page->save(['elements' => $elements, 'settings' => ['post_status' => 'publish']])) {
            throw new \RuntimeException('Could not save the host page.');
        }

        return $page->get_main_id();
    }

    private function renderWidget(int $owner): string
    {
        $document = Plugin::$instance->documents->get_doc_for_frontend($owner);
        self::assertNotNull($document);
        Plugin::$instance->documents->switch_to_document($document);

        try {
            $raw = $document->get_elements_data()[0]['elements'][0];
            $widget = Plugin::$instance->elements_manager->create_element_instance($raw);
            $method = new \ReflectionMethod($widget, 'render');
            $method->setAccessible(true);
            ob_start();
            try {
                $method->invoke($widget);
            } finally {
                $html = (string) ob_get_clean();
            }
        } finally {
            Plugin::$instance->documents->restore_document();
        }

        return $html;
    }

    /** @return array<string, string> Each selector of the stylesheet with its declarations. */
    private function cssRules(string $css): array
    {
        $rules = [];
        preg_match_all('/([^{}@]+)\{([^{}]*)\}/', $css, $found, PREG_SET_ORDER);

        foreach ($found as [, $selectors, $declarations]) {
            foreach (explode(',', $selectors) as $selector) {
                $key = trim($selector);
                $rules[$key] = ($rules[$key] ?? '') . $declarations;
            }
        }

        return $rules;
    }

    /** @return string[] The selectors setting a property to a value. */
    private function selectorsSetting(array $rules, string $property, string $value): array
    {
        return array_keys(array_filter($rules, static function (string $declarations) use ($property, $value): bool {
            return 1 === preg_match('/(?:^|;)\s*' . preg_quote($property, '/') . ':\s*' . preg_quote($value, '/') . '\s*(?:;|$)/i', $declarations);
        }));
    }

    /** Negative when $a is less specific than $b, 0 when equal, positive when more. */
    private function compareSpecificity(string $a, string $b): int
    {
        return $this->specificity($a) <=> $this->specificity($b);
    }

    /** @return int[] Ids, classes with attributes and pseudo-classes, then types with pseudo-elements. */
    private function specificity(string $selector): array
    {
        $ids = preg_match_all('/#[\w-]+/', $selector);
        $classes = preg_match_all('/\.[\w-]+|\[[^\]]*\]|(?<!:):(?!:)[\w-]+/', $selector);
        $types = preg_match_all('/(?:^|[\s>+~])[a-z][a-z0-9]*|::[\w-]+/i', $selector);

        return [(int) $ids, (int) $classes, (int) $types];
    }

    private function css(int $owner): string
    {
        Plugin::instance()->documents->get($owner, false);
        $css = \Elementor\Core\Files\CSS\Post::create($owner);
        $css->update();

        return (string) $css->get_content();
    }

    private function resolver(): PublishedLoopDefinitionResolver
    {
        return new PublishedLoopDefinitionResolver(new PublicRenderPolicy(TypeRegistry::instance(), LoopDataSourceRegistry::instance()));
    }

    /** @return array{items: string, pagination: string, next_page: int} */
    private function endpointPage(int $owner, int $page): array
    {
        $response = (array) json_decode($this->endpointResponse($owner, $page), true);
        self::assertTrue($response['success'] ?? false, 'Page ' . $page . ' must succeed: ' . wp_json_encode($response));

        return $response['data'];
    }

    /** @return string The pagination endpoint's raw JSON answer for a page. */
    private function endpointResponse(int $owner, int $page): string
    {
        $definition = $this->resolver()->find($owner, self::WIDGET_ID);
        $placement = PlacementResolverRegistry::instance()->resolve_current($owner);
        self::assertNotNull($placement, 'The host page must resolve as the current placement.');
        $context = (new PaginationContextSigner())->sign($owner, self::WIDGET_ID, $definition->fingerprint(), $placement['type'], $placement['facts']);

        return NativeAjaxRequest::loop(wp_json_encode([
            'version' => 1, 'owner_id' => $owner, 'widget_id' => self::WIDGET_ID,
            'page' => $page, 'fingerprint' => $definition->fingerprint(), 'context' => $context,
        ]), '');
    }

    /** @return int[] The listed posts, in the order shown. */
    private function itemIds(string $html): array
    {
        preg_match_all('#data-elemacy-item="(\d+)"#', $html, $found);

        return array_map('intval', $found[1]);
    }

    /** @return string[] Each item's markup, without its per-render style scope. */
    private function itemHtml(string $html): array
    {
        preg_match_all('#<div class="elemacy-loop-item [^"]+" data-elemacy-item="\d+">.*?</article></div>#s', $html, $items);

        return array_map(
            static fn (string $item): string => (string) preg_replace('#elemacy-loop-item-[0-9]+-[0-9a-f]+#', 'elemacy-loop-item-X', $item),
            $items[0]
        );
    }
}
