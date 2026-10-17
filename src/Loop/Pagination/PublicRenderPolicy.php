<?php

namespace Elemacy\Loop\Pagination;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Contracts\PageUrlPaginatedSourceInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\Loop\Pagination\PublicLoopLimits;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TypeRegistry;
use WP_Post;

/** Shared native document eligibility and explicit source-policy enforcement. */
final class PublicRenderPolicy
{
    public const UNAVAILABLE = 'elemacy_loop_unavailable';

    /** @var TypeRegistry */
    private TypeRegistry $types;
    /** @var LoopDataSourceRegistry */
    private LoopDataSourceRegistry $sources;
    /** @var PlacementResolverRegistry */
    private PlacementResolverRegistry $placements;

    /**
     * @param TypeRegistry                   $types Populated library types.
     * @param LoopDataSourceRegistry         $sources Currently available sources.
     * @param PlacementResolverRegistry|null $placements Defaults to the shared
     *                                       instance — every existing caller
     *                                       (LoopGrid included) constructs this
     *                                       class with only the first two args.
     */
    public function __construct(TypeRegistry $types, LoopDataSourceRegistry $sources, ?PlacementResolverRegistry $placements = null)
    {
        $this->types = $types;
        $this->sources = $sources;
        $this->placements = $placements ?? PlacementResolverRegistry::instance();
    }

    /**
     * @param int $id Authoring document ID.
     * @return WP_Post
     * @throws ValidationException If native access or module ownership is unavailable.
     */
    public function assert_owner(int $id): WP_Post
    {
        $owner = $this->public_post($id);
        if ('builder' !== get_post_meta($id, '_elementor_edit_mode', true)) {
            throw self::unavailable();
        }
        if (LibraryPostType::POST_TYPE === $owner->post_type) {
            if (!$this->types->get((string) get_post_meta($id, MetaKeys::TEMPLATE_TYPE, true))) {
                throw self::unavailable();
            }
        } elseif (!is_post_publicly_viewable($owner)) {
            throw self::unavailable();
        }
        return $owner;
    }

    /**
     * @param int $id Referenced loop template ID.
     * @return WP_Post
     * @throws ValidationException If the template cannot render publicly.
     */
    public function assert_template(int $id): WP_Post
    {
        $template = $this->public_post($id);
        $loop_type = $this->types->get('loop');
        if (
            !$loop_type || 'block' !== $loop_type->group
            || LibraryPostType::POST_TYPE !== $template->post_type
            || 'loop' !== get_post_meta($id, MetaKeys::TEMPLATE_TYPE, true)
            || 'elemacy_loop' !== get_post_meta($id, '_elementor_template_type', true)
            || 'builder' !== get_post_meta($id, '_elementor_edit_mode', true)
        ) {
            throw self::unavailable();
        }
        return $template;
    }

    /**
     * @param string $key Saved source identifier.
     * @return LoopDataSourceInterface Available source with a public policy.
     * @throws ValidationException If missing or lacking a safe public capability.
     */
    public function source(string $key): LoopDataSourceInterface
    {
        $source = $this->sources->get($key);
        if (!$source || !$source instanceof PublicLoopSourcePolicyInterface) {
            throw self::unavailable();
        }
        return $source;
    }

    /**
     * The source an endpoint pagination request may run. A source paginated by
     * the page's own URLs is never served by the endpoint: it has no saved
     * query to replay, and the endpoint has no main query to read.
     *
     * @param string $key Saved source identifier.
     * @return LoopDataSourceInterface Available source with a public policy.
     * @throws ValidationException If unavailable or paginated by page URL.
     */
    public function endpoint_source(string $key): LoopDataSourceInterface
    {
        $source = $this->source($key);
        if ($source instanceof PageUrlPaginatedSourceInterface) {
            throw self::unavailable();
        }
        return $source;
    }

