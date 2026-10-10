<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Modules\DynamicTags\Services\TagManager;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Dynamic tag registration (openspec dynamic-tags/registration): the tags and
 * groups free offers, WooCommerce tags only while WooCommerce is active, the
 * public Hooks::DYNAMIC_TAGS_REGISTER_ACTION for add-ons, and nothing at all
 * while the module is disabled. Each scenario builds Elementor's real tag list
 * in a fresh process (fixtures/dynamic-tags-request.php), since Elementor
 * builds it once per process.
 */
final class DynamicTagsRegistrationTest extends IntegrationTestCase
{
    /** Tag name => class under Elemacy\Modules\DynamicTags\Tags\, in registration order. */
    private const BUILT_IN = [
        'elemacy-post-title' => 'Base\PostTitle',
        'elemacy-post-content' => 'Base\PostContent',
        'post-excerpt' => 'Base\PostExcerpt',
        'elemacy-post-date' => 'Base\PostDate',
        'elemacy-post-custom-field' => 'Base\PostCustomField',
        'post-url' => 'Base\PostUrl',
        'elemacy-post-featured-image' => 'Base\PostFeaturedImage',
        'elemacy-post-terms' => 'Base\PostTerms',
        'elemacy-comments-number' => 'Base\CommentsNumber',
        'elemacy-page-title' => 'Base\PageTitle',
        'elemacy-site-logo' => 'Base\SiteLogo',
        'elemacy-site-tagline' => 'Base\SiteTagline',
        'elemacy-site-title' => 'Base\SiteTitle',
        'elemacy-site-url' => 'Base\SiteUrl',
        'elemacy-archive-title' => 'Archive\ArchiveTitle',
        'elemacy-archive-description' => 'Archive\ArchiveDescription',
        'elemacy-archive-url' => 'Archive\ArchiveUrl',
        'elemacy-term-name' => 'Taxonomy\TermName',
        'elemacy-term-description' => 'Taxonomy\TermDescription',
        'elemacy-term-url' => 'Taxonomy\TermUrl',
        'elemacy-term-count' => 'Taxonomy\TermCount',
        'elemacy-term-custom-field' => 'Taxonomy\TermCustomField',
        'elemacy-author-name' => 'Author\AuthorName',
        'elemacy-author-info' => 'Author\AuthorInfo',
        'elemacy-author-url' => 'Author\AuthorUrl',
        'elemacy-author-profile-picture' => 'Author\AuthorProfilePicture',
        'elemacy-user-name' => 'User\UserName',
        'elemacy-user-description' => 'User\UserDescription',
        'elemacy-user-avatar' => 'User\UserAvatar',
        'elemacy-user-url' => 'User\UserUrl',
        'elemacy-user-login' => 'User\UserLogin',
        'elemacy-user-email' => 'User\UserEmail',
        'elemacy-user-post-count' => 'User\UserPostCount',
        'elemacy-shortcode' => 'Utility\Shortcode',
        'elemacy-request-parameter' => 'Utility\RequestParameter',
        'elemacy-current-date-time' => 'Utility\CurrentDateTime',
        'elemacy-contact-url' => 'Utility\ContactUrl',
        'elemacy-acf-field' => 'Acf\AcfField',
        'elemacy-acf-color' => 'Acf\AcfColor',
        'elemacy-acf-date-time' => 'Acf\AcfDateTime',
        'elemacy-acf-gallery' => 'Acf\AcfGallery',
        'elemacy-acf-image' => 'Acf\AcfImage',
        'elemacy-acf-number' => 'Acf\AcfNumber',
        'elemacy-acf-url' => 'Acf\AcfUrl',
    ];

    private const WOOCOMMERCE = [
        'elemacy-product-price' => 'WooCommerce\ProductPrice',
        'elemacy-product-sku' => 'WooCommerce\ProductSku',
        'elemacy-product-stock' => 'WooCommerce\ProductStock',
        'elemacy-product-rating' => 'WooCommerce\ProductRating',
        'elemacy-product-short-description' => 'WooCommerce\ProductShortDescription',
        'elemacy-product-gallery' => 'WooCommerce\ProductGallery',
        'elemacy-product-add-to-cart-url' => 'WooCommerce\ProductAddToCartUrl',
    ];

