<?php

defined('ABSPATH') || exit;

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
use Elemacy\Modules\DynamicTags\Tags\Base\PostContent;
use Elemacy\Modules\DynamicTags\Tags\Base\PostCustomField;
use Elemacy\Modules\DynamicTags\Tags\Base\PostDate;
use Elemacy\Modules\DynamicTags\Tags\Base\PostExcerpt;
use Elemacy\Modules\DynamicTags\Tags\Base\PostFeaturedImage;
use Elemacy\Modules\DynamicTags\Tags\Base\PostTerms;
use Elemacy\Modules\DynamicTags\Tags\Base\PostTitle;
use Elemacy\Modules\DynamicTags\Tags\Base\PostUrl;
use Elemacy\Modules\DynamicTags\Tags\Base\SiteLogo;
use Elemacy\Modules\DynamicTags\Tags\Base\SiteTagline;
use Elemacy\Modules\DynamicTags\Tags\Base\SiteTitle;
use Elemacy\Modules\DynamicTags\Tags\Base\SiteUrl;
use Elemacy\Modules\DynamicTags\Tags\Taxonomy\TermCount;
use Elemacy\Modules\DynamicTags\Tags\Taxonomy\TermCustomField;
use Elemacy\Modules\DynamicTags\Tags\Taxonomy\TermDescription;
use Elemacy\Modules\DynamicTags\Tags\Taxonomy\TermName;
use Elemacy\Modules\DynamicTags\Tags\Taxonomy\TermUrl;
use Elemacy\Modules\DynamicTags\Tags\User\UserAvatar;
use Elemacy\Modules\DynamicTags\Tags\User\UserDescription;
use Elemacy\Modules\DynamicTags\Tags\User\UserEmail;
use Elemacy\Modules\DynamicTags\Tags\User\UserLogin;
use Elemacy\Modules\DynamicTags\Tags\User\UserName;
use Elemacy\Modules\DynamicTags\Tags\User\UserPostCount;
use Elemacy\Modules\DynamicTags\Tags\User\UserUrl;
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

/**
 * Dynamic tag groups free ships, keyed by group name. Each group has a
 * translated `title`, its `tags` as class-strings, and an optional `requires`
 * class name: when that class is missing (its plugin is inactive) neither the
 * group nor its tags are registered. Required inside Elementor's
 * `elementor/dynamic_tags/register` callback, so translations are loaded.
 *
 * @see \Elemacy\Modules\DynamicTags\Services\TagManager::register_tags()
 */
return [
    'elemacy' => [
        'title' => esc_html__('Elemacy', 'elemacy'),
        'tags'  => [
            // Post.
            PostTitle::class,
            PostContent::class,
            PostExcerpt::class,
            PostDate::class,
            PostCustomField::class,
            PostUrl::class,
            PostFeaturedImage::class,
            PostTerms::class,
            CommentsNumber::class,
            PageTitle::class,

            // Site.
            SiteLogo::class,
            SiteTagline::class,
            SiteTitle::class,
            SiteUrl::class,

            // Archive.
            ArchiveTitle::class,
            ArchiveDescription::class,
            ArchiveUrl::class,

            // Term.
            TermName::class,
            TermDescription::class,
            TermUrl::class,
            TermCount::class,
            TermCustomField::class,

            // Author.
            AuthorName::class,
            AuthorInfo::class,
            AuthorUrl::class,
            AuthorProfilePicture::class,

            // User.
            UserName::class,
            UserDescription::class,
            UserAvatar::class,
            UserUrl::class,
            UserLogin::class,
            UserEmail::class,
            UserPostCount::class,

            // Utility.
            Shortcode::class,
            RequestParameter::class,
            CurrentDateTime::class,
            ContactUrl::class,

            // ACF.
            AcfField::class,
            AcfColor::class,
            AcfDateTime::class,
            AcfGallery::class,
            AcfImage::class,
            AcfNumber::class,
            AcfUrl::class,
        ],
    ],

    'elemacy-woocommerce' => [
        'title'    => esc_html__('Elemacy WooCommerce', 'elemacy'),
        'requires' => 'WooCommerce',
        'tags'     => [
            ProductPrice::class,
            ProductSku::class,
            ProductStock::class,
            ProductRating::class,
            ProductShortDescription::class,
            ProductGallery::class,
            ProductAddToCartUrl::class,
        ],
    ],
];
