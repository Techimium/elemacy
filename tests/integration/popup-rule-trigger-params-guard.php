<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Modules\Popups\Requests\CreatePopupRequest;
use Elemacy\Modules\Popups\Requests\UpdatePopupRequest;
use WP_REST_Request;

require_once __DIR__ . '/IntegrationTestCase.php';

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
    private function rest_request(array $params): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/elemacy/v1/popups');
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
}
