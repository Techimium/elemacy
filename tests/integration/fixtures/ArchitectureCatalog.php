<?php

namespace Elemacy\Tests\Integration\Fixtures;

/** Reusable authoring data for the release regressions; owns all created IDs. */
final class ArchitectureCatalog
{
    private LibraryDocument $documents;
    private array $posts = [];
    private array $terms = [];
    private array $users = [];
    private string $field_group = '';
    private string $field_key = '';
    private string $options_id = '';

    public function __construct()
    {
        $this->documents = new LibraryDocument();
    }

    public function create(): array
    {
        if (!function_exists('acf_is_field_type') || !acf_is_field_type('repeater')
            || !function_exists('acf_add_options_page')) {
            throw new \RuntimeException('Native repeater fixtures require an available ACF Pro installation; provision with --acf-plugin.');
        }

        $suffix = bin2hex(random_bytes(6));
        $catalog = ['posts' => [], 'library' => [], 'sources' => [], 'locations' => [], 'popups' => []];
        foreach (['published' => 'publish', 'draft' => 'draft', 'private' => 'private', 'protected' => 'publish'] as $name => $status) {
            $catalog['posts'][$name] = $this->post([
                'post_title' => 'Source ' . $name . ' ' . $suffix,
                'post_content' => 'Native source content ' . $name,
                'post_status' => $status,
                'post_password' => $name === 'protected' ? 'fixture-password' : '',
            ]);
            $id = $this->documents->create('Loop ' . $name . ' ' . $suffix, 'loop', $status);
            if ($name === 'protected') {
                $this->updatePost(['ID' => $id, 'post_password' => 'fixture-password']);
            }
            $catalog['library'][$name] = $id;
        }
        $catalog['library']['wrong_type'] = $this->documents->create('Section ' . $suffix);

        $term = wp_insert_term('Fixture category ' . $suffix, 'category');
        $this->assertNativeResult($term);
        $this->terms[] = (int) $term['term_id'];
        $catalog['term'] = (int) $term['term_id'];
        update_term_meta($catalog['term'], 'fixture_label', 'Native term metadata');
        wp_set_post_terms($catalog['posts']['published'], [$catalog['term']], 'category');

        $user = wp_insert_user([
            'user_login' => 'elemacy_fixture_' . $suffix,
            'user_pass' => wp_generate_password(32),
            'user_email' => 'fixture-' . $suffix . '@example.invalid',
            'display_name' => 'Fixture author ' . $suffix,
            'role' => 'author',
        ]);
        $this->assertNativeResult($user);
        $this->users[] = (int) $user;
        $catalog['user'] = (int) $user;
        $this->updatePost(['ID' => $catalog['posts']['published'], 'post_author' => $user]);

        $catalog['acf'] = $this->acf($suffix, $catalog['posts']['published']);
        $catalog['sources'] = [
            'posts' => [
                'data_source' => 'posts', 'post_type' => 'post', 'posts_per_page' => 2,
                'orderby' => 'title', 'order' => 'ASC', 'exclude_current_post' => '', 'offset' => 0,
            ],
            'terms' => [
                'data_source' => 'terms', 'taxonomy' => 'category', 'terms_number' => 2,
                'terms_orderby' => 'id', 'terms_order' => 'ASC', 'hide_empty' => '',
            ],
            'users' => [
                'data_source' => 'users', 'users_roles' => ['author'], 'users_number' => 2,
                'users_orderby' => 'ID', 'users_order' => 'ASC',
            ],
            'acf_repeater' => [
                'data_source' => 'acf_repeater', 'repeater_field' => $catalog['acf']['name'],
                'source_type' => 'specific_post', 'specific_post_id' => $catalog['posts']['published'],
                'rows_number' => 2, 'rows_offset' => 0,
            ],
        ];
        $grid = $catalog['sources']['posts'] + [
            'template_id' => $catalog['library']['published'], 'pagination_type' => 'ajax',
        ];
        $catalog['widget_id'] = 'eac2002';
        $catalog['nested_loop'] = $this->documents->create('Outer loop ' . $suffix, 'loop', 'publish', self::grid($grid));
        foreach (['header', 'single', 'footer'] as $location) {
            $catalog['locations'][$location] = $this->documents->create('Location ' . $location . ' ' . $suffix, $location);
        }
        $catalog['library']['wrong_group'] = $catalog['locations']['header'];

        foreach (['center', 'custom'] as $position) {
            $id = $this->documents->create('Popup ' . $position . ' ' . $suffix, 'popup');
            update_post_meta($id, '_elemacy_popup_triggers', [['id' => 'fixture-click', 'type' => 'click', 'params' => ['selector' => '#fixture-open']]]);
            update_post_meta($id, '_elemacy_popup_rules', []);
            $settings = get_post_meta($id, '_elementor_page_settings', true) ?: [];
            $settings['elemacy_position'] = $position;
            if ($position === 'custom') {
                $settings['elemacy_offset_x'] = ['unit' => 'px', 'size' => 37];
                $settings['elemacy_offset_y'] = ['unit' => 'px', 'size' => 53];
                $settings['elemacy_offset_x_mobile'] = ['unit' => 'px', 'size' => 11];
            }
            update_post_meta($id, '_elementor_page_settings', $settings);
            $catalog['popups'][$position] = $id;
        }

        $catalog['native_host'] = $this->post([
            'post_type' => 'page', 'post_status' => 'publish',
            'post_title' => 'Native host ' . $suffix, 'post_content' => '[elemacy_fixture_document]',
            'meta_input' => [
                '_elemacy_fixture_environment' => ELEMACY_INTEGRATION_TOKEN,
                '_elemacy_fixture_document' => $catalog['library']['wrong_type'],
            ],
        ]);
        $owner = \Elementor\Plugin::instance()->documents->create('wp-page', [
            'post_type' => 'page', 'post_title' => 'Elementor host ' . $suffix, 'post_status' => 'draft',
        ]);
        $this->assertNativeResult($owner);
        $catalog['elementor_host'] = $owner->get_main_id();
        $this->posts[] = $catalog['elementor_host'];
        if (!$owner->save(['elements' => self::grid($grid), 'settings' => ['post_status' => 'publish']])) {
            throw new \RuntimeException('Could not save the authored pagination owner.');
        }
        return $catalog;
    }

