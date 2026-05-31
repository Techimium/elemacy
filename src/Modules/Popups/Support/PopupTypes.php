<?php

namespace Elemacy\Modules\Popups\Support;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;

class PopupTypes
{
    const POPUP    = 'popup';
    const TOPBAR   = 'topbar';
    const BANNER   = 'banner';
    const FLOATING = 'floating';

    /**
     * All popup types as `[{value, label}, ...]`.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function all(): array
    {
        $types = [
            [
				'value' => self::POPUP,
				'label' => __('Popup', 'elemacy')
			],
            [
				'value' => self::TOPBAR,
				'label' => __('Top/Bottom Bar', 'elemacy')
			],
            [
				'value' => self::FLOATING,
				'label' => __('Floating Element', 'elemacy')
			],
            [
				'value' => self::BANNER,
				'label' => __('Banner', 'elemacy')
			],
        ];

        return apply_filters(Hooks::POPUP_TYPES_FILTER, $types);
    }

    /**
     * Flat list of valid type values.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(
            static fn(array $type): string => $type['value'],
            self::all()
        );
    }
}
