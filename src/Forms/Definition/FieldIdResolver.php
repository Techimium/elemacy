<?php

namespace Elemacy\Forms\Definition;

defined('ABSPATH') || exit;

/**
 * Gives every authored field a field ID that survives reordering. Rendering
 * and submission both resolve IDs here, so they can never disagree.
 *
 * A field's ID is its author ID when that is valid and not already taken by
 * an earlier field, else its builder's permanent item ID, else (malformed
 * data only) its position.
 */
final class FieldIdResolver
{
    public const PATTERN = '/\A[A-Za-z0-9_-]{1,64}\z/';

    /**
     * @param array[] $rows Authored fields in order. Each may carry 'custom_id'
     *                      (author ID) and '_id' (the builder's item ID).
     * @return array{ids: string[], invalid: array<int, string>, duplicate: array<int, string>}
     *         ids: one per row, same order; invalid/duplicate: author IDs that
     *         were not used, by row position, for an editor notice.
     */
    public static function resolve(array $rows): array
    {
        $taken = [];
        $result = [
            'ids' => [],
            'invalid' => [],
            'duplicate' => [],
        ];

        foreach (array_values($rows) as $index => $row) {
            $row = is_array($row) ? $row : [];
            $author = is_scalar($row['custom_id'] ?? null) ? trim((string) $row['custom_id']) : '';

            if ($author !== '' && !self::is_valid($author)) {
                $result['invalid'][$index] = $author;
                $author = '';
            } elseif ($author !== '' && isset($taken[$author])) {
                $result['duplicate'][$index] = $author;
                $author = '';
            }

            $item = is_scalar($row['_id'] ?? null) ? (string) $row['_id'] : '';
            $id = '';

            foreach ([$author, $item, 'field_' . $index] as $candidate) {
                if ($candidate !== '' && self::is_valid($candidate) && !isset($taken[$candidate])) {
                    $id = $candidate;
                    break;
                }
            }

            for ($suffix = 2; $id === ''; ++$suffix) {
                $candidate = 'field_' . $index . '_' . $suffix;
                $id = isset($taken[$candidate]) ? '' : $candidate;
            }

            $taken[$id] = true;
            $result['ids'][] = $id;
        }

        return $result;
    }

    public static function is_valid(string $id): bool
    {
        return 1 === preg_match(self::PATTERN, $id);
    }
}