    public static function grid(array $settings): array
    {
        $elements = LibraryDocument::heading('');
        $elements[0]['elements'][0] = [
            'id' => 'eac2002', 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid',
            'settings' => $settings, 'elements' => [],
        ];
        return $elements;
    }

    private function acf(string $suffix, int $post): array
    {
        $name = 'elemacy_fixture_rows_' . $suffix;
        $this->field_group = 'group_elemacy_' . $suffix;
        $this->field_key = 'field_elemacy_rows_' . $suffix;
        $this->options_id = 'elemacy_fixture_options_' . $suffix;
        acf_add_local_field_group([
            'key' => $this->field_group, 'title' => 'Elemacy integration rows',
            'fields' => [[
                'key' => $this->field_key, 'name' => $name, 'label' => 'Rows', 'type' => 'repeater',
                'sub_fields' => [
                    ['key' => 'field_elemacy_label_' . $suffix, 'name' => 'label', 'label' => 'Label', 'type' => 'text'],
                    ['key' => 'field_elemacy_children_' . $suffix, 'name' => 'children', 'label' => 'Children', 'type' => 'repeater',
                        'sub_fields' => [['key' => 'field_elemacy_child_' . $suffix, 'name' => 'label', 'label' => 'Label', 'type' => 'text']]],
                ],
            ]],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
        ]);
        $options = acf_add_options_page([
            'page_title' => 'Elemacy fixture options', 'menu_slug' => $this->options_id,
            'post_id' => $this->options_id, 'redirect' => false,
        ]);
        if (!$options) {
            throw new \RuntimeException('Native ACF options page registration is unavailable.');
        }
        $rows = [
            ['label' => 'First native row', 'children' => [['label' => 'Nested native row']]],
            ['label' => 'Second native row', 'children' => []],
            ['label' => 'Third native row', 'children' => []],
        ];
        if (!update_field($this->field_key, $rows, $post)
            || !update_field($this->field_key, $rows, $this->options_id)) {
            throw new \RuntimeException('Native ACF repeater data could not be saved.');
        }
        return ['key' => $this->field_key, 'name' => $name, 'post_id' => $post,
            'options_id' => $this->options_id, 'rows' => $rows];
    }

    private function post(array $values): int
    {
        $id = wp_insert_post($values + ['post_type' => 'post'], true);
        $this->assertNativeResult($id);
        $this->posts[] = (int) $id;
        return (int) $id;
    }

    private function updatePost(array $values): void
    {
        $this->assertNativeResult(wp_update_post($values, true));
    }

    private function assertNativeResult($result): void
    {
        if (is_wp_error($result)) {
            throw new \RuntimeException($result->get_error_message());
        }
        if (!$result) {
            throw new \RuntimeException('A native fixture operation failed.');
        }
    }

    public function cleanup(): void
    {
        if ($this->field_key && $this->options_id) {
            delete_field($this->field_key, $this->options_id);
        }
        if ($this->field_group) {
            acf_remove_local_field_group($this->field_group);
        }
        if ($this->options_id) {
            // ACF 6.8 exposes no remove API; remove only this fixture's entry
            // from its public request-local options-page registry.
            unset(acf_options_page()->pages[$this->options_id]);
        }
        $this->field_key = $this->field_group = $this->options_id = '';
        $this->documents->cleanup();
        foreach (array_reverse($this->posts) as $post) {
            wp_delete_post($post, true);
        }
        foreach ($this->terms as $term) {
            wp_delete_term($term, 'category');
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($this->users as $user) {
            wp_delete_user($user);
        }
        $this->posts = $this->terms = $this->users = [];
    }
}
