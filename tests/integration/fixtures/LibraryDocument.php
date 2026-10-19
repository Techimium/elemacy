<?php

namespace Elemacy\Tests\Integration\Fixtures;

/** Native Elementor authoring fixture; cleanup owns only IDs it created. */
final class LibraryDocument
{
    private array $ids = [];

    public function create(string $title, string $type = 'section', string $status = 'publish', array $elements = []): int
    {
        $document_types = [
            'section' => 'wp-page',
            'loop' => 'elemacy_loop',
            'header' => 'elemacy_header',
            'footer' => 'elemacy_footer',
            'single' => 'elemacy_single',
            'archive' => 'elemacy_archive',
            'search' => 'elemacy_search',
            '404' => 'elemacy_404',
            'popup' => 'elemacy_popup',
        ];
        if (!isset($document_types[$type])) {
            throw new \InvalidArgumentException('Unregistered fixture document type: ' . $type);
        }

        $document = \Elementor\Plugin::instance()->documents->create(
            $document_types[$type],
            ['post_type' => 'elemacy_library', 'post_title' => $title, 'post_status' => 'draft'],
            ['_elemacy_template_type' => $type]
        );
        if (is_wp_error($document)) {
            throw new \RuntimeException($document->get_error_message());
        }
        $id = $document->get_main_id();
        $this->ids[] = $id;

        $saved = $document->save([
            'elements' => $elements ?: self::heading($title),
            'settings' => ['post_status' => $status],
        ]);
        if (!$saved || !get_post_meta($id, '_elementor_data', true)) {
            throw new \RuntimeException('Elementor could not save fixture document ' . $id);
        }

        return $id;
    }

    /**
     * Like create(), plus the conditions a new template of $type starts with
     * in the Theme Builder (its broadest one, e.g. Entire Site), so a
     * published theme template is actually displayed. A type targeting its
     * whole location (404) gets none, as in the admin.
     */
    public function create_everywhere(string $title, string $type, string $status = 'publish', array $elements = []): int
    {
        $id = $this->create($title, $type, $status, $elements);
        $definition = \Elemacy\TemplateLibrary\TypeRegistry::instance()->get($type);

        if ($definition === null) {
            throw new \InvalidArgumentException('Unregistered library type: ' . $type);
        }

        if ($definition->default_conditions !== []) {
            (new \Elemacy\Conditions\ConditionRepository())->save($id, $definition->default_conditions);
        }

        return $id;
    }

    /** Takes ownership of a library item created another way, so cleanup() removes it too. */
    public function adopt(int $id): int
    {
        $this->ids[] = $id;

        return $id;
    }

    public static function heading(string $text): array
    {
        return [[
            'id' => 'eac1001',
            'elType' => 'container',
            'settings' => [],
            'elements' => [[
                'id' => 'eac1002',
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => ['title' => $text, 'header_size' => 'h2'],
                'elements' => [],
            ]],
        ]];
    }

    public function cleanup(): void
    {
        foreach (array_reverse($this->ids) as $id) {
            wp_delete_post($id, true);
            if (get_post($id)) {
                throw new \RuntimeException('Fixture document was not removed: ' . $id);
            }
        }
        $this->ids = [];
    }
}
