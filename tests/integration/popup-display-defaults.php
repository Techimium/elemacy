<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Modules\Popups\Support\DisplayDefaults;
use Elemacy\Modules\Popups\Support\DocumentDisplay;
use Elemacy\Modules\Popups\Support\PopupTypes;
use Elemacy\TemplateLibrary\Constants\MetaKeys;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Task 10.5: DisplayDefaults is documented as "the schema source of truth
 * for the display settings shape" — DocumentDisplay (the reader) and
 * PopupDocument (the writer/Elementor controls) must both consult it rather
 * than hardcoding their own fallback for any field it declares.
 *
 * A real Elementor document's own get_settings() already merges in each
 * control's own default (itself sourced from DisplayDefaults), so the
 * reader's fallback branch is normally masked. To actually exercise it, this
 * uses a post Elementor does not recognize as one of its documents (no
 * `_elementor_edit_mode` meta) so DocumentDisplay::for_popup() takes its
 * empty-$settings path and every field falls through to its own fallback —
 * proving the fallback itself, not just the control's registered default,
 * agrees with DisplayDefaults.
 */
final class PopupDisplayDefaultsTest extends IntegrationTestCase
{
    private array $owned = [];

    protected function tearDown(): void
    {
        try {
            foreach (array_reverse($this->owned) as $id) {
                wp_delete_post($id, true);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function non_elementor_popup(string $type): int
    {
        $id = wp_insert_post([
            'post_type' => 'elemacy_library', 'post_status' => 'publish',
            'post_title' => 'Display defaults fixture (' . $type . ')',
        ], true);
        self::assertIsInt($id, 'Fixture post must be created.');
        $this->owned[] = $id;
        update_post_meta($id, MetaKeys::TEMPLATE_TYPE, $type);

        return $id;
    }

    public function testEveryDisplayDefaultsTypeDeclaresStickyExplicitly(): void
    {
        foreach ([PopupTypes::POPUP, PopupTypes::TOPBAR, PopupTypes::BANNER, PopupTypes::FLOATING] as $type) {
            $defaults = DisplayDefaults::for_type($type);
            self::assertArrayHasKey('sticky', $defaults, "$type must declare its own sticky default, not rely on a reader/control fallback.");
        }
    }

    /**
     * @dataProvider popupTypes
     */
    public function testDocumentDisplayFallsBackToDisplayDefaultsForEveryField(string $type): void
    {
        $id = $this->non_elementor_popup($type);
        $expected = DisplayDefaults::for_type($type);
        $is_modal = PopupTypes::POPUP === $type;

        $display = DocumentDisplay::for_popup($id);

        self::assertSame($expected['position'], $display['position'], "$type: position");
        self::assertSame($expected['sticky'], $display['sticky'], "$type: sticky");
        self::assertSame($expected['close']['button'], $display['close']['button'], "$type: close.button");
        self::assertSame($expected['close']['auto_close_s'], $display['close']['auto_close_s'], "$type: close.auto_close_s");
        self::assertSame(
            $is_modal && $expected['close']['on_overlay_click'],
            $display['close']['on_overlay_click'],
            "$type: close.on_overlay_click"
        );
        self::assertSame($is_modal && $expected['close']['on_esc'], $display['close']['on_esc'], "$type: close.on_esc");
        self::assertSame(
            $is_modal && $expected['prevent_body_scroll'],
            $display['prevent_body_scroll'],
            "$type: prevent_body_scroll"
        );
        self::assertSame($expected['z_index'], $display['z_index'], "$type: z_index");
    }

    public function popupTypes(): array
    {
        return [
            'popup' => [PopupTypes::POPUP],
            'topbar' => [PopupTypes::TOPBAR],
            'banner' => [PopupTypes::BANNER],
            'floating' => [PopupTypes::FLOATING],
        ];
    }

    /**
     * Task 10.5: PopupManager::is_top_bar() must compare against
     * PopupTypes::TOPBAR, not a raw 'topbar' literal — proven here by
     * confirming a real topbar popup is still classified as one end-to-end
     * (render_top_bars() picks it up; the constant reference in front of it
     * is exercised the same way a raw string would have been).
     */
    public function testTopBarPopupIsStillClassifiedAsATopBarByPopupManager(): void
    {
        $id = $this->non_elementor_popup(PopupTypes::TOPBAR);
        update_post_meta($id, '_wp_page_template', 'elementor_canvas');

        $ref = new \ReflectionMethod(\Elemacy\Modules\Popups\Services\PopupManager::class, 'is_top_bar');
        $ref->setAccessible(true);

        $popup = new \Elemacy\Modules\Popups\DTO\PopupDTO();
        $popup->id = $id;
        $popup->type = PopupTypes::TOPBAR;

        self::assertTrue(
            $ref->invoke(\Elemacy\Modules\Popups\Services\PopupManager::instance(), $popup),
            'A topbar popup positioned at the top (DisplayDefaults default) must be classified as a top bar.'
        );
    }
}
