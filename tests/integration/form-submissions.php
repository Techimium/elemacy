<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Container;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Http\Exceptions\HttpException;
use Elemacy\Modules\Widgets\Forms\Services\PublishedFormDefinitionResolver;
use Elemacy\Modules\Widgets\Forms\Services\SubmissionService;
use Elemacy\Modules\Widgets\Forms\Services\SubmissionValidator;
use Elemacy\Modules\Widgets\Forms\Services\SubmissionRateLimiter;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

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
        $this->capture = function ($result, $mail) { $this->mail[] = $mail; return true; };
        add_filter('pre_wp_mail', $this->capture, 999, 2);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_wp_mail', $this->capture, 999);
        foreach ($this->owned as $id) {
            wp_delete_post($id, true);
        }
        global $wpdb;
        $key = 'elemacy_form_limit_' . hash_hmac('sha256', $this->ip, wp_salt('auth'));
        $wpdb->delete($wpdb->options, ['option_name' => $key]);
        parent::tearDown();
    }

    private function settings(): array
    {
        return ['form_fields' => [
            ['type' => 'text', 'label' => 'Name', 'custom_id' => 'name', 'required' => 'yes'],
            ['type' => 'email', 'label' => 'Email', 'custom_id' => 'email', 'required' => 'yes'],
            ['type' => 'checkbox', 'label' => 'Topics', 'options' => "One|one\nTwo|two", 'required' => ''],
            ['type' => 'textarea', 'label' => 'Message', 'custom_id' => 'message', 'required' => ''],
        ], 'submit_actions' => ['email'], 'email_to' => 'authored@example.invalid',
            'email_reply_to' => 'email', 'email_content' => '[name] [field_1] [all-fields]'];
    }

    private function values(): array
    {
        return ['Ada', 'ada@example.invalid', ['one'], "Hello\nWorld"];
    }

    private function page(string $status = 'publish', string $widget_type = 'elemacy-form', string $password = ''): int
    {
        $id = wp_insert_post(['post_type' => 'page', 'post_status' => $status, 'post_title' => 'Form fixture', 'post_password' => $password]);
        $this->owned[] = $id;
        update_post_meta($id, '_elementor_edit_mode', 'builder');
        update_post_meta($id, '_elementor_template_type', 'wp-page');
        update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode([[
            'id' => 'form123', 'elType' => 'widget', 'widgetType' => $widget_type, 'settings' => $this->settings(), 'elements' => [],
        ]])));
        return $id;
    }

    private function submit(int $id, ?array $values = null, string $context = ''): void
    {
        Container::instance()->make(SubmissionService::class)->submit($id, 'form123', $context, $values ?? $this->values(), $this->ip);
    }

    public function testPublishedSavedFormUsesAuthoredRecipientsAndFormatting(): void
    {
        $id = $this->page();
        wp_set_current_user(0);
        $this->submit($id);
        self::assertCount(1, $this->mail);
        self::assertSame('authored@example.invalid', $this->mail[0]['to']);
        self::assertStringContainsString('Ada ada@example.invalid', $this->mail[0]['message']);
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

    /** @dataProvider invalidValues */
    public function testInvalidFieldShapesAndValuesProduceFieldErrors(array $values, string $key): void
    {
        try {
            $this->submit($this->page(), $values);
            self::fail('Malformed field accepted.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey($key, $error->get_errors());
            self::assertCount(0, $this->mail);
        }
    }

    public static function invalidValues(): array
    {
        return [
            [['', 'ada@example.invalid', [], ''], '0'],
            [[['Ada'], 'ada@example.invalid', [], ''], '0'],
            [['Ada', 'bad-email', [], ''], '1'],
            [['Ada', "ada@example.invalid\r\nBcc: other@example.invalid", [], ''], '1'],
            [['Ada', 'ada@example.invalid', 'one', ''], '2'],
            [['Ada', 'ada@example.invalid', ['unconfigured'], ''], '2'],
            [['Ada', 'ada@example.invalid', [['nested']], ''], '2'],
            [['Ada', 'ada@example.invalid', [], '', 'extra'], '4'],
        ];
    }

    public function testSavedActionSelectionIsValidatedBeforeAnyMail(): void
    {
        $id = $this->page();
        $tree = json_decode(get_post_meta($id, '_elementor_data', true), true);
        $tree[0]['settings']['submit_actions'] = ['email', 'unsupported'];
        update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode($tree)));
        try {
            $this->submit($id);
            self::fail('Unsupported action was accepted.');
        } catch (ValidationException $error) {
            self::assertCount(0, $this->mail);
        }
        $tree[0]['settings']['submit_actions'] = ['email', 'email'];
        update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode($tree)));
        $this->submit($id);
        self::assertCount(1, $this->mail);
    }

    public function testTransportFailureIsNotSuccess(): void
    {
        $id = $this->page();
        $fail = static function () { return false; };
        add_filter('pre_wp_mail', $fail, 1000);
        try {
            $this->expectException(HttpException::class);
            $this->submit($id);
        } finally {
            remove_filter('pre_wp_mail', $fail, 1000);
        }
    }

    public function testLibraryPlacementIsBoundToOwnerWidgetAndHost(): void
    {
        $host = $this->page();
        $popup = $this->documents->create('Form popup', 'popup', 'publish', [[
            'id' => 'form123', 'elType' => 'widget', 'widgetType' => 'elemacy-form', 'settings' => $this->settings(), 'elements' => [],
        ]]);
        wp_set_current_user(0);
        $request = new RequestSimulator();
        try {
            $request->visit(get_permalink($host));
            $resolver = Container::instance()->make(PublishedFormDefinitionResolver::class);
            $token = $resolver->context($popup, 'form123');
            self::assertNotEmpty($token);
            $native = $this->native([
                'post_id' => (string) $popup, 'widget_id' => 'form123',
                'form_field' => $this->values(), 'elemacy_form_context' => $token,
            ]);
            self::assertTrue($native['success'], wp_json_encode($native));
            $this->submit($popup, null, $token);
            self::assertCount(1, $this->mail);
            foreach (['', $token . 'forged'] as $bad) {
                try {
                    $this->submit($popup, null, $bad);
                    self::fail('Forged placement accepted.');
                } catch (ValidationException $error) {
                    self::assertCount(1, $this->mail);
                }
            }
            wp_update_post(['ID' => $host, 'post_status' => 'private']);
            $this->expectException(ValidationException::class);
            $this->submit($popup, null, $token);
        } finally {
            $request->restore();
        }
    }

    public function testNativeTransportRejectsBrowserSettingsAndMalformedRequestsBeforeMail(): void
    {
        $id = $this->page();
        $evidence = dirname($GLOBALS['elemacy_test_environment']['root']) . '/form-native-mail.jsonl';
        file_put_contents($evidence, '');
        $base = ['post_id' => (string) $id, 'widget_id' => 'form123', 'form_field' => $this->values()];
        foreach ([
            ['email_to' => 'attacker@example.invalid'], ['submit_actions' => ['email']],
            ['_wpnonce' => 'invalid'],
            ['post_id' => [$id]], ['widget_id' => 'another'], ['form_field' => 'bad-shape'],
            ['form_field' => ['Ada', 'bad-email']],
        ] as $changed) {
            $result = $this->native($changed + $base);
            self::assertFalse($result['success']);
            self::assertSame('', file_get_contents($evidence));
        }
        $success = $this->native($base);
        self::assertTrue($success['success'], wp_json_encode($success));
        $mail = json_decode(trim(file_get_contents($evidence)), true);
        self::assertSame('authored@example.invalid', $mail['to']);
        wp_update_post(['ID' => $id, 'post_status' => 'private']);
        self::assertFalse($this->native($base)['success']);
        self::assertTrue($this->native($base, true)['success']);
        unlink($evidence);
    }

    private function native(array $post, bool $authenticated = false): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/form-ajax-request.php'], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes);
        self::assertIsResource($process);
        fwrite($pipes[0], wp_json_encode(['post' => $post, 'ip' => $this->ip, 'authenticated' => $authenticated]));
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
}
