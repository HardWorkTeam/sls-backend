<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Helper for building formatted Excel (.xlsx) exports with proper table
 * styling — header row, borders, auto-sized columns, and alternating
 * row colors so the file looks polished when opened.
 */
class Excel
{
    /**
     * Build an XLSX binary string from a header row and data rows.
     *
     * @param  list<string>          $headers   Column header labels.
     * @param  list<list<mixed>>     $rows      Data rows (same column count as headers).
     * @param  string                $sheetName Worksheet tab name.
     */
    public static function build(array $headers, array $rows, string $sheetName = 'Sheet1'): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetName, 0, 31)); // Excel limits tab names to 31 chars.

        $columnCount = count($headers);
        $lastCol = self::colLetter($columnCount);

        // --- Write header row ---
        foreach ($headers as $i => $header) {
            $cell = self::colLetter($i + 1) . '1';
            $sheet->setCellValue($cell, $header);
        }

        // Style the header row: bold white text on a dark teal background.
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2D6A4F'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // --- Write data rows ---
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2; // row 1 = header, data starts at 2.
            foreach ($row as $colIndex => $value) {
                $cell = self::colLetter($colIndex + 1) . $excelRow;
                $sheet->setCellValue($cell, $value ?? '');
            }
        }

        $lastRow = count($rows) + 1;

        // --- Zebra-stripe the data rows ---
        for ($r = 2; $r <= $lastRow; $r++) {
            $range = "A{$r}:{$lastCol}{$r}";
            if ($r % 2 === 0) {
                $sheet->getStyle($range)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFF0FFF4'],
                    ],
                ]);
            }
            $sheet->getStyle($range)->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // --- Borders on the entire table ---
        if ($lastRow >= 1) {
            $tableRange = "A1:{$lastCol}{$lastRow}";
            $sheet->getStyle($tableRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF999999'],
                    ],
                    'outline' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['argb' => 'FF333333'],
                    ],
                ],
            ]);
        }

        // --- Auto-size columns ---
        for ($col = 1; $col <= $columnCount; $col++) {
            $sheet->getColumnDimension(self::colLetter($col))
                ->setAutoSize(true);
        }

        // Freeze the header row so it stays visible when scrolling.
        $sheet->freezePane('A2');

        // Auto-filter so users can sort/filter any column.
        if ($lastRow >= 1) {
            $sheet->setAutoFilter("A1:{$lastCol}{$lastRow}");
        }

        return self::toXlsxString($spreadsheet);
    }

    /**
     * Read a spreadsheet file (CSV or XLSX) and return the header row and
     * data rows as plain arrays. Uses PhpSpreadsheet's IOFactory so the
     * same import logic works regardless of file format.
     *
     * @return array{header: list<string>, rows: list<list<string|null>>}|null
     *         Null when the file cannot be read or is empty.
     */
    public static function readRows(string $filePath): ?array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable) {
            return null;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        if (count($data) === 0) {
            return null;
        }

        // First row is the header.
        $header = array_map(
            fn ($col) => strtolower(trim((string) ($col ?? ''))),
            array_shift($data),
        );

        // Strip a UTF-8 BOM that some editors/Excel prepend.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $rows = array_map(
            fn (array $row) => array_map(
                fn ($cell) => $cell !== null ? trim((string) $cell) : null,
                $row,
            ),
            $data,
        );

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * Convert a 1-based column index to a letter (1→A, 26→Z, 27→AA, …).
     */
    private static function colLetter(int $col): string
    {
        $letter = '';
        while ($col > 0) {
            $col--;
            $letter = chr(65 + ($col % 26)) . $letter;
            $col = intdiv($col, 26);
        }

        return $letter;
    }

    /**
     * Render a Spreadsheet object to an XLSX binary string.
     */
    private static function toXlsxString(Spreadsheet $spreadsheet): string
    {
        $writer = new Xlsx($spreadsheet);

        $stream = fopen('php://temp', 'r+b');
        $writer->save($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        $spreadsheet->disconnectWorksheets();

        return $contents === false ? '' : $contents;
    }
}
