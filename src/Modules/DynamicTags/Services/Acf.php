<?php

namespace Elemacy\Modules\DynamicTags\Services;

defined('ABSPATH') || exit;

class Acf
{
    public static function get_field_value($key, $post_id = false)
    {
        if (function_exists('get_field')) {
            $field = !empty(get_sub_field_object($key)) ? get_sub_field_object($key) : get_field_object($key, $post_id);

            if ($field && isset($field['type'])) {
                $value = $field['value'];

                switch ($field['type']) {
                    case 'radio':
                        $value = $field['choices'][$value] ?? $value;
                        break;

                    case 'select':
                    case 'checkbox':
                        $values = (array) $value;
                        $value = implode(', ', array_map(function ($item) use ($field) {
                            return $field['choices'][$item] ?? $item;
                        }, $values));
                        break;

                    case 'oembed':
                        $value = static::get_queried_object_meta($key, $post_id);
                        break;
                    case 'google_map':
                        $meta = static::get_queried_object_meta($key);
                        $value = isset($meta['address']) ? $meta['address'] : '';
                        break;
                    case 'true_false':
                        $value = (string) $value;
                        break;
                    case 'color_picker':
                        $value = (string) $value;
                        break;
                    case 'date_time_picker':
                        $value = static::get_date_time_value($field);
                        break;
                    case 'image':
                        $value = static::get_image_value($field);
                        break;
                    case 'link':
                        $value = static::get_url_value($field);
                        break;
                    case 'relationship':
                        break;
                }
            } else {
                $value = get_field($key, $post_id);
            }
        } else {
            // Fallback if ACF not installed.
            $value = get_post_meta($post_id ? $post_id : get_the_ID(), $key, true);
        }

        return $value;
    }

    protected static function get_queried_object_meta($key, $post_id = false)
    {
        $value = '';

        if (is_singular()) {
            $value = get_post_meta($post_id ? $post_id : get_the_ID(), $key, true);
        } elseif (is_tax() || is_category() || is_tag()) {
            $value = get_term_meta(get_queried_object_id(), $key, true);
        }

        return $value;
    }

    protected static function get_image_value($field)
    {
        $value = '';

        if ($field && is_array($field)) {
            $field['return_format'] = isset($field['save_format']) ? $field['save_format'] : $field['return_format'];
            switch ($field['return_format']) {
                case 'object':
                case 'array':
                    $value = $field['value'];
                    break;
                case 'url':
                    $value = [
                        'id' => 0,
                        'url' => $field['value'],
                    ];
                    break;
                case 'id':
                    $src = wp_get_attachment_image_src($field['value'], $field['preview_size']);
                    $value = [
                        'id' => $field['value'],
                        'url' => $src[0],
                    ];
                    break;
            }
        }

        return $value;
    }

    protected static function get_date_time_value($field)
    {
        $date_time = \DateTime::createFromFormat($field['return_format'], $field['value']);

        $display_format = $field['display_format'] ?? 'Y-m-d H:i:s';

        $value = $date_time instanceof \DateTime
            ? $date_time->format($display_format)
            : '';

        return $value;
    }

    protected static function get_url_value($field)
    {
        if (empty($field)) {
            return null;
        }

        $value = $field['value'];

        if (is_array($value) && isset($value[0])) {
            $value = $value[0];
        }

        if ($value) {
            if (!isset($field['return_format'])) {
                $field['return_format'] = isset($field['save_format']) ? $field['save_format'] : '';
            }


            switch ($field['type']) {
                case 'email':
                    if ($value) {
                        $value = 'mailto:' . $value;
                    }
                    break;
                case 'image':
                case 'file':
                    switch ($field['return_format']) {
                        case 'array':
                        case 'object':
                            $value = $value['url'];
                            break;
                        case 'id':
                            if ('image' === $field['type']) {
                                $src = wp_get_attachment_image_src($value, 'full');
                                $value = $src[0];
                            } else {
                                $value = wp_get_attachment_url($value);
                            }
                            break;
                    }
                    break;
                case 'post_object':
                case 'relationship':
                    $value = get_permalink($value);
                    break;
                case 'taxonomy':
                    $value = get_term_link($value, $field['taxonomy']);
                    break;
            } // End switch().
        }

        return $value;
    }
}
