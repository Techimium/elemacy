<?php

namespace Elemacy\Modules\LoopBuilder\PostCard;

defined('ABSPATH') || exit;

/**
 * A card design read from saved widget settings, with every default filled in.
 * The first page and a later page loaded in place read the same thing, whether
 * or not Elementor stored each value, so their cards cannot differ.
 *
 * @internal
 */
final class PostCardDefinition
{
    public const ZONE_MEDIA_TOP = 'media_top';
    public const ZONE_MEDIA_BOTTOM = 'media_bottom';
    public const ZONE_BODY = 'body';
    public const ZONE_FOOTER = 'footer';
    public const ZONES = [self::ZONE_MEDIA_TOP, self::ZONE_MEDIA_BOTTOM, self::ZONE_BODY, self::ZONE_FOOTER];

    public const PARTS = 'card_parts';
    public const WHOLE_CARD_LINK = 'card_whole_link';
    public const NEW_TAB = 'card_links_new_tab';
    public const LAYOUT = 'layout';

    public const LAYOUT_GRID = 'grid';

    /**
     * The Part control's default. Elementor saves no value equal to its
     * default, even inside a repeater row, so a saved row without `part` is
     * one of these.
     */
    public const DEFAULT_PART = 'title';

    /** @var array<int, array<string, mixed>> */
    private array $rows;
    private bool $whole_card_link;
    private bool $new_tab;

    /** @return array<int, array{part: string, zone: string}> What a new widget starts with. */
    public static function default_rows(): array
    {
        $row = static fn (string $part, string $zone, array $extra = []): array => [
            'part' => $part,
            'zone' => $zone,
        ] + $extra;

        return [
            $row('image', self::ZONE_BODY),
            $row('title', self::ZONE_BODY),
            $row('meta', self::ZONE_BODY, ['meta_item' => 'date']),
            $row('meta', self::ZONE_BODY, ['meta_item' => 'comments']),
            $row('excerpt', self::ZONE_BODY),
            $row('button', self::ZONE_FOOTER),
        ];
    }

    public function __construct(array $settings)
    {
        $saved = $settings[self::PARTS] ?? self::default_rows();
        $this->rows = [];

        foreach (is_array($saved) ? $saved : [] as $row) {
            $part = is_array($row) ? PostCardParts::get((string) ($row['part'] ?? self::DEFAULT_PART)) : null;

            if (null !== $part) {
                $zone = (string) ($row['zone'] ?? self::ZONE_BODY);
                $this->rows[] = [
                    'part' => $part->key(),
                    'zone' => in_array($zone, self::ZONES, true) ? $zone : self::ZONE_BODY,
                ] + $row + $part->defaults();
            }
        }

        $this->whole_card_link = 'yes' === ($settings[self::WHOLE_CARD_LINK] ?? '');
        $this->new_tab = 'yes' === ($settings[self::NEW_TAB] ?? '');
    }

    /** @return array<int, array<string, mixed>> */
    public function rows(): array
    {
        return $this->rows;
    }

    public function whole_card_link(): bool
    {
        return $this->whole_card_link;
    }

    public function new_tab(): bool
    {
        return $this->new_tab;
    }
}
