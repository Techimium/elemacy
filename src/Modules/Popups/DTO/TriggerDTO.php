<?php

namespace Elemacy\Modules\Popups\DTO;

defined('ABSPATH') || exit;

use Elemacy\Http\DTO\DTO;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Modules\Popups\Support\RuleTriggerParams;

/**
 * A single trigger attached to a popup (page-load, exit-intent, scroll, etc.).
 */
class TriggerDTO extends DTO
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
     * Hydrate a list of triggers from their stored array form.
     *
     * @param array $triggers
     * @return self[]
     */
    public static function collection(array $triggers): array
    {
        return array_values(array_map(
            static fn($trigger): self => $trigger instanceof static
                ? $trigger
                : static::from_array(is_array($trigger) ? $trigger : []),
            $triggers
        ));
    }

    /**
     * Like {@see collection()}, but for the write boundary: an entry that is
     * neither a TriggerDTO instance nor a plain array is unsupported input
     * and must be rejected outright, not silently substituted with a blank/
     * default trigger the caller never actually asked for.
     *
     * @param array $triggers
     * @return self[]
     * @throws \Elemacy\Http\Exceptions\ValidationException
     */
    public static function collection_or_fail(array $triggers): array
    {
        return array_values(array_map(static function ($trigger): self {
            if ($trigger instanceof static) {
                $dto = $trigger;
            } elseif (is_array($trigger)) {
                $dto = static::from_array($trigger);
            } else {
                throw ValidationException::with_errors([
                    'triggers' => [esc_html__('One or more triggers has an unsupported shape.', 'elemacy')],
                ]);
            }

            RuleTriggerParams::assert_valid('triggers', is_array($dto->params) ? $dto->params : []);

            return $dto;
        }, $triggers));
    }

    /**
     * Serialize a list of triggers (DTOs and/or arrays) back to plain arrays.
     *
     * @param iterable $triggers
     * @return array<int, array{id: string, type: string, params: array}>
     */
    public static function to_arrays(iterable $triggers): array
    {
        $out = [];

        foreach ($triggers as $trigger) {
            if ($trigger instanceof static) {
                $out[] = $trigger->to_array();
            } elseif (is_array($trigger)) {
                $out[] = static::from_array($trigger)->to_array();
            }
        }

        return $out;
    }
}