    public function testEveryBuiltInTagIsOfferedUnderTheElemacyGroup(): void
    {
        $result = $this->build_tags(['woocommerce' => false]);

        self::assertSame(['elemacy' => 'Elemacy'], $result['groups']);
        self::assertSame(self::classes(self::BUILT_IN), $result['tags']);
    }

    public function testWooCommerceTagsAndGroupAreOfferedWhileWooCommerceIsActive(): void
    {
        $result = $this->build_tags(['woocommerce' => true]);

        self::assertSame(['elemacy' => 'Elemacy', 'elemacy-woocommerce' => 'Elemacy WooCommerce'], $result['groups']);
        self::assertSame(self::classes(self::BUILT_IN + self::WOOCOMMERCE), $result['tags']);
    }

    public function testNoWooCommerceTagIsRegisteredWhileWooCommerceIsInactive(): void
    {
        $result = $this->build_tags(['woocommerce' => false]);

        self::assertArrayNotHasKey('elemacy-woocommerce', $result['groups']);
        self::assertSame([], array_intersect_key($result['tags'], self::WOOCOMMERCE));
    }

    public function testAnExtensionAddsATagThroughTheRegisterActionAfterTheBuiltInTags(): void
    {
        $result = $this->build_tags(['extension' => true]);

        self::assertSame('Elemacy_Fixture_Extension_Tag', $result['tags']['elemacy-fixture-extension-tag'] ?? null);
        self::assertSame('elemacy-fixture-extension-tag', array_key_last($result['tags']), 'The action fires after every built-in tag is registered.');
        self::assertSame(1, $result['fired'], 'Building the tag list twice in one request fires the action once.');
    }

    public function testAnExtensionTagWithABuiltInNameReplacesIt(): void
    {
        $result = $this->build_tags(['extension' => true]);

        self::assertSame('Elemacy_Fixture_Post_Title_Override', $result['tags']['elemacy-post-title'] ?? null);
    }

    public function testADisabledModuleOffersNoTagsAndFiresNoAction(): void
    {
        $result = $this->build_tags(['modules' => ['theme-builder', 'widgets', 'custom-css', 'popups'], 'extension' => true]);

        self::assertSame([], $result['groups']);
        self::assertSame([], $result['tags']);
        self::assertSame(0, $result['fired']);
    }

    public function testSettingUpTheHooksTwiceAttachesTheRegistrationOnce(): void
    {
        $manager = new TagManager();
        $manager->register_hooks();
        $manager->register_hooks();

        try {
            $attached = 0;
            foreach ($GLOBALS['wp_filter']['elementor/dynamic_tags/register']->callbacks as $callbacks) {
                foreach ($callbacks as $callback) {
                    $attached += $callback['function'] === [$manager, 'register_tags'] ? 1 : 0;
                }
            }

            self::assertSame(1, $attached);
        } finally {
            remove_action('elementor/dynamic_tags/register', [$manager, 'register_tags']);
        }
    }

    /**
     * @param array{woocommerce?: bool, modules?: string[]|null, extension?: bool} $input
     * @return array{groups: array<string, string>, tags: array<string, string>, fired: int}
     */
    private function build_tags(array $input): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/dynamic-tags-request.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fwrite($pipes[0], wp_json_encode($input + ['woocommerce' => false, 'modules' => null, 'extension' => false]));
        fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);

        $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        // An empty PHP map encodes as a JSON list.
        $result['groups'] = (array) $result['groups'];
        $result['tags'] = (array) $result['tags'];

        return $result;
    }

    /**
     * @param array<string, string> $tags
     * @return array<string, string>
     */
    private static function classes(array $tags): array
    {
        return array_map(static fn(string $class) => 'Elemacy\\Modules\\DynamicTags\\Tags\\' . $class, $tags);
    }
}
