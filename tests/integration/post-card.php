<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Container;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\Rendering\ItemPlacement;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardDefinition;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardPresenter;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * The post card's markup for real posts of a custom post type, rendered through
 * the presenter both the first page and later pages use.
 */
final class PostCardTest extends IntegrationTestCase
{
    private const TYPE = 'fx_card';
    private const TAX = 'fx_card_genre';

    /** @var int[] */
    private array $posts = [];
    /** @var int[] */
    private array $attachments = [];
    /** @var int[] */
    private array $terms = [];

    protected function setUp(): void
    {
        parent::setUp();
        register_post_type(self::TYPE, ['public' => true, 'label' => 'Fx Cards', 'supports' => ['title', 'editor', 'excerpt', 'thumbnail']]);
        register_taxonomy(self::TAX, self::TYPE, ['public' => true, 'label' => 'Fx Genres']);
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->posts as $post) {
                wp_delete_post($post, true);
            }
            foreach ($this->attachments as $attachment) {
                wp_delete_attachment($attachment, true);
            }
            foreach ($this->terms as $term) {
                wp_delete_term($term, self::TAX);
            }
            unregister_taxonomy(self::TAX);
            unregister_post_type(self::TYPE);
        } finally {
            parent::tearDown();
        }
    }

    public function testASavedWidgetWithNoPartsUsesTheDefaultCard(): void
    {
        $html = $this->card($this->post('Default', ['post_type' => 'post', 'post_content' => 'Some body text.']), []);

        self::assertStringContainsString('elemacy-post-card__part--title', $html);
        self::assertStringContainsString('elemacy-post-card__part--excerpt', $html);
        self::assertStringContainsString('elemacy-post-card__part--button', $html);
        self::assertStringContainsString('elemacy-post-card__group--meta', $html);
    }

    public function testUnknownPartsAndMalformedRowsAreDropped(): void
    {
        $definition = new PostCardDefinition([PostCardDefinition::PARTS => [
            ['part' => 'not-a-part'],
            ['part' => 'divider', 'divider_style' => 'space'],
            ['part' => 'custom_text', 'custom_text_content' => 'Hello {title}'],
            'not-a-row',
            ['part' => 'title', 'zone' => 'nowhere'],
        ]]);

        self::assertCount(1, $definition->rows());
        self::assertSame('title', $definition->rows()[0]['part']);
        self::assertSame(PostCardDefinition::ZONE_BODY, $definition->rows()[0]['zone'], 'An unknown zone falls back to the body.');
        self::assertSame('h3', $definition->rows()[0]['title_tag'], 'A saved row missing a value gets its default.');
    }

    public function testMalformedPartsAreRefusedPublicly(): void
    {
        $this->expectException(ValidationException::class);

        $this->presenter()->assert_public([PostCardDefinition::PARTS => 'broken']);
    }

    public function testPartsRenderInTheirSavedOrder(): void
    {
        $html = $this->card($this->post('Ordered'), [['part' => 'meta', 'meta_item' => 'date'], ['part' => 'title']]);

        self::assertLessThan(strpos($html, 'elemacy-post-card__part--title'), strpos($html, 'elemacy-post-card__group--meta'));
    }

    public function testAPartCanRepeatWithItsOwnSettings(): void
    {
        $id = $this->post('Repeat', [], ['first' => 'One', 'second' => 'Two']);
        $html = $this->card($id, [
            ['part' => 'custom_field', 'custom_field_key' => 'first'],
            ['part' => 'custom_field', 'custom_field_key' => 'second'],
        ]);

        self::assertStringContainsString('One', $html);
        self::assertStringContainsString('Two', $html);
    }

    public function testACardWithNothingInItsFooterHasNoFooter(): void
    {
        $id = $this->post('No footer');
        $html = $this->card($id, [['part' => 'title'], ['part' => 'custom_field', 'zone' => 'footer', 'custom_field_key' => 'absent']]);

        self::assertStringNotContainsString('elemacy-post-card__zone--footer', $html);
        self::assertStringNotContainsString('elemacy-post-card__part--custom_field', $html);
    }

    public function testAnOverlayPartOpensTheBodyWhenThereIsNoImage(): void
    {
        $term = $this->term('Jazz');
        $id = $this->post('Overlay');
        wp_set_object_terms($id, [$term], self::TAX);
        $html = $this->card($id, [['part' => 'title'], ['part' => 'terms', 'zone' => 'media_top']]);

        self::assertStringNotContainsString('elemacy-post-card__media', $html);
        self::assertLessThan(strpos($html, 'elemacy-post-card__part--title'), strpos($html, 'elemacy-post-card__part--terms'));
    }

    public function testAnOverlayPartSitsOnTheImage(): void
    {
        $term = $this->term('Jazz');
        $id = $this->post('Overlay');
        wp_set_object_terms($id, [$term], self::TAX);
        set_post_thumbnail($id, $this->attachment());
        $html = $this->card($id, [['part' => 'image'], ['part' => 'terms', 'zone' => 'media_top']]);

        self::assertMatchesRegularExpression('#elemacy-post-card__media.*elemacy-post-card__zone--media-top.*elemacy-post-card__part--terms#s', $html);
    }

    public function testFeaturedImageRendersResponsiveSourcesAndDimensions(): void
    {
        $id = $this->post('Image');
        set_post_thumbnail($id, $this->attachment());
        $html = $this->card($id, [['part' => 'image', 'image_size' => 'full']]);

        self::assertMatchesRegularExpression('#<img[^>]+width="800"[^>]+height="600"#', $html);
        self::assertStringContainsString('srcset=', $html);
        self::assertStringContainsString('alt="Fixture alt"', $html);
        self::assertStringContainsString('loading="lazy"', $html);
    }

    public function testFallbackAndNoImage(): void
    {
        $id = $this->post('No image');
        $fallback = $this->attachment();

        self::assertStringContainsString('<img', $this->card($id, [['part' => 'image', 'image_fallback' => ['id' => $fallback, 'url' => '']]]));
        self::assertStringNotContainsString('elemacy-post-card__media', $this->card($id, [['part' => 'title'], ['part' => 'image']]));
    }

    public function testEagerCountOnlyAppliesToTheFirstImagesOfPageOne(): void
    {
        $id = $this->post('Eager');
        set_post_thumbnail($id, $this->attachment());
        $row = [['part' => 'image', 'image_eager_count' => 1]];

        $first = $this->card($id, $row, [], 0, 1);
        self::assertStringContainsString('loading="eager"', $first);
        self::assertStringContainsString('fetchpriority="high"', $first);
        self::assertStringContainsString('loading="lazy"', $this->card($id, $row, [], 1, 1));
        self::assertStringContainsString('loading="lazy"', $this->card($id, $row, [], 0, 2));
    }

    public function testTitleTagAndUnlinkedTitle(): void
    {
        $id = $this->post('A title');

        self::assertMatchesRegularExpression('#<h2 class="elemacy-post-card__title"><a [^>]*href="[^"]+"[^>]*>A title</a></h2>#', $this->card($id, [['part' => 'title', 'title_tag' => 'h2']]));
        self::assertStringNotContainsString('<a ', $this->card($id, [['part' => 'title', 'title_link' => '']]));
    }

    public function testAnUnsafeTitleTagFallsBack(): void
    {
        self::assertStringContainsString('<h3 class="elemacy-post-card__title">', $this->card($this->post('T'), [['part' => 'title', 'title_tag' => 'script']]));
    }

    public function testGeneratedExcerptIsPlainTextUpToTheLength(): void
    {
        add_shortcode('fx_card_code', static fn (): string => 'expanded');

        try {
            $id = $this->post('Generated', ['post_content' => 'one two three [fx_card_code id="1"] four five <!-- wp:paragraph --><p>six</p><!-- /wp:paragraph -->']);
            $html = $this->card($id, [['part' => 'excerpt', 'excerpt_length' => 4]]);
        } finally {
            remove_shortcode('fx_card_code');
        }

        self::assertTrue(!shortcode_exists('fx_card_code'), 'The fixture shortcode is removed again.');
        self::assertStringContainsString('one two three four…', $html);
        self::assertStringNotContainsString('[fx_card_code', $html);
        self::assertStringNotContainsString('expanded', $html);
        self::assertStringNotContainsString('wp:paragraph', $html);
    }

    public function testExcerptByCharacters(): void
    {
        $html = $this->card($this->post('Chars', ['post_content' => 'abcdefghij']), [['part' => 'excerpt', 'excerpt_length' => 4, 'excerpt_unit' => 'characters']]);

        self::assertStringContainsString('abcd…', $html);
    }

    public function testManualExcerptStaysWholeUnlessAsked(): void
    {
        $id = $this->post('Manual', ['post_excerpt' => 'one two three four five']);

        self::assertStringContainsString('one two three four five', $this->card($id, [['part' => 'excerpt', 'excerpt_length' => 2]]));
        self::assertStringContainsString('one two…', $this->card($id, [['part' => 'excerpt', 'excerpt_length' => 2, 'excerpt_trim_manual' => 'yes']]));
    }

    public function testAnElementorBuiltPostGivesAPlainExcerpt(): void
    {
        $filtered = 0;
        $count = static function ($content) use (&$filtered) {
            $filtered++;
            return $content;
        };
        add_filter('the_content', $count);

        try {
            $id = $this->post('Built', ['post_content' => '<style>.x{color:red}</style><div class="elementor">Readable words</div>']);
            update_post_meta($id, '_elementor_edit_mode', 'builder');
            $html = $this->card($id, [['part' => 'excerpt']]);
        } finally {
            remove_filter('the_content', $count);
        }

        self::assertStringContainsString('Readable words', $html);
        self::assertStringNotContainsString('color:red', $html);
        self::assertSame(0, $filtered, 'A card never runs the_content.');
    }

    public function testAPostWithNoTextHasNoExcerpt(): void
    {
        self::assertStringNotContainsString('elemacy-post-card__part--excerpt', $this->card($this->post('Empty'), [['part' => 'title'], ['part' => 'excerpt']]));
    }

    public function testAdjacentMetaRowsShareOneLineWithSeparators(): void
    {
        $html = $this->card($this->post('Meta', ['post_type' => 'post']), [
            ['part' => 'meta', 'meta_item' => 'date'],
            ['part' => 'meta', 'meta_item' => 'comments', 'meta_separator' => '/'],
        ]);

        self::assertSame(1, substr_count($html, 'elemacy-post-card__group--meta'));
        self::assertMatchesRegularExpression('#<time datetime="\d{4}-\d{2}-\d{2}T[^"]+">[^<]+</time>#', $html);
        self::assertStringContainsString('aria-hidden="true">/</span>', $html);
        self::assertStringContainsString('0 comments', $html);
    }

    public function testAMetaItemTheTypeDoesNotSupportTakesItsSeparatorWithIt(): void
    {
        // The fixture type supports neither comments nor authors.
        $html = $this->card($this->post('Type'), [
            ['part' => 'meta', 'meta_item' => 'date'],
            ['part' => 'meta', 'meta_item' => 'comments'],
            ['part' => 'meta', 'meta_item' => 'author'],
        ]);

        self::assertStringNotContainsString('comment', $html);
        self::assertStringNotContainsString('elemacy-post-card__separator', $html);
        self::assertStringContainsString('<time', $html);
    }

    public function testReadingTime(): void
    {
        $html = $this->card($this->post('Long', ['post_content' => str_repeat('word ', 450)]), [['part' => 'meta', 'meta_item' => 'reading_time']]);

        self::assertStringContainsString('3 min read', $html);
    }

    public function testAutomaticTaxonomyAndTermLimit(): void
    {
        $first = $this->term('Blues');
        $second = $this->term('Rock');
        $id = $this->post('Terms');
        wp_set_object_terms($id, [$first, $second], self::TAX);

        $html = $this->card($id, [['part' => 'terms', 'terms_limit' => 1]]);

        self::assertSame(1, substr_count($html, 'elemacy-post-card__term"') + substr_count($html, 'elemacy-post-card__term '));
        self::assertMatchesRegularExpression('#<a class="elemacy-post-card__term" href="[^"]+">(Blues|Rock)#', $html);
    }

    public function testAuthorPartIsOmittedForATypeWithoutAuthors(): void
    {
        self::assertStringNotContainsString('elemacy-post-card__part--author', $this->card($this->post('No author'), [['part' => 'title'], ['part' => 'author']]));
    }

    public function testAuthorPartShowsThePostsOwnAuthor(): void
    {
        $html = $this->card($this->post('Author', ['post_type' => 'post']), [['part' => 'author']]);

        self::assertStringContainsString('elemacy-post-card__author-name', $html);
        self::assertStringContainsString('<img', $html);
    }

    public function testAuthorPartShowsOnlyAvatarAndName(): void
    {
        $id = $this->post('Author', ['post_type' => 'post', 'post_author' => get_current_user_id()]);
        $user = (int) get_post($id)->post_author;
        $before = get_user_meta($user, 'description', true);
        update_user_meta($user, 'description', 'Fixture biography');

        try {
            $html = $this->card($id, [['part' => 'author', 'author_bio' => 'yes']]);
        } finally {
            update_user_meta($user, 'description', $before);
        }

        self::assertStringContainsString('elemacy-post-card__author-name', $html);
        self::assertStringNotContainsString('Fixture biography', $html);
    }

    public function testButtonNamesThePostForScreenReaders(): void
    {
        $html = $this->card($this->post('Some Post'), [['part' => 'button', 'button_text' => 'Read']]);

        self::assertMatchesRegularExpression('#<a class="elemacy-post-card__button"[^>]*>Read<span class="elemacy-post-card__sr">: Some Post</span></a>#', $html);
    }

    public function testButtonTextIsPlainText(): void
    {
        $html = $this->card($this->post('Plain'), [['part' => 'button', 'button_text' => 'Open {title} <b>now</b>']]);

        self::assertStringContainsString('>Open {title} &lt;b&gt;now&lt;/b&gt;<span', $html);
    }

    public function testNewTabLinksAreSafe(): void
    {
        $html = $this->card($this->post('Tab'), [['part' => 'button']], [PostCardDefinition::NEW_TAB => 'yes']);

        self::assertStringContainsString('target="_blank" rel="noopener"', $html);
    }

    public function testCustomFieldReadsPostMetaWithBeforeAndAfter(): void
    {
        $html = $this->card($this->post('Price', [], ['price' => '42']), [['part' => 'custom_field', 'custom_field_key' => 'price', 'custom_field_before' => '$', 'custom_field_after' => ' USD']]);

        self::assertStringContainsString('$42 USD', $html);
    }

    public function testCustomFieldFallbackAndEmpty(): void
    {
        $id = $this->post('Empty field');

        self::assertStringContainsString('N/A', $this->card($id, [['part' => 'custom_field', 'custom_field_key' => 'absent', 'custom_field_fallback' => 'N/A']]));
        self::assertStringNotContainsString('elemacy-post-card__part--custom_field', $this->card($id, [['part' => 'title'], ['part' => 'custom_field', 'custom_field_key' => 'absent']]));
    }

    public function testAScriptInACustomFieldIsNeverOutput(): void
    {
        $html = $this->card($this->post('XSS', [], ['evil' => '<script>alert(1)</script>']), [['part' => 'custom_field', 'custom_field_key' => 'evil']]);

        self::assertStringNotContainsString('<script', $html);
    }

    public function testAProtectedKeyRendersNothing(): void
    {
        $id = $this->post('Secret', [], ['_secret' => 'hidden-value']);

        $html = $this->card($id, [['part' => 'title'], ['part' => 'custom_field', 'custom_field_key' => '_secret']]);

        self::assertStringContainsString('elemacy-post-card__part--title', $html);
        self::assertStringNotContainsString('hidden-value', $html);
        self::assertStringNotContainsString('elemacy-post-card__part--custom_field', $html);
    }

    public function testAPasswordProtectedPostWithholdsItsExcerptAndFields(): void
    {
        $id = $this->post('Locked', ['post_content' => 'Secret body', 'post_password' => 'pw'], ['price' => '42']);
        $html = $this->card($id, [['part' => 'title'], ['part' => 'excerpt'], ['part' => 'custom_field', 'custom_field_key' => 'price']]);

        self::assertStringNotContainsString('Secret body', $html);
        self::assertStringNotContainsString('42', $html);
    }

    public function testCustomFieldLinksAUrlValue(): void
    {
        $html = $this->card($this->post('Link', [], ['site' => 'https://example.com/a']), [['part' => 'custom_field', 'custom_field_key' => 'site', 'custom_field_link' => 'yes']]);

        self::assertStringContainsString('<a href="https://example.com/a"', $html);

        // Linked from the value alone: no host lookup, so a host that does not resolve here still links.
        $html = $this->card($this->post('Intranet', [], ['site' => 'http://intranet.invalid:8443/a']), [['part' => 'custom_field', 'custom_field_key' => 'site', 'custom_field_link' => 'yes']]);
        self::assertStringContainsString('<a href="http://intranet.invalid:8443/a"', $html);

        foreach (['javascript:alert(1)', 'example.com/a', 'mailto:a@example.com'] as $value) {
            $html = $this->card($this->post('Not a link', [], ['site' => $value]), [['part' => 'custom_field', 'custom_field_key' => 'site', 'custom_field_link' => 'yes']]);
            self::assertStringNotContainsString('<a href', $html, $value . ' is not linked.');
        }
    }

    public function testWholeCardLinkIsItsOwnPointerOnlyOverlayBesideALinkedTitleWithoutNestingAnchors(): void
    {
        $id = $this->post('Whole');
        set_post_thumbnail($id, $this->attachment());
        $html = $this->card($id, [['part' => 'image'], ['part' => 'title']], [PostCardDefinition::WHOLE_CARD_LINK => 'yes']);

        self::assertStringContainsString('elemacy-post-card--linked', $html);
        self::assertSame(1, substr_count($html, 'elemacy-post-card__link'), 'One primary link.');
        self::assertMatchesRegularExpression('#<a class="elemacy-post-card__overlay" href="[^"]+" tabindex="-1" aria-hidden="true"></a></article>#', $html);
        self::assertSame(2, substr_count($html, '<a '), 'The image gets no second link.');
        self::assertDoesNotMatchRegularExpression('#<a [^>]*>(?:(?!</a>).)*<a #s', $html);
    }

    public function testWholeCardLinkWithoutATitleStillLinksTheCard(): void
    {
        $html = $this->card($this->post('No title part', ['post_content' => 'Body']), [['part' => 'excerpt']], [PostCardDefinition::WHOLE_CARD_LINK => 'yes']);

        self::assertMatchesRegularExpression('#<a class="elemacy-post-card__overlay" href="[^"]+"><span class="elemacy-post-card__sr">No title part</span></a>#', $html);
    }

    public function testWholeCardLinkStillLinksAPostWithNoTitle(): void
    {
        $html = $this->card($this->post('', ['post_content' => 'Body']), [['part' => 'title'], ['part' => 'excerpt']], [PostCardDefinition::WHOLE_CARD_LINK => 'yes']);

        self::assertStringNotContainsString('elemacy-post-card__title', $html);
        self::assertMatchesRegularExpression('#<a class="elemacy-post-card__overlay" href="[^"]+"><span class="elemacy-post-card__sr">\(no title\)</span></a>#', $html, 'A Title row renders nothing for an untitled post, so the card links itself.');
    }

    public function testWholeCardLinkNeedsNoTitleRowOrOneHiddenOnADevice(): void
    {
        $id = $this->post('Hidden title', ['post_content' => 'Body']);
        $settings = [PostCardDefinition::WHOLE_CARD_LINK => 'yes'];

        foreach ([[['part' => 'excerpt']], [['part' => 'title', 'row_hide_on' => ['mobile']], ['part' => 'excerpt']]] as $rows) {
            $html = $this->card($id, $rows, $settings);

            self::assertMatchesRegularExpression('#<a class="elemacy-post-card__overlay" href="[^"]+"><span class="elemacy-post-card__sr">Hidden title</span></a>#', $html);
            self::assertStringNotContainsString('tabindex="-1"', $html);
        }
    }

    public function testACardWithNothingToShowRendersNoArticleAndNoItemWrapper(): void
    {
        $post = $this->post('', ['post_content' => 'Body']);

        ob_start();
        $rendered = $this->presenter()->render_item(
            new PostLoopItem(get_post($post)),
            [PostCardDefinition::PARTS => [['part' => 'title', 'zone' => 'body']], PostCardDefinition::WHOLE_CARD_LINK => 'yes'],
            new ItemPlacement('fixture-placement', 'posts', 0, '', 1)
        );

        self::assertFalse($rendered);
        self::assertSame('', (string) ob_get_clean());
    }

    public function testATermsSeparatorIsNotAnnounced(): void
    {
        $id = $this->post('Separated');
        wp_set_object_terms($id, [$this->term('Blues'), $this->term('Rock')], self::TAX);
        $html = $this->card($id, [['part' => 'terms', 'terms_separator' => '|']]);

        self::assertStringContainsString('<span class="elemacy-post-card__separator" aria-hidden="true">|</span>', $html);
    }

    public function testALinkedImageBesideALinkedTitleAddsNoKeyboardStop(): void
    {
        $id = $this->post('Keyboard');
        set_post_thumbnail($id, $this->attachment());

        self::assertMatchesRegularExpression('#<a href="[^"]+" tabindex="-1" aria-hidden="true">#', $this->card($id, [['part' => 'image'], ['part' => 'title']]));
        self::assertMatchesRegularExpression('#<a href="[^"]+" aria-label="Keyboard">#', $this->card($id, [['part' => 'image']]));
    }

    public function testACardIsAnArticleWithStableClasses(): void
    {
        $html = $this->card($this->post('Stable'), [['part' => 'title']]);

        self::assertMatchesRegularExpression('#<article class="elemacy-post-card elemacy-post-card--type-fx_card">#', $html);
    }

    public function testRowHidingAndRepeaterClasses(): void
    {
        $html = $this->card($this->post('Hide'), [['part' => 'title', '_id' => 'abc123', 'row_hide_on' => ['mobile']]]);

        self::assertStringContainsString('elementor-repeater-item-abc123', $html);
        self::assertStringContainsString('elementor-hidden-mobile', $html);
    }

    public function testACardRendersNoElementorDocument(): void
    {
        $renders = 0;
        $count = static function ($data) use (&$renders) {
            $renders++;
            return $data;
        };
        add_filter('elementor/frontend/builder_content_data', $count);

        try {
            $this->card($this->post('Cheap'), [['part' => 'title']]);
        } finally {
            remove_filter('elementor/frontend/builder_content_data', $count);
        }

        self::assertSame(0, $renders);
    }

    private function presenter(): PostCardPresenter
    {
        return Container::instance()->singleton(PostCardPresenter::class);
    }

    private function card(int $post_id, ?array $rows, array $settings = [], int $occurrence = 0, int $page = 1): string
    {
        if (null !== $rows && [] !== $rows) {
            $settings[PostCardDefinition::PARTS] = array_map(static fn (array $row): array => $row + ['zone' => 'body'], $rows);
        }

        ob_start();
        $rendered = $this->presenter()->render_item(new PostLoopItem(get_post($post_id)), $settings, new ItemPlacement('fixture-placement', 'posts', $occurrence, '', $page));
        $html = (string) ob_get_clean();

        self::assertTrue($rendered);

        return $html;
    }

    /** @param array<string, string> $meta */
    private function post(string $title, array $post = [], array $meta = []): int
    {
        $id = wp_insert_post($post + ['post_type' => self::TYPE, 'post_status' => 'publish', 'post_title' => $title], true);
        self::assertIsInt($id);
        $this->posts[] = $id;
        foreach ($meta as $key => $value) {
            update_post_meta($id, $key, $value);
        }

        return $id;
    }

    private function term(string $name): int
    {
        $term = wp_insert_term($name . ' ' . bin2hex(random_bytes(3)), self::TAX);
        self::assertIsArray($term);
        $this->terms[] = (int) $term['term_id'];

        return (int) $term['term_id'];
    }

    private function attachment(): int
    {
        $id = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => 'Fixture', 'post_status' => 'inherit'], 'fixture-card.jpg');
        self::assertIsInt($id);
        $this->attachments[] = $id;
        update_post_meta($id, '_wp_attachment_image_alt', 'Fixture alt');
        update_post_meta($id, '_wp_attached_file', 'fixture-card.jpg');
        wp_update_attachment_metadata($id, [
            'width' => 800, 'height' => 600, 'file' => 'fixture-card.jpg',
            'sizes' => ['medium' => ['file' => 'fixture-card-300x225.jpg', 'width' => 300, 'height' => 225, 'mime-type' => 'image/jpeg']],
        ]);

        return $id;
    }
}
