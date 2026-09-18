<?php

namespace Elemacy\Modules\Popups\DTO;

defined('ABSPATH') || exit;

use Elemacy\Http\DTO\DTO;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Modules\Popups\Support\RuleTriggerParams;

/**
 * A single advanced display rule attached to a popup
 * (frequency, device, schedule, etc.).
 */
class RuleDTO extends DTO
{
    /** @var string */
    public $id = '';

    /** @var string */
    public $type = '';

    /** @var array */
    public $params = [];

    /**
     * @return array{id: string, type: string, params: array}
     */
    public function to_array(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'params' => (array) $this->params,
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
            static fn($rule): self => $rule instanceof static
                ? $rule
                : static::from_array(is_array($rule) ? $rule : []),
            $rules
        ));
    }

    /**
     * Like {@see collection()}, but for the write boundary: an entry that is
     * neither a RuleDTO instance nor a plain array is unsupported input and
     * must be rejected outright, not silently substituted with a blank/
     * default rule the caller never actually asked for.
     *
     * @param array $rules
     * @return self[]
     * @throws ValidationException
     */
    public static function collection_or_fail(array $rules): array
    {
        return array_values(array_map(static function ($rule): self {
            if ($rule instanceof static) {
                $dto = $rule;
            } elseif (is_array($rule)) {
                $dto = static::from_array($rule);
            } else {
                throw ValidationException::with_errors([
                    'rules' => [esc_html__('One or more rules has an unsupported shape.', 'elemacy')],
                ]);
            }

            RuleTriggerParams::assert_valid('rules', is_array($dto->params) ? $dto->params : []);

            return $dto;
        }, $rules));
    }

    /**
     * Serialize a list of rules (DTOs and/or arrays) back to plain arrays.
     *
     * @param iterable $rules
     * @return array<int, array{id: string, type: string, params: array}>
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
