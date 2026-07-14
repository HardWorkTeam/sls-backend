<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
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
                'color' => new Color(Color::COLOR_WHITE),
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => new Color('FF2D6A4F'),
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
                $sheet->getStyle($range)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF0FFF4'); // very light green
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
                        'color' => new Color('FFD5D5D5'),
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
