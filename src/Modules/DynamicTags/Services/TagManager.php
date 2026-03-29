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
use Elemacy\Modules\DynamicTags\Tags\Base\PostFeaturedImage;
use Elemacy\Modules\DynamicTags\Tags\Base\PostContent;
use Elemacy\Modules\DynamicTags\Tags\Base\PostCustomField;
use Elemacy\Modules\DynamicTags\Tags\Base\PostDate;
use Elemacy\Modules\DynamicTags\Tags\Base\PostExcerpt;
use Elemacy\Modules\DynamicTags\Tags\Base\PostUrl;
use Elemacy\Modules\DynamicTags\Tags\Base\PostTitle;
use Elemacy\Modules\DynamicTags\Tags\Base\SiteLogo;
use Elemacy\Modules\DynamicTags\Tags\Base\SiteTagline;
use Elemacy\Modules\DynamicTags\Tags\Base\SiteTitle;
use Elemacy\Modules\DynamicTags\Tags\Base\SiteUrl;

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
        $this->register_group($tags_manager);

        $tags_manager->register(new PostTitle());
        $tags_manager->register(new PostContent());
        $tags_manager->register(new PostExcerpt());
        $tags_manager->register(new PostDate());
        $tags_manager->register(new PostCustomField());
        $tags_manager->register(new PostUrl());
        $tags_manager->register(new PostFeaturedImage());

        $tags_manager->register(new SiteLogo());
        $tags_manager->register(new SiteTagline());
        $tags_manager->register(new SiteTitle());
        $tags_manager->register(new SiteUrl());

        $tags_manager->register(new AcfField());
        $tags_manager->register(new AcfColor());
        $tags_manager->register(new AcfDateTime());
        $tags_manager->register(new AcfGallery());
        $tags_manager->register(new AcfImage());
        $tags_manager->register(new AcfNumber());
        $tags_manager->register(new AcfUrl());
    }

    public function register_group($tags_manager)
    {
        $tags_manager->register_group(
            'elemacy',
            [
                'title' => esc_html__('Elemacy', 'elemacy')
            ]
        );
    }
}
