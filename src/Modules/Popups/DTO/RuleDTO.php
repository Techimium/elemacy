<?php

namespace Elemacy\Modules\Popups\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\DTO;

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
