<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Container;
use Elemacy\Core\Hooks;
use Elemacy\Forms\Services\SubmissionRateLimiter;
use Elemacy\Forms\Services\SubmissionService;
use Elemacy\Forms\Submission\SubmissionMeta;
use Elemacy\Http\Exceptions\HttpException;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * The forms engine against real WordPress and Elementor (openspec
 * forms/submission-pipeline, forms/field-types, forms/extension-contract):
 * saved-form resolution, owner access, per-type validation, actions and
 * their hooks, the public endpoint and the rate limiter. The fixture add-on
 * in fixtures/mu-plugins/form-registry-extension.php registers the
 * fixture_color field type and the fixture_log action.
 */
final class FormSubmissionsTest extends IntegrationTestCase
{
    private array $owned = [];
    private array $mail = [];
    private string $ip;
    private $capture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ip = 'form-fixture-' . wp_generate_uuid4();
        $this->capture = function ($result, $mail) {
            $this->mail[] = $mail;
            return true;
        };
        add_filter('pre_wp_mail', $this->capture, 999, 2);
        $GLOBALS['elemacy_fixture_form_log'] = [];
    }

    protected function tearDown(): void
    {
        remove_filter('pre_wp_mail', $this->capture, 999);
        foreach ($this->owned as $id) {
            wp_delete_post($id, true);
        }
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('elemacy_form_limit_') . '%'));
        parent::tearDown();
    }

    private function fields(): array
    {
        return [
            ['_id' => 'nam0001', 'type' => 'text', 'label' => 'Name', 'custom_id' => 'name', 'required' => 'yes'],
            ['_id' => 'ema0002', 'type' => 'email', 'label' => 'Email', 'custom_id' => 'email', 'required' => 'yes'],
            ['_id' => 'top0003', 'type' => 'checkbox', 'label' => 'Topics', 'options' => "One|one\nTwo|two", 'required' => ''],
            ['_id' => 'msg0004', 'type' => 'textarea', 'label' => 'Message', 'custom_id' => 'message', 'required' => ''],
        ];
    }

    private function settings(array $fields = null): array
    {
        return [
            'form_fields' => $fields ?? $this->fields(),
            'submit_actions' => ['email'],
            'email_to' => 'authored@example.invalid',
            'email_reply_to' => 'email',
            'email_content' => '[field id="name"] [field id="top0003"] [all-fields]',
        ];
    }

    private function values(): array
    {
        return ['name' => 'Ada', 'email' => 'ada@example.invalid', 'top0003' => ['one'], 'message' => "Hello\nWorld"];
    }

    private function widget(string $id = 'form123', array $settings = null, string $widget_type = 'elemacy-form'): array
    {
        return ['id' => $id, 'elType' => 'widget', 'widgetType' => $widget_type, 'settings' => $settings ?? $this->settings(), 'elements' => []];
    }

    private function page(string $status = 'publish', string $widget_type = 'elemacy-form', string $password = '', array $elements = null): int
    {
        $id = wp_insert_post(['post_type' => 'page', 'post_status' => $status, 'post_title' => 'Form fixture', 'post_password' => $password]);
        $this->owned[] = $id;
        update_post_meta($id, '_elementor_edit_mode', 'builder');
        update_post_meta($id, '_elementor_template_type', 'wp-page');
        update_post_meta($id, '_elementor_version', ELEMENTOR_VERSION);
        update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode([[
            'id' => 'box1', 'elType' => 'container', 'settings' => [], 'elements' => $elements ?? [$this->widget('form123', null, $widget_type)],
        ]])));
        return $id;
    }

    private function set_settings(int $id, array $settings, string $form = 'form123'): void
    {
        $tree = json_decode(get_post_meta($id, '_elementor_data', true), true);
        foreach ($tree[0]['elements'] as &$element) {
            if ($element['id'] === $form) {
                $element['settings'] = $settings;
            }
        }
        update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode($tree)));
    }

    /** @return array The success response. */
    private function submit(int $id, array $values = null, string $form = 'form123', string $source = 'elementor'): array
    {
        return Container::instance()->make(SubmissionService::class)->submit(
            $source,
            $id,
            $form,
            $values ?? $this->values(),
            new SubmissionMeta('', get_current_user_id(), time()),
            $this->ip
        );
    }

    /** Each saved widget of a page printed directly, as Elementor renders it. */
    private function markup(int $id): string
    {
        $request = new RequestSimulator();
        try {
            $request->visit(get_permalink($id));
            $html = '';
            foreach (json_decode((string) get_post_meta($id, '_elementor_data', true), true)[0]['elements'] as $data) {
                ob_start();
                \Elementor\Plugin::$instance->elements_manager->create_element_instance($data)->print_element();
                $html .= ob_get_clean();
            }
            return $html;
        } finally {
            $request->restore();
        }
    }

    /* ── Resolution and owner access ─────────────────────────────────────── */

    public function testPublishedSavedFormUsesAuthoredRecipientsAndFormatting(): void
    {
        $id = $this->page();
        wp_set_current_user(0);
        $response = $this->submit($id);
        self::assertSame('Form submitted successfully!', $response['message']);
        self::assertCount(1, $this->mail);
        self::assertSame('authored@example.invalid', $this->mail[0]['to']);
        self::assertStringStartsWith('Ada one <strong>Name:</strong> Ada', $this->mail[0]['message']);
        self::assertStringContainsString('<strong>Message:</strong> Hello<br />', $this->mail[0]['message']);
        self::assertStringContainsString('Reply-To: ada@example.invalid', implode("\n", $this->mail[0]['headers']));
    }

    public function testReorderedFieldsKeepTheirValuesAndPlaceholders(): void
    {
        $id = $this->page();
        $this->set_settings($id, $this->settings(array_reverse($this->fields())));
        wp_set_current_user(0);
        $this->submit($id);
        self::assertStringStartsWith('Ada one <strong>Message:</strong> Hello<br />', $this->mail[0]['message']);
        self::assertStringContainsString('<strong>Name:</strong> Ada', $this->mail[0]['message']);
        self::assertStringContainsString('Reply-To: ada@example.invalid', implode("\n", $this->mail[0]['headers']));
    }

    /** @dataProvider invalidHosts */
    public function testInvalidDefinitionCannotSend(string $status, string $type, string $password): void
    {
        $id = $this->page($status, $type, $password);
        wp_set_current_user(0);
        try {
            $this->submit($id);
            self::fail('Inaccessible or wrong-widget submission accepted.');
        } catch (ValidationException $error) {
            self::assertSame('This form is unavailable. Please refresh the page.', $error->getMessage());
            self::assertCount(0, $this->mail);
        }
    }

    public static function invalidHosts(): array
    {
        return [['draft', 'elemacy-form', ''], ['private', 'elemacy-form', ''], ['publish', 'heading', ''], ['publish', 'elemacy-form', 'secret']];
    }

    public function testNativePrivateReadCapabilityIsRetained(): void
    {
        $id = $this->page('private');
        self::assertTrue(current_user_can('read_post', $id));
        $this->submit($id);
        self::assertCount(1, $this->mail);
    }

    public function testUnknownSourceAmbiguousElementIdAndMissingFormAreUnavailable(): void
    {
        $id = $this->page('publish', 'elemacy-form', '', [$this->widget('form123'), $this->widget('form123')]);
        $other = $this->page();
        wp_set_current_user(0);
        foreach ([[$id, 'form123', 'elementor'], [$other, 'missing', 'elementor'], [$other, 'form123', 'another-builder']] as [$owner, $form, $source]) {
            try {
                $this->submit($owner, null, $form, $source);
                self::fail('Unresolvable form accepted: ' . $form . ' via ' . $source);
            } catch (ValidationException $error) {
                self::assertCount(0, $this->mail);
            }
        }
    }

    public function testLibraryTemplateFormsNeedNoPlacementTokenAndSurviveUnrelatedEdits(): void
    {
        $popup = $this->documents->create('Form popup', 'popup', 'publish', [$this->widget()]);
        $header = $this->documents->create('Form header', 'header', 'publish', [$this->widget()]);
        // Elementor saves documents only for a user who may edit them, so every template exists before the guest arrives.
        $unusable = [];
        foreach (['draft', 'bogus', 'trash'] as $value) {
            $unusable[$value] = $this->documents->create('Form popup ' . $value, 'popup', 'publish', [$this->widget()]);
        }
        wp_set_current_user(0);
        $this->submit($popup);
        $this->submit($header);
        // Markup a cache captured earlier stays valid after the template is edited.
        update_post_meta($popup, '_elemacy_popup_triggers', [['id' => 'later', 'type' => 'page_load', 'params' => ['delay' => 3]]]);
        $this->submit($popup);
        self::assertCount(3, $this->mail);

        foreach (['draft' => 'post_status', 'bogus' => '_elemacy_template_type', 'trash' => 'trash'] as $value => $what) {
            $template = $unusable[$value];
            if ('post_status' === $what) {
                wp_update_post(['ID' => $template, 'post_status' => $value]);
            } elseif ('trash' === $what) {
                wp_trash_post($template);
            } else {
                update_post_meta($template, $what, $value);
            }
            try {
                $this->submit($template);
                self::fail('Unusable template accepted: ' . $value);
            } catch (ValidationException $error) {
                self::assertCount(3, $this->mail);
            }
        }
    }

    /* ── Values ──────────────────────────────────────────────────────────── */

    /** @dataProvider invalidValues */
    public function testInvalidFieldShapesAndValuesProduceFieldErrors(array $changes, string $key): void
    {
        try {
            $this->submit($this->page(), array_merge($this->values(), $changes));
            self::fail('Malformed field accepted.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey($key, $error->get_errors());
            self::assertCount(0, $this->mail);
        }
    }

    public static function invalidValues(): array
    {
        return [
            [['name' => ''], 'name'],
            [['name' => ['Ada']], 'name'],
            [['email' => 'bad-email'], 'email'],
            [['email' => "ada@example.invalid\r\nBcc: other@example.invalid"], 'email'],
            [['top0003' => 'one'], 'top0003'],
            [['top0003' => ['unconfigured']], 'top0003'],
            [['top0003' => [['nested']]], 'top0003'],
            [['extra' => 'value'], 'extra'],
            [[0 => 'positional'], '0'],
        ];
    }

    /** @dataProvider newFieldTypes */
    public function testNewFieldTypesApplyTheirRulesOnTheServer(array $field, $valid, $invalid, string $message): void
    {
        $id = $this->page();
        $this->set_settings($id, $this->settings(array_merge($this->fields(), [$field + ['_id' => 'new0009', 'custom_id' => 'extra_field', 'label' => 'Extra']])));
        wp_set_current_user(0);
        $this->submit($id, $this->values() + ['extra_field' => $valid]);
        self::assertCount(1, $this->mail);
        try {
            $this->submit($id, $this->values() + ['extra_field' => $invalid]);
            self::fail('Invalid ' . $field['type'] . ' value accepted.');
        } catch (ValidationException $error) {
            self::assertSame($message, $error->get_errors()['extra_field'] ?? null);
            self::assertCount(1, $this->mail);
        }
    }

    public static function newFieldTypes(): array
    {
        return [
            'url' => [['type' => 'url'], 'https://example.com/page', 'javascript:alert(1)', 'Enter a valid URL starting with http:// or https://.'],
            'number' => [['type' => 'number', 'field_min' => 1, 'field_max' => 10], '5', '11', 'Enter a number of at most 10.'],
            'date' => [['type' => 'date', 'min_date' => '2026-01-01'], '2026-02-28', '2026-02-30', 'Enter a valid date.'],
            'time' => [['type' => 'time'], '09:30', '25:00', 'Enter a valid time.'],
            'acceptance' => [['type' => 'acceptance', 'required' => 'yes'], 'on', '', 'This field is required.'],
            'hidden' => [['type' => 'hidden', 'field_value' => 'spring'], 'spring', "a\nb", 'Invalid field value.'],
        ];
    }

    public function testHtmlAndUnregisteredFieldTypesSubmitNothingAndDoNotBlockTheForm(): void
    {
        $id = $this->page();
        $this->set_settings($id, $this->settings(array_merge($this->fields(), [
            ['_id' => 'htm0010', 'type' => 'html', 'field_html' => '<p>Note</p>'],
            ['_id' => 'sig0011', 'type' => 'signature', 'label' => 'Signature', 'required' => 'yes', 'custom_id' => 'signature'],
        ])));
        wp_set_current_user(0);
        $this->submit($id);
        self::assertCount(1, $this->mail);
        self::assertStringNotContainsString('Note', $this->mail[0]['message']);
        foreach (['htm0010' => 'x', 'signature' => 'x'] as $field => $value) {
            try {
                $this->submit($id, $this->values() + [$field => $value]);
                self::fail('A value for a field that submits nothing was accepted.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey($field, $error->get_errors());
            }
        }
    }

    public function testValidationFilterErrorsStopTheSubmission(): void
    {
        $id = $this->page();
        $filter = static function (array $errors, $submission) {
            return 'ada@example.invalid' === $submission->value('email') ? $errors + ['email' => 'This domain is blocked.'] : $errors;
        };
        add_filter(Hooks::FORM_VALIDATION_FILTER, $filter, 10, 2);
        try {
            $this->submit($id);
            self::fail('A filter-added error was ignored.');
        } catch (ValidationException $error) {
            self::assertSame(['email' => 'This domain is blocked.'], $error->get_errors());
            self::assertCount(0, $this->mail);
        } finally {
            remove_filter(Hooks::FORM_VALIDATION_FILTER, $filter, 10);
        }
    }

    public function testRequiredChoiceGroupsNeedOneChoiceAndAPlaceholderSelectCanFail(): void
    {
        $fields = array_merge($this->fields(), [
            ['_id' => 'int0014', 'type' => 'checkbox', 'label' => 'Interests', 'custom_id' => 'interests', 'required' => 'yes', 'options' => "A|a\nB|b\nC|c"],
            ['_id' => 'pla0015', 'type' => 'select', 'label' => 'Plan', 'custom_id' => 'plan', 'required' => 'yes', 'placeholder' => 'Choose a plan', 'options' => "Basic|basic\nPro|pro"],
        ]);
        $id = $this->page();
        $this->set_settings($id, $this->settings($fields));
        wp_set_current_user(0);
        $this->submit($id, $this->values() + ['interests' => ['b'], 'plan' => 'pro']);
        self::assertCount(1, $this->mail, 'One ticked box of a required group was refused.');

        foreach ([['interests', ['interests' => [], 'plan' => 'pro']], ['plan', ['interests' => ['a'], 'plan' => '']]] as [$empty, $changes]) {
            try {
                $this->submit($id, $changes + $this->values());
                self::fail('An empty required ' . $empty . ' was accepted.');
            } catch (ValidationException $error) {
                self::assertSame(['message' => 'This field is required.'], ['message' => $error->get_errors()[$empty] ?? null]);
                self::assertCount(1, $this->mail);
            }
        }
    }

    /* ── Actions and hooks ───────────────────────────────────────────────── */

    public function testSavedActionSelectionIsValidatedBeforeAnyMail(): void
    {
        $id = $this->page();
        $this->set_settings($id, ['submit_actions' => ['email', 'unsupported']] + $this->settings());
        try {
            $this->submit($id);
            self::fail('Unsupported action was accepted.');
        } catch (HttpException $error) {
            self::assertSame('This form action is unavailable.', $error->getMessage());
            self::assertCount(0, $this->mail);
        }
        $this->set_settings($id, ['submit_actions' => ['email', 'email']] + $this->settings());
        $this->submit($id);
        self::assertCount(1, $this->mail);
    }

    public function testTransportFailureIsNotSuccess(): void
    {
        $id = $this->page();
        $fail = static function () {
            return false;
        };
        add_filter('pre_wp_mail', $fail, 1000);
        try {
            $this->submit($id);
            self::fail('Mail failure reported success.');
        } catch (HttpException $error) {
            self::assertSame('Your message could not be sent. Please try again.', $error->getMessage());
        } finally {
            remove_filter('pre_wp_mail', $fail, 1000);
        }
    }

    public function testEmailHeadersTakeAFieldOrLiteralReplyToAndStayOnOneLine(): void
    {
        $id = $this->page();
        wp_set_current_user(0);
        $send = function (array $changes) use ($id): array {
            $this->mail = [];
            $this->set_settings($id, $changes + $this->settings());
            $this->submit($id);
            self::assertCount(1, $this->mail);
            return $this->mail[0];
        };

        self::assertContains('Reply-To: support@example.invalid', $send(['email_reply_to' => 'support@example.invalid'])['headers']);
        // A field whose value is not an address, or a setting that is not one, adds no Reply-To.
        foreach (['name', 'not an address', "x@example.invalid\r\nBcc: other@example.invalid"] as $reply_to) {
            self::assertEmpty(preg_grep('/^Reply-To:/', $send(['email_reply_to' => $reply_to])['headers']), $reply_to);
        }

        $mail = $send(['email_subject' => "Hello\r\nBcc: other@example.invalid", 'email_from_name' => "Site > Team\r\nBcc: x", 'email_from' => 'not-an-address']);
        self::assertSame('HelloBcc: other@example.invalid', $mail['subject']);
        self::assertCount(1, preg_grep('/\AFrom: Site  ?TeamBcc: x <' . preg_quote((string) get_option('admin_email'), '/') . '>\z/', $mail['headers']), implode(' | ', $mail['headers']));
        foreach ($mail['headers'] as $header) {
            self::assertDoesNotMatchRegularExpression('/[\r\n]/', $header);
        }
    }

    public function testExtensionFieldAndActionRegisterThroughHooksRenderAndRun(): void
    {
        $fields = array_merge($this->fields(), [
            ['_id' => 'col0012', 'type' => 'fixture_color', 'label' => 'Color', 'custom_id' => 'color', 'required' => 'yes'],
            ['_id' => 'out0013', 'type' => 'text', 'label' => 'Outcome', 'custom_id' => 'outcome'],
        ]);
        $id = $this->page();
        $this->set_settings($id, ['submit_actions' => ['fixture_log', 'email'], 'fixture_log_note' => 'from the fixture'] + $this->settings($fields));
        wp_set_current_user(0);

        $html = $this->markup($id);
        self::assertMatchesRegularExpression('/<input[^>]*name="form_fields\[color\]"[^>]*data-fixture-color="yes"/', $html);

        $runs = [];
        $submitted = 0;
        $observe_run = static function ($key, $result) use (&$runs) {
            $runs[] = [$key, $result->is_success()];
        };
        $observe_submitted = static function () use (&$submitted) {
            ++$submitted;
        };
        add_action(Hooks::FORM_ACTION_RUN_ACTION, $observe_run, 10, 2);
        add_action(Hooks::FORM_SUBMITTED_ACTION, $observe_submitted);
        try {
            $response = $this->submit($id, $this->values() + ['color' => 'red']);
            self::assertSame(['fixture_log' => ['logged' => true]], $response['data']);
            self::assertSame([['fixture_log', true], ['email', true]], $runs);
            self::assertSame(1, $submitted);
            self::assertSame('red', $GLOBALS['elemacy_fixture_form_log'][0]['values']['color']);
            self::assertSame('from the fixture', $GLOBALS['elemacy_fixture_form_log'][0]['note']);
            self::assertCount(1, $this->mail);

            try {
                $this->submit($id, $this->values() + ['color' => 'green']);
                self::fail('The extension field rule was not applied.');
            } catch (ValidationException $error) {
                self::assertSame('Pick red or blue.', $error->get_errors()['color']);
            }

            $runs = [];
            try {
                $this->submit($id, $this->values() + ['color' => 'blue', 'outcome' => 'fail']);
                self::fail('A failing action reported success.');
            } catch (HttpException $error) {
                self::assertSame('Could not reach the CRM', $error->getMessage());
                self::assertSame([['fixture_log', false]], $runs, 'A later action ran after a failure.');
                self::assertCount(1, $this->mail);
                self::assertSame(1, $submitted);
            }
        } finally {
            remove_action(Hooks::FORM_ACTION_RUN_ACTION, $observe_run, 10);
            remove_action(Hooks::FORM_SUBMITTED_ACTION, $observe_submitted);
        }
    }

    public function testAnActionKeyCanBeRegisteredOnlyOnce(): void
    {
        $registry = Container::instance()->make(\Elemacy\Forms\FormActionRegistry::class);
        $fixture = $registry->get('fixture_log');
        $reported = [];
        $record = static function ($function_name, $message) use (&$reported) {
            $reported[] = $function_name . ': ' . $message;
        };
        add_action('doing_it_wrong_run', $record, 10, 2);
        add_filter('doing_it_wrong_trigger_error', '__return_false');
        try {
            self::assertFalse($registry->register(new \Elemacy_Fixture_Log_Action()));
        } finally {
            remove_action('doing_it_wrong_run', $record, 10);
            remove_filter('doing_it_wrong_trigger_error', '__return_false');
        }
        self::assertSame($fixture, $registry->get('fixture_log'));
        self::assertSame(['Elemacy\Forms\FormActionRegistry::register: &quot;fixture_log&quot; is already registered.'], $reported);
    }

    /* ── Markup ──────────────────────────────────────────────────────────── */

    public function testRenderedMarkupIsCacheSafeUniqueAndAccessible(): void
    {
        $fields = [
            ['_id' => 'nam0001', 'type' => 'text', 'label' => 'Name', 'custom_id' => 'name'],
            ['_id' => 'top0003', 'type' => 'checkbox', 'label' => 'Topics', 'required' => 'yes', 'options' => "One\nTwo\nThree"],
            ['_id' => 'pla0005', 'type' => 'select', 'label' => 'Plan', 'required' => 'yes', 'placeholder' => 'Choose a plan', 'options' => "Basic|basic\nPro|pro"],
        ];
        $id = $this->page('publish', 'elemacy-form', '', [
            $this->widget('form1', $this->settings($fields)),
            $this->widget('form2', ['labels_display' => ''] + $this->settings($fields)),
        ]);
        wp_set_current_user(0);
        $html = $this->markup($id);

        preg_match_all('/\sid="([^"]+)"/', $html, $ids);
        self::assertGreaterThan(10, count($ids[1]));
        self::assertSame(array_values(array_unique($ids[1])), $ids[1], 'A DOM ID repeats.');
        preg_match_all('/<input[^>]*type="checkbox"[^>]*>/', $html, $boxes);
        self::assertCount(0, preg_grep('/\srequired(\s|>)/', $boxes[0]), 'A required checkbox group requires every box.');
        self::assertSame(2, substr_count($html, '<fieldset class="elemacy-form-group" data-required="true">'));
        self::assertSame(2, preg_match_all('/<option value="">Choose a plan<\/option>/', $html));
        // Hidden labels on the second form only: Name and Plan keep a screen-reader label, Topics its legend.
        self::assertSame(2, preg_match_all('/<label class="elemacy-form-label elemacy-screen-reader-text"/', $html));
        self::assertSame(1, preg_match_all('/<legend class="elemacy-form-label elemacy-screen-reader-text">Topics<\/legend>/', $html));
        self::assertStringNotContainsString('_wpnonce', $html);
        self::assertStringNotContainsString('elemacy_form_context', $html);
        self::assertStringContainsString('name="action" value="elemacy_form_submit"', $html);
        self::assertStringContainsString('name="form_owner" value="' . $id . '"', $html);
    }

    public function testAFormInALoopItemRendersOncePerItemWithItsOwnIdsAndSubmits(): void
    {
        // As elsewhere in this suite: Elementor's element cache is orthogonal to what is checked here.
        update_option('elementor_element_cache_ttl', 'disable');
        $template = $this->documents->create('Form loop item', 'loop', 'publish', [$this->widget()]);
        $posts = [];
        for ($i = 0; $i < 3; ++$i) {
            $posts[] = $this->owned[] = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Form loop post ' . $i]);
        }
        wp_set_current_user(0);

        $request = new RequestSimulator();
        $renderer = new \Elemacy\Loop\Rendering\LoopRenderer();
        try {
            $request->visit(get_permalink($posts[0]));
            ob_start();
            try {
                foreach ($posts as $occurrence => $post) {
                    self::assertTrue($renderer->render_item($template, new \Elemacy\Loop\Items\PostLoopItem(get_post($post)), 'form-loop', 'posts', $occurrence));
                }
                $html = (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
        } finally {
            $request->restore();
            delete_option('elementor_element_cache_ttl');
        }

        self::assertSame(3, substr_count($html, '<form class="elemacy-form"'), $html);
        self::assertSame(3, substr_count($html, 'name="form_owner" value="' . $template . '"'));
        self::assertSame(1, substr_count($html, 'id="elemacy-loop-base-' . $template . '"'), 'The loop template base styles are emitted once.');
        preg_match_all('/\sid="(elemacy-form-[^"]+)"/', $html, $ids);
        self::assertGreaterThan(6, count($ids[1]));
        self::assertSame(array_values(array_unique($ids[1])), $ids[1], 'A DOM ID repeats between loop items.');

        $this->submit($template);
        self::assertCount(1, $this->mail);
    }

    public function testEditorWarnsAboutInvalidAndDuplicateFieldIds(): void
    {
        $fields = [
            ['_id' => 'aaa0001', 'type' => 'text', 'label' => 'A', 'custom_id' => 'email'],
            ['_id' => 'bbb0002', 'type' => 'email', 'label' => 'B', 'custom_id' => 'email'],
            ['_id' => 'ccc0003', 'type' => 'text', 'label' => 'C', 'custom_id' => 'has space'],
        ];
        $id = $this->page('publish', 'elemacy-form', '', [$this->widget('form1', $this->settings($fields))]);
        $editor = \Elementor\Plugin::$instance->editor;
        $editor->set_edit_mode(true);
        try {
            $html = $this->markup($id);
        } finally {
            $editor->set_edit_mode(false);
        }
        self::assertStringContainsString('The field ID &quot;email&quot; is used more than once; only the first field keeps it.', $html);
        self::assertStringContainsString('&quot;has space&quot; is not a valid field ID', $html);
        self::assertStringNotContainsString('elemacy-alert', $this->markup($id), 'The ID notice leaked to visitors.');
    }

    /* ── Public endpoint ─────────────────────────────────────────────────── */

    public function testNativeEndpointContract(): void
    {
        $id = $this->page();
        $directory = dirname($GLOBALS['elemacy_test_environment']['root']);
        $mail = $directory . '/form-native-mail.jsonl';
        $users = $directory . '/form-native-users.jsonl';
        file_put_contents($mail, '');
        file_put_contents($users, '');
        $base = ['form_source' => 'elementor', 'form_owner' => (string) $id, 'form_id' => 'form123', 'form_fields' => $this->values()];

        foreach ([
            ['form_owner' => [$id]], ['form_owner' => '0'], ['form_owner' => '01'], ['form_id' => ''], ['form_source' => 'Bad Source'],
            ['form_fields' => 'bad-shape'], ['form_fields' => ['email' => 'bad-email'] + $this->values()],
        ] as $changed) {
            $result = $this->native($changed + $base);
            self::assertFalse($result['success'], wp_json_encode($changed));
            self::assertSame('', file_get_contents($mail));
        }
        // The pre-1.0 request names are not accepted.
        self::assertFalse($this->native(['post_id' => (string) $id, 'widget_id' => 'form123', 'form_field' => array_values($this->values())])['success']);

        // Settings in the request never reach the action; a guest needs no nonce.
        $success = $this->native(['email_to' => 'attacker@example.invalid', 'submit_actions' => ['email']] + $base);
        self::assertTrue($success['success'], wp_json_encode($success));
        self::assertSame('Form submitted successfully!', $success['data']['message']);
        self::assertSame('authored@example.invalid', json_decode(trim(file_get_contents($mail)), true)['to']);

        // A filled honeypot gets the normal answer and sends nothing.
        file_put_contents($mail, '');
        self::assertTrue($this->native(['elemacy_honeypot' => 'bot'] + $base)['success']);
        self::assertSame('', file_get_contents($mail));

        // A logged-in visitor without a valid nonce is a guest, so a private owner refuses it.
        wp_update_post(['ID' => $id, 'post_status' => 'private']);
        self::assertFalse($this->native($base)['success']);
        self::assertFalse($this->native($base, true)['success']);
        self::assertFalse($this->native($base, true, 'invalid')['success']);
        file_put_contents($users, '');
        self::assertTrue($this->native($base, true, true)['success']);
        self::assertSame(get_user_by('login', 'elemacy_test_admin')->ID, json_decode(trim(file_get_contents($users)), true)['user']);

        wp_update_post(['ID' => $id, 'post_status' => 'publish']);
        file_put_contents($users, '');
        self::assertTrue($this->native($base, true)['success']);
        self::assertSame(0, json_decode(trim(file_get_contents($users)), true)['user'], 'An unverified logged-in request carried the user.');
        unlink($mail);
        unlink($users);
    }

    public function testCachedPopupMarkupStillSubmitsAfterTheTemplateIsEdited(): void
    {
        $popup = $this->documents->create('Cached form popup', 'popup', 'publish', [$this->widget()]);
        wp_set_current_user(0);

        // The markup a page cache would have captured.
        $request = new RequestSimulator();
        $documents = \Elementor\Plugin::$instance->documents;
        try {
            $request->visit(home_url('/'));
            $documents->switch_to_document($documents->get($popup));
            ob_start();
            \Elementor\Plugin::$instance->elements_manager->create_element_instance(json_decode((string) get_post_meta($popup, '_elementor_data', true), true)[0])->print_element();
            $html = ob_get_clean();
        } finally {
            $documents->restore_document();
            $request->restore();
        }
        preg_match_all('/<input type="(?:hidden|text)"(?: id="[^"]+")? name="([a-z_]+)" value="([^"]*)"/', $html, $inputs, PREG_SET_ORDER);
        $posted = [];
        foreach ($inputs as [, $name, $value]) {
            $posted[$name] = html_entity_decode($value);
        }
        // Nothing in it can expire: no nonce, no signed token, no timestamp, so no clock change can stale it.
        self::assertSame(['action' => 'elemacy_form_submit', 'form_source' => 'elementor', 'form_owner' => (string) $popup, 'form_id' => 'form123', 'elemacy_honeypot' => ''], $posted);

        // Later the template is edited (its triggers, and the form's own subject) and saved again.
        update_post_meta($popup, '_elemacy_popup_triggers', [['id' => 'later', 'type' => 'page_load', 'params' => ['delay' => 3]]]);
        $tree = json_decode((string) get_post_meta($popup, '_elementor_data', true), true);
        $tree[0]['settings']['email_subject'] = 'Edited subject';
        update_post_meta($popup, '_elementor_data', wp_slash(wp_json_encode($tree)));
        wp_update_post(['ID' => $popup, 'post_title' => 'Cached form popup (edited)']);

        $directory = dirname($GLOBALS['elemacy_test_environment']['root']);
        $mail = $directory . '/form-native-mail.jsonl';
        file_put_contents($mail, '');
        try {
            $response = $this->native($posted + ['form_fields' => $this->values()]);
            self::assertTrue($response['success'], wp_json_encode($response));
            self::assertSame('Edited subject', json_decode(trim(file_get_contents($mail)), true)['subject'], 'The current saved form was not used.');
        } finally {
            @unlink($mail);
            @unlink($directory . '/form-native-users.jsonl');
        }
    }

    /** @param bool|string|null $nonce true for a valid nonce, a string to send as is. */
    private function native(array $post, bool $authenticated = false, $nonce = null, ?string $forwarded = null): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/form-ajax-request.php'], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes);
        self::assertIsResource($process);
        $input = ['post' => $post, 'ip' => $this->ip, 'authenticated' => $authenticated, 'nonce' => $nonce];
        if (null !== $forwarded) {
            $input['forwarded'] = $forwarded;
        }
        fwrite($pipes[0], wp_json_encode($input));
        fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        $response = json_decode($body, true);
        self::assertIsArray($response, $body);
        return $response;
    }

    /* ── Rate limiting ───────────────────────────────────────────────────── */

    public function testConcurrentLimiterAndExpiry(): void
    {
        $processes = [];
        for ($i = 0; $i < 12; ++$i) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/form-rate-request.php', $this->ip], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        $accepted = 0;
        foreach ($processes as [$process, $pipes]) {
            $body = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $errors);
            self::assertContains(trim($body), ['accepted', 'limited']);
            $accepted += trim($body) === 'accepted' ? 1 : 0;
        }
        self::assertSame(5, $accepted);
        global $wpdb;
        $key = 'elemacy_form_limit_' . hash_hmac('sha256', $this->ip, wp_salt('auth'));
        $wpdb->update($wpdb->options, ['option_value' => (time() - 1) . ':5'], ['option_name' => $key]);
        (new SubmissionRateLimiter())->consume($this->ip);
        self::assertStringEndsWith(':1', $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key)));
    }

    public function testRateLimitIsFilterable(): void
    {
        $policy = static function () {
            return ['limit' => 2, 'window' => 60];
        };
        add_filter(Hooks::FORM_RATE_LIMIT_FILTER, $policy);
        try {
            $limiter = new SubmissionRateLimiter();
            $limiter->consume($this->ip);
            $limiter->consume($this->ip);
            $this->expectException(HttpException::class);
            $limiter->consume($this->ip);
        } finally {
            remove_filter(Hooks::FORM_RATE_LIMIT_FILTER, $policy);
        }
    }

    public function testFilteredClientIpLimitsEachVisitorBehindOneProxy(): void
    {
        $id = $this->page();
        $directory = dirname($GLOBALS['elemacy_test_environment']['root']);
        $base = ['form_source' => 'elementor', 'form_owner' => (string) $id, 'form_id' => 'form123', 'form_fields' => $this->values()];
        $visitor = '203.0.113.' . wp_rand(1, 250);
        for ($i = 0; $i < 5; ++$i) {
            self::assertTrue($this->native($base, false, null, $visitor)['success']);
        }
        self::assertFalse($this->native($base, false, null, $visitor)['success'], 'The visitor was not limited.');
        self::assertTrue($this->native($base, false, null, '198.51.100.7')['success'], 'Another visitor behind the same proxy was limited.');
        @unlink($directory . '/form-native-mail.jsonl');
        @unlink($directory . '/form-native-users.jsonl');
    }
}
