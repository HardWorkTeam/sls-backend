<?php

namespace App\Support;

/**
 * Helpers for building spreadsheet-safe CSV exports.
 */
class Csv
{
    /**
     * Characters that make Excel/Sheets treat a cell as a formula. A value
     * beginning with any of these is attacker-controllable data (e.g. a guest
     * name entered via public RSVP), so we prefix it with a single quote to
     * force the spreadsheet to read it as literal text — defusing CSV/formula
     * injection (=HYPERLINK(...), =cmd|'/c ...'!A1, etc.).
     */
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Neutralize a single cell value against formula injection.
     */
    public static function cell(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], self::FORMULA_TRIGGERS, true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Neutralize every value in a row.
     *
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    public static function row(array $row): array
    {
        return array_map(self::cell(...), $row);
    }
}
