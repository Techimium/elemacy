<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Elementor\Rendering\TemplateRenderer;

require_once __DIR__ . '/IntegrationTestCase.php';

final class DocumentLifecycleTest extends IntegrationTestCase
{
    public function testNativeElementorDocumentCreatesRendersAndDeletes(): void
    {
        $marker = 'Elemacy real document ' . wp_generate_uuid4();
        $id = $this->documents->create($marker);
        self::assertSame('elemacy_library', get_post_type($id));
        self::assertSame('publish', get_post_status($id));
        self::assertSame('wp-page', get_post_meta($id, '_elementor_template_type', true));

        $html = (new TemplateRenderer())->render($id);
        self::assertStringContainsString($marker, $html, 'Real Elementor must render the saved heading.');
        self::assertStringContainsString('data-elementor-id="' . $id . '"', $html);

        $this->documents->cleanup();
        self::assertNull(get_post($id), 'Document survived force deletion.');
        self::assertSame([], get_post_meta($id), 'Metadata survived force deletion.');
    }
}
