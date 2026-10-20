<?php

namespace Elemacy\Modules\Popups\Requests;

defined('ABSPATH') || exit;

use Elemacy\Http\Request;
use Elemacy\Http\Sanitizer;
use Elemacy\Modules\Popups\Support\RuleTriggerParams;
use Elemacy\TemplateLibrary\TypeRegistry;

class CreatePopupRequest extends Request
{
    public function rules()
    {
        $rules = [
            'title' => 'required|string',
            'type' => 'required|string|in:' . implode(',', TypeRegistry::instance()->names_in_group('popup')),
            'status' => 'nullable|string|in:publish,draft,trash',
            'conditions' => 'nullable|array',
            'conditions.*.id' => 'string',
            'conditions.*.type' => 'string',
            'conditions.*.operator' => 'required|string|in:include,exclude',
            'conditions.*.value' => 'string',
            'triggers' => 'nullable|array',
            'rules' => 'nullable|array',
            'rules.*.id' => 'string',
            'rules.*.type' => 'required|string',
            'rules.*.params' => 'nullable|array',
        ];

        // A request may leave triggers out: the popup then starts with the
        // default Page Load trigger (PopupService::create()). Each entry is
        // checked only when a list is sent, since the validator would read a
        // missing list as a missing required `triggers.*.type`.
        if ($this->has('triggers')) {
            $rules += [
                'triggers.*.id' => 'string',
                'triggers.*.type' => 'required|string',
                'triggers.*.params' => 'nullable|array',
            ];
        }

        return $rules;
    }

    public function filters()
    {
        return [
            'title' => Sanitizer::TEXT,
            'type' => Sanitizer::TEXT,
            'status' => Sanitizer::TEXT,
            'conditions' => Sanitizer::ARRAY,
            'conditions.*.id' => Sanitizer::TEXT,
            'conditions.*.type' => Sanitizer::TEXT,
            'conditions.*.operator' => Sanitizer::TEXT,
            'conditions.*.value' => Sanitizer::TEXT,
            'triggers' => Sanitizer::ARRAY,
            'triggers.*.id' => Sanitizer::TEXT,
            'triggers.*.type' => Sanitizer::TEXT,
            'triggers.*.params' => Sanitizer::ARRAY,
            'rules' => Sanitizer::ARRAY,
            'rules.*.id' => Sanitizer::TEXT,
            'rules.*.type' => Sanitizer::TEXT,
            'rules.*.params' => Sanitizer::ARRAY,
        ];
    }

    /**
     * The rules() and filters() only check that params is an array. The rest is
     * done here, at the request boundary, before the popup is ever saved: a type
     * that is not registered is refused, a params shape that tries to smuggle a
     * reserved discriminator key is rejected (the same check
     * RuleDTO/TriggerDTO::collection_or_fail() runs again at the write boundary
     * regardless of caller), and text parameters are cleaned without altering
     * percent-encoded sequences.
     */
    public function clean()
    {
        return RuleTriggerParams::clean_payload(parent::clean());
    }
}
