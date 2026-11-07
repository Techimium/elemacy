<?php

/** Disposable ordinary, Theme Builder and popup form browser fixture. */

function elemacy_form_browser_reset_limiter(): void
{
    global $wpdb;
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('elemacy_form_limit_') . '%'));
}

try {
    require __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/fixtures/LibraryDocument.php';

    $directory = dirname($elemacy_test_environment['root']);
    $command = $argv[1] ?? '';

    if ($command === 'create') {
        $administrator = get_user_by('login', 'elemacy_test_admin');
        wp_set_current_user($administrator->ID);

        // Elementor's own Element Cache module (active by default) would
        // otherwise replace these widgets' output with an unexpanded
        // shortcode placeholder for this fixture's purposes — see
        // foreign-document-assets.php's own note on this. Orthogonal to
        // what this fixture verifies.
        update_option('elementor_element_cache_ttl', 'disable');
        // The runner sends five valid submissions from one address, the whole
        // per-IP allowance; start every run with an empty limiter.
        elemacy_form_browser_reset_limiter();

        $documents = new Elemacy\Tests\Integration\Fixtures\LibraryDocument();
        $owned_posts = [];

        try {
            $suffix = wp_generate_uuid4();

            $form = static function (string $id, array $fields, array $settings = []): array {
                return [
                    'id' => $id, 'elType' => 'widget', 'widgetType' => 'elemacy-form',
                    'settings' => $settings + ['form_fields' => $fields, 'submit_actions' => ['email'], 'email_to' => 'fixture@example.invalid'],
                    'elements' => [],
                ];
            };
            $contact = [
                ['_id' => 'nam0001', 'custom_id' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => 'yes'],
                ['_id' => 'ema0002', 'custom_id' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => 'yes'],
            ];
            // The form every location shows (page, Theme Builder header, popup).
            $elements = [$form('form123', $contact)];
            $host_elements = [
                $elements[0],
                // Same field IDs as form123, with labels hidden.
                $form('form456', $contact, ['labels_display' => '']),
                $form('form789', [
                    ['_id' => 'top0003', 'custom_id' => 'topics', 'type' => 'checkbox', 'label' => 'Topics', 'required' => 'yes', 'options' => "News|news\nOffers|offers\nEvents|events"],
                    ['_id' => 'pla0004', 'custom_id' => 'plan', 'type' => 'select', 'label' => 'Plan', 'required' => 'yes', 'placeholder' => 'Choose a plan', 'options' => "Basic|basic\nPro|pro"],
                    ['_id' => 'qty0005', 'custom_id' => 'quantity', 'type' => 'number', 'label' => 'Quantity', 'field_min' => 1, 'field_max' => 10, 'width' => '50'],
                    ['_id' => 'day0006', 'custom_id' => 'day', 'type' => 'date', 'label' => 'Day', 'min_date' => '2030-01-01', 'max_date' => '2030-12-31', 'width' => '50'],
                ]),
            ];
            $document = \Elementor\Plugin::instance()->documents->create('wp-page', [
                'post_type' => 'page', 'post_title' => 'Form submissions host ' . $suffix, 'post_status' => 'draft',
            ]);
            elemacy_test_assert(!is_wp_error($document), 'Could not create form page.');
            $host_id = $document->get_main_id();
            $owned_posts[] = $host_id;
            update_post_meta($host_id, '_elemacy_fixture_environment', ELEMACY_INTEGRATION_TOKEN);
            elemacy_test_assert($document->save(['elements' => $host_elements, 'settings' => ['post_status' => 'publish']]), 'Could not save form page.');
            $header_id = $documents->create_everywhere('Form submissions header ' . $suffix, 'header', 'publish', $elements);
            $owned_posts[] = $header_id;
            $popup_id = $documents->create('Form submissions popup ' . $suffix, 'popup', 'publish', $elements);
            $owned_posts[] = $popup_id;
            // A popup created in the admin starts with one immediate Page Load trigger.
            update_post_meta($popup_id, '_elemacy_popup_triggers', [['id' => 'fixture-page-load', 'type' => 'page_load', 'params' => ['delay' => 0]]]);

            $fixture = [
                'token' => ELEMACY_INTEGRATION_TOKEN,
                'host_id' => $host_id,
                'popup_id' => $popup_id,
                'header_id' => $header_id,
                'url' => get_permalink($host_id),
                'owned_posts' => $owned_posts,
                'manifest' => $directory . '/form-submissions-fixture-' . wp_generate_uuid4() . '.json',
            ];
            elemacy_test_assert((bool) file_put_contents($fixture['manifest'], wp_json_encode($fixture)), 'Cannot record fixture ownership.');
            chmod($fixture['manifest'], 0600);
            echo wp_json_encode($fixture) . "\n";
        } catch (Throwable $error) {
            foreach (array_reverse($owned_posts) as $id) {
                wp_delete_post($id, true);
            }
            $documents->cleanup();
            delete_option('elementor_element_cache_ttl');
            throw $error;
        }
    } elseif ($command === 'cleanup') {
        $path = realpath($argv[2] ?? '');
        elemacy_test_assert($path && dirname($path) === $directory
            && preg_match('/\Aform-submissions-fixture-[a-f0-9-]+\.json\z/', basename($path)), 'Refusing an unrelated fixture manifest.');
        $fixture = json_decode(file_get_contents($path), true);
        elemacy_test_assert(($fixture['token'] ?? '') === ELEMACY_INTEGRATION_TOKEN, 'Fixture belongs to another environment.');
        foreach (array_reverse($fixture['owned_posts'] ?? []) as $id) {
            $id = (int) $id;
            if (!$id || !get_post($id)) {
                continue;
            }
            elemacy_test_assert(get_post_meta($id, '_elemacy_fixture_environment', true) === ELEMACY_INTEGRATION_TOKEN
                || get_post_type($id) === 'elemacy_library', 'Refusing to delete an unowned post.');
            wp_delete_post($id, true);
            elemacy_test_assert(!get_post($id), 'Fixture cleanup failed for post ' . $id . '.');
        }
        delete_option('elementor_element_cache_ttl');
        elemacy_form_browser_reset_limiter();
        unlink($path);
        echo "Form submissions browser fixture removed.\n";
    } else {
        throw new InvalidArgumentException('Use create or cleanup <fixture-manifest>.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Form submissions browser fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
