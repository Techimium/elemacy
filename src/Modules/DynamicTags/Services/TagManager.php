<?php
namespace Elemacy\Modules\DynamicTags\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Modules\DynamicTags\Tags\Acf\AcfColor;
use Elemacy\Modules\DynamicTags\Tags\Acf\AcfDateTime;
use Elemacy\Modules\DynamicTags\Tags\Acf\AcfField;
use Elemacy\Modules\DynamicTags\Tags\Acf\AcfGallery;
use Elemacy\Modules\DynamicTags\Tags\Acf\AcfImage;
use Elemacy\Modules\DynamicTags\Tags\Acf\AcfNumber;
use Elemacy\Modules\DynamicTags\Tags\Acf\AcfUrl;
use Elemacy\Modules\DynamicTags\Tags\Archive\ArchiveDescription;
use Elemacy\Modules\DynamicTags\Tags\Archive\ArchiveTitle;
use Elemacy\Modules\DynamicTags\Tags\Archive\ArchiveUrl;
use Elemacy\Modules\DynamicTags\Tags\Author\AuthorInfo;
use Elemacy\Modules\DynamicTags\Tags\Author\AuthorName;
use Elemacy\Modules\DynamicTags\Tags\Author\AuthorProfilePicture;
use Elemacy\Modules\DynamicTags\Tags\Author\AuthorUrl;
use Elemacy\Modules\DynamicTags\Tags\Base\CommentsNumber;
use Elemacy\Modules\DynamicTags\Tags\Base\PageTitle;
use Elemacy\Modules\DynamicTags\Tags\Base\PostFeaturedImage;
use Elemacy\Modules\DynamicTags\Tags\Base\PostContent;
use Elemacy\Modules\DynamicTags\Tags\Base\PostCustomField;
use Elemacy\Modules\DynamicTags\Tags\Base\PostDate;
use Elemacy\Modules\DynamicTags\Tags\Base\PostExcerpt;
use Elemacy\Modules\DynamicTags\Tags\Base\PostTerms;
use Elemacy\Modules\DynamicTags\Tags\Base\PostUrl;
use Elemacy\Modules\DynamicTags\Tags\Base\PostTitle;
use Elemacy\Modules\DynamicTags\Tags\Base\SiteLogo;
use Elemacy\Modules\DynamicTags\Tags\Base\SiteTagline;
use Elemacy\Modules\DynamicTags\Tags\Base\SiteTitle;
use Elemacy\Modules\DynamicTags\Tags\Base\SiteUrl;
use Elemacy\Modules\DynamicTags\Tags\Taxonomy\TermCount;
use Elemacy\Modules\DynamicTags\Tags\Taxonomy\TermCustomField;
use Elemacy\Modules\DynamicTags\Tags\Taxonomy\TermDescription;
use Elemacy\Modules\DynamicTags\Tags\Taxonomy\TermName;
use Elemacy\Modules\DynamicTags\Tags\Taxonomy\TermUrl;
use Elemacy\Modules\DynamicTags\Tags\Utility\ContactUrl;
use Elemacy\Modules\DynamicTags\Tags\Utility\CurrentDateTime;
use Elemacy\Modules\DynamicTags\Tags\Utility\RequestParameter;
use Elemacy\Modules\DynamicTags\Tags\Utility\Shortcode;
use Elemacy\Modules\DynamicTags\Tags\WooCommerce\ProductAddToCartUrl;
use Elemacy\Modules\DynamicTags\Tags\WooCommerce\ProductGallery;
use Elemacy\Modules\DynamicTags\Tags\WooCommerce\ProductPrice;
use Elemacy\Modules\DynamicTags\Tags\WooCommerce\ProductRating;
use Elemacy\Modules\DynamicTags\Tags\WooCommerce\ProductShortDescription;
use Elemacy\Modules\DynamicTags\Tags\WooCommerce\ProductSku;
use Elemacy\Modules\DynamicTags\Tags\WooCommerce\ProductStock;

class TagManager
{

    protected static $instance;

    public function __construct()
    {
        add_action('elementor/dynamic_tags/register', [$this, 'register_tags']);
    }

    public static function init()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function register_tags($tags_manager)
    {
        $this->register_groups($tags_manager);

        $tags_manager->register(new PostTitle());
        $tags_manager->register(new PostContent());
        $tags_manager->register(new PostExcerpt());
        $tags_manager->register(new PostDate());
        $tags_manager->register(new PostCustomField());
        $tags_manager->register(new PostUrl());
        $tags_manager->register(new PostFeaturedImage());
        $tags_manager->register(new PostTerms());
        $tags_manager->register(new CommentsNumber());
        $tags_manager->register(new PageTitle());

        $tags_manager->register(new SiteLogo());
        $tags_manager->register(new SiteTagline());
        $tags_manager->register(new SiteTitle());
        $tags_manager->register(new SiteUrl());

        $tags_manager->register(new ArchiveTitle());
        $tags_manager->register(new ArchiveDescription());
        $tags_manager->register(new ArchiveUrl());

        $tags_manager->register(new TermName());
        $tags_manager->register(new TermDescription());
        $tags_manager->register(new TermUrl());
        $tags_manager->register(new TermCount());
        $tags_manager->register(new TermCustomField());

        $tags_manager->register(new AuthorName());
        $tags_manager->register(new AuthorInfo());
        $tags_manager->register(new AuthorUrl());
        $tags_manager->register(new AuthorProfilePicture());

        $tags_manager->register(new Shortcode());
        $tags_manager->register(new RequestParameter());
        $tags_manager->register(new CurrentDateTime());
        $tags_manager->register(new ContactUrl());

        $tags_manager->register(new AcfField());
        $tags_manager->register(new AcfColor());
        $tags_manager->register(new AcfDateTime());
        $tags_manager->register(new AcfGallery());
        $tags_manager->register(new AcfImage());
        $tags_manager->register(new AcfNumber());
        $tags_manager->register(new AcfUrl());

        if (class_exists('WooCommerce')) {
            $tags_manager->register(new ProductPrice());
            $tags_manager->register(new ProductSku());
            $tags_manager->register(new ProductStock());
            $tags_manager->register(new ProductRating());
            $tags_manager->register(new ProductShortDescription());
            $tags_manager->register(new ProductGallery());
            $tags_manager->register(new ProductAddToCartUrl());
        }
    }

    public function register_groups($tags_manager)
    {
        $tags_manager->register_group(
            'elemacy',
            [
                'title' => esc_html__('Elemacy', 'elemacy')
            ]
        );

        if (class_exists('WooCommerce')) {
            $tags_manager->register_group(
                'elemacy-woocommerce',
                [
                    'title' => esc_html__('Elemacy WooCommerce', 'elemacy')
                ]
            );
        }
    }
}
