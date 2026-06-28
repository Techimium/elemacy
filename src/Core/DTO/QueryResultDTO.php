<?php

namespace Elemacy\Core\DTO;

defined('ABSPATH') || exit;

/**
 * A single live-search result for the elemacy_query control: the stored value and
 * the label shown in the dropdown.
 */
class QueryResultDTO extends DTO
{
    /** @var int|string */
    public $id = 0;

    /** @var string */
    public $text = '';

    /**
     * @return array{id: int|string, text: string}
     */
    public function to_array(): array
    {
        return [
            'id'   => $this->id,
            'text' => $this->text,
        ];
    }

    /**
     * Serialize a list of results to the plain `[{id, text}, ...]` shape select2 expects.
     *
     * @param iterable<self> $results
     *
     * @return array<int, array{id: int|string, text: string}>
     */
    public static function to_arrays(iterable $results): array
    {
        $out = [];

        foreach ($results as $result) {
            if ($result instanceof static) {
                $out[] = $result->to_array();
            }
        }

        return $out;
    }
}
