<?php

namespace Elemacy\Conditions\DTO;

defined('ABSPATH') || exit;

use Elemacy\Http\DTO\DTO;
use Elemacy\Http\Exceptions\ValidationException;

/** A single display-condition rule attached to a template. */
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
     * Whether $rules name at least one place to display, as opposed to only
     * places not to.
     *
     * @param self[] $rules
     */
    public static function any_include(array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule->is_include()) {
                return true;
            }
        }

        return false;
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
     * Like {@see collection()}, but for the write boundary: an entry that is
     * neither a ConditionRuleDTO instance nor a plain array is unsupported
     * input and must be rejected outright, not silently substituted with a
     * blank/default rule the caller never actually asked for.
     *
     * @param array $rules
     * @return self[]
     * @throws ValidationException When an entry is neither a ConditionRuleDTO nor an array.
     */
    public static function collection_or_fail(array $rules): array
    {
        return array_values(array_map(static function ($rule): self {
            if ($rule instanceof static) {
                return $rule;
            }

            if (is_array($rule)) {
                return static::from_array($rule);
            }

            throw ValidationException::with_errors([
                'conditions' => [esc_html__('One or more conditions has an unsupported shape.', 'elemacy')],
            ]);
        }, $rules));
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
