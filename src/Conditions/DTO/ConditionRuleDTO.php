<?php

namespace Elemacy\Conditions\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\DTO;

/**
 * A single display-condition rule attached to a template.
 *
 * This is the canonical shape passed to {@see \Elemacy\Conditions\ConditionInterface::check()},
 * evaluated by the resolver, and persisted (as an array) by the ConditionService.
 */
class ConditionRuleDTO extends DTO
{
    /** @var string */
    public $id = '';

    /** @var string */
    public $type = '';

    /** @var string include|exclude */
    public $operator = 'include';

    /** @var string */
    public $value = '';

    public function is_exclude(): bool
    {
        return $this->operator === 'exclude';
    }

    public function is_include(): bool
    {
        return $this->operator === 'include';
    }

    /**
     * @return array{id: string, type: string, operator: string, value: string}
     */
    public function to_array(): array
    {
        return [
            'id'       => $this->id,
            'type'     => $this->type,
            'operator' => $this->operator,
            'value'    => $this->value,
        ];
    }

    /**
     * Hydrate a list of rules from their stored array form.
     *
     * @param array $rules
     * @return self[]
     */
    public static function collection(array $rules): array
    {
        return array_values(array_map(
            static fn($rule): self => static::from_array(is_array($rule) ? $rule : []),
            $rules
        ));
    }

    /**
     * Serialize a list of rules (DTOs and/or arrays) back to plain arrays.
     *
     * @param iterable $rules
     * @return array<int, array{id: string, type: string, operator: string, value: string}>
     */
    public static function to_arrays(iterable $rules): array
    {
        $out = [];

        foreach ($rules as $rule) {
            if ($rule instanceof static) {
                $out[] = $rule->to_array();
            } elseif (is_array($rule)) {
                $out[] = static::from_array($rule)->to_array();
            }
        }

        return $out;
    }
}
