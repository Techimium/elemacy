<?php

namespace Elemacy\Modules\Popups\Requests;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Sanitizer;
use Elemacy\Modules\Popups\Support\RuleTriggerParams;
use Elemacy\TemplateLibrary\TypeRegistry;

class UpdatePopupRequest extends Request
{
    public function rules()
    {
        return [
            'id' => 'required|integer',
            'title' => 'required|string',
            'type' => 'required|string|in:' . implode(',', TypeRegistry::instance()->names_in_group('popup')),
            'status' => 'nullable|string|in:publish,draft,trash',
            'conditions' => 'nullable|array',
            'conditions.*.id' => 'string',
            'conditions.*.type' => 'string',
            'conditions.*.operator' => 'required|string|in:include,exclude',
            'conditions.*.value' => 'string',
            'triggers' => 'nullable|array',
            'triggers.*.id' => 'string',
            'triggers.*.type' => 'required|string',
            'triggers.*.params' => 'nullable|array',
            'rules' => 'nullable|array',
            'rules.*.id' => 'string',
            'rules.*.type' => 'required|string',
            'rules.*.params' => 'nullable|array',
        ];
    }

    public function filters()
    {
        return [
            'id' => Sanitizer::INT,
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
            'triggers.*.params' => Sanitizer::ARRAY_DEEP,
            'rules' => Sanitizer::ARRAY,
            'rules.*.id' => Sanitizer::TEXT,
            'rules.*.type' => Sanitizer::TEXT,
            'rules.*.params' => Sanitizer::ARRAY_DEEP,
        ];
    }

    /**
     * rules()/filters() only check that params is an array; a params shape
     * that tries to smuggle a reserved discriminator key (task 10.2) is
     * rejected here, at the request boundary, before the popup is ever
     * saved — the same check RuleDTO/TriggerDTO::collection_or_fail() runs
     * again at the write boundary regardless of caller.
     */
    public function clean()
    {
        $data = parent::clean();

        foreach (($data['rules'] ?? []) as $rule) {
            RuleTriggerParams::assert_valid('rules', is_array($rule['params'] ?? null) ? $rule['params'] : []);
        }

        foreach (($data['triggers'] ?? []) as $trigger) {
            RuleTriggerParams::assert_valid('triggers', is_array($trigger['params'] ?? null) ? $trigger['params'] : []);
        }

        return $data;
    }
}
