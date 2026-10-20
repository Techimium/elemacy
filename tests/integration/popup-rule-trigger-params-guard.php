<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Modules\Popups\Constants\MetaKeys;
use Elemacy\Modules\Popups\Requests\CreatePopupRequest;
use Elemacy\Modules\Popups\Requests\UpdatePopupRequest;
use Elemacy\Tests\Integration\Fixtures\RealRestRoutes;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RealRestRoutes.php';

/**
 * Task 10.2: CreatePopupRequest/UpdatePopupRequest::clean() rejects a
 * rules/triggers params payload that tries to smuggle a reserved
 * discriminator key ("type"), at the actual HTTP request boundary — the
 * real WP_REST_Request path, not just the RuleDTO/TriggerDTO write boundary
 * these Request classes eventually reach.
 *
 * Every payload below explicitly sends conditions/rules/triggers as (empty
 * where irrelevant) arrays, matching how the real admin app always submits
 * these fields — omitting one entirely trips an unrelated, pre-existing
 * validator quirk (conditions.*.operator's own "required" sub-rule fires
 * against the whole-array key when "conditions" is absent rather than an
 * empty array) that has nothing to do with this task.
 */
final class PopupRuleTriggerParamsGuardTest extends IntegrationTestCase
{
    use RealRestRoutes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->boot_real_routes();
    }

    protected function tearDown(): void
    {
        $this->restore_rest_server();
        parent::tearDown();
    }

    private function rest_request(array $params): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/elemacy/popups');
        foreach (array_merge(['conditions' => [], 'rules' => [], 'triggers' => []], $params) as $key => $value) {
            $request->set_param($key, $value);
        }

        return $request;
    }

    public function testCreatePopupRequestRejectsARuleParamsPayloadThatTriesToOverrideType(): void
    {
        try {
            CreatePopupRequest::from_wp_rest_request($this->rest_request([
                'title' => 'Fixture popup',
                'type' => 'popup',
                'rules' => [
                    ['id' => 'r1', 'type' => 'frequency_cap', 'params' => ['type' => 'hijacked', 'max' => 5]],
                ],
            ]))->clean();

            self::fail('Expected a ValidationException for the forged params.type key.');
        } catch (ValidationException $e) {
            $errors = $e->get_errors();
            self::assertArrayHasKey('rules', $errors, 'Must be rejected against the "rules" field, not some other unrelated validation failure.');
            self::assertStringContainsString('reserved', $errors['rules'][0]);
        }
    }

    public function testCreatePopupRequestRejectsATriggerParamsPayloadThatTriesToOverrideType(): void
    {
        try {
            CreatePopupRequest::from_wp_rest_request($this->rest_request([
                'title' => 'Fixture popup',
                'type' => 'popup',
                'triggers' => [
                    ['id' => 't1', 'type' => 'page_load', 'params' => ['type' => 'hijacked']],
                ],
            ]))->clean();

            self::fail('Expected a ValidationException for the forged params.type key.');
        } catch (ValidationException $e) {
            $errors = $e->get_errors();
            self::assertArrayHasKey('triggers', $errors, 'Must be rejected against the "triggers" field, not some other unrelated validation failure.');
            self::assertStringContainsString('reserved', $errors['triggers'][0]);
        }
    }

    public function testCreatePopupRequestStillAcceptsLegitimateRuleAndTriggerParamsUnchanged(): void
    {
        $data = CreatePopupRequest::from_wp_rest_request($this->rest_request([
            'title' => 'Fixture popup',
            'type' => 'popup',
            'rules' => [
                ['id' => 'r1', 'type' => 'frequency_cap', 'params' => ['max' => 5]],
            ],
            'triggers' => [
                ['id' => 't1', 'type' => 'page_load', 'params' => []],
            ],
        ]))->clean();

        self::assertSame('frequency_cap', $data['rules'][0]['type']);
        self::assertSame(5, $data['rules'][0]['params']['max']);
        self::assertSame('page_load', $data['triggers'][0]['type']);
    }

    public function testUpdatePopupRequestRejectsARuleParamsPayloadThatTriesToOverrideType(): void
    {
        try {
            UpdatePopupRequest::from_wp_rest_request($this->rest_request([
                'id' => 1,
                'title' => 'Fixture popup',
                'type' => 'popup',
                'rules' => [
                    ['id' => 'r1', 'type' => 'frequency_cap', 'params' => ['type' => 'hijacked', 'max' => 5]],
                ],
            ]))->clean();

            self::fail('Expected a ValidationException for the forged params.type key.');
        } catch (ValidationException $e) {
            $errors = $e->get_errors();
            self::assertArrayHasKey('rules', $errors);
            self::assertStringContainsString('reserved', $errors['rules'][0]);
        }
    }

    /* ── The real routes, dispatched as the admin app does ─────────────── */

    private function dispatch(string $method, string $route, array $body = []): WP_REST_Response
    {
        $request = new WP_REST_Request($method, $route);
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode($body));

        return rest_do_request($request);
    }

    /** A saved popup, as the admin app's payload shape. */
    private function payload(array $overrides = []): array
    {
        return array_merge(['title' => 'Params popup', 'type' => 'popup', 'status' => 'draft', 'conditions' => [], 'triggers' => [], 'rules' => []], $overrides);
    }

    private function create_through_rest(array $overrides = []): int
    {
        $response = $this->dispatch('POST', '/elemacy/popups', $this->payload($overrides));
        self::assertSame(201, $response->get_status(), wp_json_encode($response->get_data()));
        $id = (int) $response->get_data()['data']['id'];
        $this->documents->adopt($id);

        return $id;
    }

    public function testAnUnknownTriggerOrRuleTypeIsRefusedNamingItAndLeavesThePopupUnchanged(): void
    {
        $id = $this->create_through_rest(['triggers' => [['id' => 't1', 'type' => 'page_load', 'params' => ['delay' => 3]]]]);
        $before = get_post_meta($id, MetaKeys::TRIGGERS, true);

        $response = $this->dispatch('PUT', '/elemacy/popups/' . $id, $this->payload([
            'id' => $id,
            'title' => 'Changed title',
            'triggers' => [['id' => 't2', 'type' => 'made_up', 'params' => []]],
        ]));
        self::assertSame(422, $response->get_status(), wp_json_encode($response->get_data()));
        self::assertStringContainsString('made_up', wp_json_encode($response->get_data()), 'The error names the unknown type.');
        self::assertSame($before, get_post_meta($id, MetaKeys::TRIGGERS, true), 'The saved triggers are untouched.');
        self::assertSame('Params popup', get_the_title($id), 'The rejected save changed nothing, the title included.');

        $response = $this->dispatch('POST', '/elemacy/popups', $this->payload(['rules' => [['id' => 'r1', 'type' => 'no_such_rule', 'params' => []]]]));
        self::assertSame(422, $response->get_status());
        self::assertStringContainsString('no_such_rule', wp_json_encode($response->get_data()));
    }

    public function testAPlaceholderTypeIsAcceptedAndKeptUnchangedAcrossATitleOnlyEdit(): void
    {
        // elemacy_fixture/* are registered as non-functional stand-ins whether or not Pro is active.
        $rule = ['id' => 'r1', 'type' => 'elemacy_fixture/mock_rule', 'params' => ['start' => '2030-01-01T09:00', 'end' => '2030-02-01T09:00', 'unknown_key' => 'kept']];
        $trigger = ['id' => 't1', 'type' => 'elemacy_fixture/mock_trigger', 'params' => ['percentage' => '40']];
        $id = $this->create_through_rest(['rules' => [$rule], 'triggers' => [$trigger]]);

        self::assertSame([$rule], get_post_meta($id, MetaKeys::RULES, true), 'A placeholder rule is saved as sent.');
        self::assertSame([$trigger], get_post_meta($id, MetaKeys::TRIGGERS, true));

        // The admin app sends the whole popup back when only the title changed.
        $response = $this->dispatch('PUT', '/elemacy/popups/' . $id, $this->payload(['id' => $id, 'title' => 'Renamed', 'rules' => [$rule], 'triggers' => [$trigger]]));
        self::assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        self::assertSame('Renamed', get_the_title($id));
        self::assertSame([$rule], get_post_meta($id, MetaKeys::RULES, true), 'The rule and its parameters survive a title-only edit.');
        self::assertSame([$trigger], get_post_meta($id, MetaKeys::TRIGGERS, true));
    }

    public function testTextParametersKeepPercentEncodingButLoseMarkupAndLineBreaks(): void
    {
        $id = $this->create_through_rest(['rules' => [[
            'id' => 'r1',
            'type' => 'elemacy_fixture/mock_rule',
            'params' => [
                'url' => 'example.com/?q=a%20b&c=%E2%9C%93',
                'markup' => '<script>alert(1)</script>plain <b>bold</b>',
                'multiline' => "first line\nsecond\tline",
                'list' => ['editor', ' author '],
                'flag' => true,
                'count' => 3,
                'unknown_key' => 'kept',
            ],
        ]]]);

        $params = get_post_meta($id, MetaKeys::RULES, true)[0]['params'];

        self::assertSame('example.com/?q=a%20b&c=%E2%9C%93', $params['url'], 'Percent-encoded sequences round-trip exactly.');
        self::assertSame('plain bold', $params['markup'], 'Markup is stripped, scripts with their content.');
        self::assertSame('first line second line', $params['multiline'], 'Line breaks and tabs are flattened.');
        self::assertSame(['editor', 'author'], $params['list'], 'List items are cleaned too.');
        self::assertTrue($params['flag'], 'Non-text values are untouched.');
        self::assertSame(3, $params['count']);
        self::assertSame('kept', $params['unknown_key'], 'Parameter keys the type does not declare are kept.');
    }

    public function testTheReferrerRuleKeepsAnEncodedSpaceInItsUrl(): void
    {
        $id = $this->create_through_rest(['rules' => [['id' => 'r1', 'type' => 'referrer', 'params' => ['url' => 'example.com/?q=a%20b']]]]);

        self::assertSame('example.com/?q=a%20b', get_post_meta($id, MetaKeys::RULES, true)[0]['params']['url']);

        $response = $this->dispatch('GET', '/elemacy/popups/' . $id);
        $read = json_decode((string) wp_json_encode($response->get_data()), true);
        self::assertSame('example.com/?q=a%20b', $read['data']['rules'][0]['params']['url'], 'And so does what the admin app reads back.');
    }
}