    /**
     * Prepare a public query without executing it.
     *
     * @param LoopDataSourceInterface $source Previously resolved source.
     * @param array                   $settings Saved settings, never request settings.
     * @param array                   $context Independently authorized host context.
     * @param int                     $page Validated page number.
     * @return array
     * @throws ValidationException On unavailable or unsafe query context.
     */
    public function settings(LoopDataSourceInterface $source, array $settings, array $context, int $page): array
    {
        PublicLoopLimits::assert_page($page);
        if ($this->source($source->get_key()) !== $source) {
            throw self::unavailable();
        }
        return $source->get_public_settings($settings, $context, $page);
    }

    /**
     * The public-facing equivalent of $source->get_items($settings) for a
     * Grid/Carousel widget's own classic, non-AJAX render: applies the
     * identical template and source eligibility policy the AJAX endpoint
     * enforces, so a rejected private template/source cannot appear on the
     * first page load either.
     *
     * @param array $settings Raw author settings (get_settings_for_display()).
     * @param int   $owner_id The document currently rendering this widget.
     * @param int   $page     The page of the loop to render: 1 unless the
     *                        visitor opened a later paginated URL of a paginated loop.
     * @return LoopResultDTO
     * @throws ValidationException If the template or referenced source context is unavailable.
     */
    public function eligible_items(array $settings, int $owner_id, int $page = 1): LoopResultDTO
    {
        $this->assert_template((int) ($settings['template_id'] ?? 0));
        $source = $this->source((string) ($settings['data_source'] ?? 'posts'));
        $placement = $this->placements->resolve_current($owner_id);
        $item_settings = $this->settings($source, $settings, $placement['facts'] ?? [], $page);

        return $source->get_items($item_settings);
    }

    /**
     * An editor-facing sentence explaining why eligible_items refused
     * $settings. Only ever shown to a user who can edit the owning document;
     * visitors get nothing.
     *
     * @param array $settings Raw author settings.
     * @return string
     */
    public function describe_ineligibility(array $settings): string
    {
        $template = get_post((int) ($settings['template_id'] ?? 0));

        if (!$template instanceof WP_Post) {
            return __('The selected Loop Item template no longer exists.', 'elemacy');
        }
        if ('publish' !== $template->post_status) {
            /* translators: %s: template title */
            return sprintf(__('The Loop Item template "%s" is not published, so visitors cannot see this loop.', 'elemacy'), $template->post_title);
        }
        if (post_password_required($template)) {
            return __('The Loop Item template is password protected, so visitors cannot see this loop.', 'elemacy');
        }

        try {
            $this->assert_template($template->ID);
        } catch (ValidationException $error) {
            return __('The selected template is not a Loop Item template.', 'elemacy');
        }

        $key = (string) ($settings['data_source'] ?? 'posts');
        $source = $this->sources->get($key);

        if (!$source) {
            /* translators: %s: data source key */
            return sprintf(__('The "%s" data source is not available.', 'elemacy'), $key);
        }
        if (!$source instanceof PublicLoopSourcePolicyInterface) {
            /* translators: %s: data source key */
            return sprintf(__('The "%s" data source cannot be shown to visitors.', 'elemacy'), $key);
        }

        return __('This loop cannot be shown to visitors with its current settings.', 'elemacy');
    }

    /**
     * @param int $id Native post ID; zero cannot fall back to the global post.
     * @return WP_Post
     * @throws ValidationException If the document is not public/native-accessible.
     */
    private function public_post(int $id): WP_Post
    {
        $post = $id > 0 ? get_post($id) : null;
        if (!$post instanceof WP_Post || 'publish' !== $post->post_status || post_password_required($post)) {
            throw self::unavailable();
        }
        return $post;
    }

    /** @return ValidationException Client-safe unavailable-definition error. */
    public static function unavailable(): ValidationException
    {
        return ValidationException::with_errors(
            ['code' => self::UNAVAILABLE],
            esc_html__('This list is not available.', 'elemacy')
        );
    }
}
