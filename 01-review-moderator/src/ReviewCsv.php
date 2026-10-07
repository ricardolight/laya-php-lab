<?php

declare(strict_types=1);

namespace ReviewModerator;

/**
 * Reads reviews from a CSV with an `id` and a `text` column. An optional `lang` column and
 * gold-label columns named after the questions (`spam`, `abuse`, `sentiment`) are picked up too.
 */
final class ReviewCsv
{
    /** @return list<Review> */
    public static function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('Cannot read review file "%s".', $path));
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \InvalidArgumentException(sprintf('Cannot open review file "%s".', $path));
        }

        try {
            $header = self::row($handle);
            if ($header === null) {
                throw new \InvalidArgumentException(sprintf('Review file "%s" is empty.', $path));
            }

            $header = array_map(fn (string $column): string => strtolower($column), $header);
            foreach (['id', 'text'] as $required) {
                if (! in_array($required, $header, true)) {
                    throw new \InvalidArgumentException(sprintf('Review file "%s" needs a "%s" column.', $path, $required));
                }
            }

            $reviews = [];
            $rowNumber = 1;
            while (($row = self::row($handle)) !== null) {
                $rowNumber++;
                if ($row === ['']) {
                    continue;
                }

                if (count($row) !== count($header)) {
                    throw new \InvalidArgumentException(sprintf('Row %d of "%s" has %d columns, expected %d.', $rowNumber, $path, count($row), count($header)));
                }

                $record = array_combine($header, $row);
                $gold = array_filter(
                    array_intersect_key($record, array_flip(ReviewQuestions::IDS)),
                    fn (string $label): bool => $label !== '',
                );

                $reviews[] = new Review(
                    $record['id'],
                    $record['text'],
                    ($record['lang'] ?? '') !== '' ? $record['lang'] : null,
                    $gold,
                );
            }

            return $reviews;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return list<string>|null
     */
    private static function row($handle): ?array
    {
        $row = fgetcsv($handle, null, ',', '"', '');
        if ($row === false) {
            return null;
        }

        return array_values(array_map(fn (?string $value): string => trim((string) $value), $row));
    }
}
