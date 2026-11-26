<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExportService
{
    private const COLOR_NAVY      = '0F1E33';
    private const COLOR_SUB_TEXT  = '475569';
    private const COLOR_ZEBRA     = 'F8FAFC';
    private const COLOR_TOTAL_BG  = 'E2E8F0';
    private const COLOR_BORDER    = 'CBD5E1';

    /**
     * Unduh file .xlsx 1 sheet dengan tabel asli berformat (bukan CSV).
     *
     * @param  array<int, array{label:string, type?:string, width?:int}>  $columns
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, mixed>|null  $totalsRow
     */
    public function downloadSingleSheet(
        string $filename,
        string $sheetTitle,
        string $reportTitle,
        string $filterSummary,
        array $columns,
        array $rows,
        ?array $totalsRow = null
    ): StreamedResponse {
        return $this->downloadMultiSheet($filename, [
            [
                'sheet_title'    => $sheetTitle,
                'report_title'   => $reportTitle,
                'filter_summary' => $filterSummary,
                'columns'        => $columns,
                'rows'           => $rows,
                'totals_row'     => $totalsRow,
            ],
        ]);
    }

    /**
     * Unduh file .xlsx multi-sheet.
     *
     * @param  array<int, array{
     *     sheet_title:string,
     *     report_title:string,
     *     filter_summary?:string,
     *     columns:array<int, array{label:string, type?:string, width?:int}>,
     *     rows:array<int, array<int, mixed>>,
     *     totals_row?:array<int, mixed>|null
     * }>  $sheets
     */
    public function downloadMultiSheet(string $filename, array $sheets): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('DRC Stock Control')
            ->setCompany('PT. Dian Raya Cipta')
            ->setTitle(pathinfo($filename, PATHINFO_FILENAME));

        $spreadsheet->getDefaultStyle()->getFont()
            ->setName('Calibri')
            ->setSize(10);

        $exportedAt = Carbon::now()->format('d M Y, H:i') . ' WIB';
        $exportedBy = Auth::user()?->name ?? 'PPIC';

        foreach ($sheets as $index => $def) {
            $sheet = $index === 0
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet();

            $this->populateSheet(
                $sheet,
                $def['sheet_title'] ?? ('Sheet ' . ($index + 1)),
                $def['report_title'] ?? 'Laporan DRC Stock Control',
                $def['filter_summary'] ?? 'Semua Data (Tanpa Filter)',
                $def['columns'] ?? [],
                $def['rows'] ?? [],
                $def['totals_row'] ?? null,
                $exportedAt,
                $exportedBy
            );
        }

        $spreadsheet->setActiveSheetIndex(0);

        $safeName = str_ends_with(strtolower($filename), '.xlsx') ? $filename : ($filename . '.xlsx');

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $safeName, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma'              => 'public',
        ]);
    }

    /**
     * @param  array<int, array{label:string, type?:string, width?:int}>  $columns
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, mixed>|null  $totalsRow
     */
    private function populateSheet(
        Worksheet $sheet,
        string $sheetTitle,
        string $reportTitle,
        string $filterSummary,
        array $columns,
        array $rows,
        ?array $totalsRow,
        string $exportedAt,
        string $exportedBy
    ): void {
        $cleanTitle = preg_replace('/[\\\\\/\?\*\[\]\:]/', ' ', $sheetTitle) ?: 'Data';
        $sheet->setTitle(mb_substr(trim($cleanTitle), 0, 31));

        $colCount = max(1, count($columns));
        $lastCol  = Coordinate::stringFromColumnIndex($colCount);

        // Baris 1-3: Judul, Metadata, & Filter Aktif
        $sheet->setCellValueExplicit('A1', $reportTitle, DataType::TYPE_STRING);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 13,
                'color' => ['rgb' => self::COLOR_NAVY],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $metaLine = "PT. Dian Raya Cipta  ·  Diekspor: {$exportedAt}  ·  Oleh: {$exportedBy}  ·  Total Baris: " . count($rows);
        $sheet->setCellValueExplicit('A2', $metaLine, DataType::TYPE_STRING);
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'size'  => 9.5,
                'color' => ['rgb' => self::COLOR_SUB_TEXT],
            ],
        ]);

        $sheet->setCellValueExplicit('A3', 'Filter Aktif: ' . $filterSummary, DataType::TYPE_STRING);
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->applyFromArray([
            'font' => [
                'italic' => true,
                'size'   => 9.5,
                'color'  => ['rgb' => self::COLOR_SUB_TEXT],
            ],
        ]);

        $sheet->getRowDimension(4)->setRowHeight(8);

        // Baris 5: Header Tabel
        $headerRow = 5;
        $maxLens   = [];

        foreach ($columns as $i => $col) {
            $colLetter = Coordinate::stringFromColumnIndex($i + 1);
            $label     = (string) ($col['label'] ?? '');
            $type      = (string) ($col['type'] ?? 'text');

            $sheet->setCellValueExplicit("{$colLetter}{$headerRow}", $label, DataType::TYPE_STRING);
            $maxLens[$i] = mb_strlen($label) + 3;

            $hAlign = match ($type) {
                'number', 'int' => Alignment::HORIZONTAL_RIGHT,
                'center'        => Alignment::HORIZONTAL_CENTER,
                default         => Alignment::HORIZONTAL_LEFT,
            };

            $sheet->getStyle("{$colLetter}{$headerRow}")->getAlignment()->setHorizontal($hAlign);
        }

        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 10,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLOR_NAVY],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => self::COLOR_NAVY],
                ],
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        // Baris 6..N: Data
        $currentRow = $headerRow + 1;

        if (empty($rows)) {
            $sheet->setCellValueExplicit("A{$currentRow}", 'Tidak ada data yang cocok dengan filter yang dipilih.', DataType::TYPE_STRING);
            $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
            $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->applyFromArray([
                'font' => [
                    'italic' => true,
                    'color'  => ['rgb' => self::COLOR_SUB_TEXT],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => self::COLOR_BORDER],
                    ],
                ],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(22);
            $lastDataRow = $currentRow;
        } else {
            foreach ($rows as $rowIndex => $rowValues) {
                foreach ($columns as $i => $col) {
                    $colLetter = Coordinate::stringFromColumnIndex($i + 1);
                    $coord     = "{$colLetter}{$currentRow}";
                    $type      = (string) ($col['type'] ?? 'text');
                    $val       = $rowValues[$i] ?? '';

                    if (($type === 'number' || $type === 'int') && $val !== '' && $val !== null && is_numeric($val)) {
                        $numVal = $type === 'int' ? (int) $val : (float) $val;
                        $sheet->setCellValueExplicit($coord, $numVal, DataType::TYPE_NUMERIC);
                        $fmt = ($type === 'int' || abs($numVal - round($numVal)) < 0.0001)
                            ? '#,##0'
                            : '#,##0.00';
                        $sheet->getStyle($coord)->getNumberFormat()->setFormatCode($fmt);
                        $sheet->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $len = strlen((string) $numVal) + 3;
                    } else {
                        $strVal = ($val === null || $val === '') ? '-' : (string) $val;
                        // TYPE_STRING mencegah angka panjang Part No menjadi notasi ilmiah & mencegah formula injection
                        $sheet->setCellValueExplicit($coord, $strVal, DataType::TYPE_STRING);
                        $hAlign = $type === 'center' ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT;
                        $sheet->getStyle($coord)->getAlignment()->setHorizontal($hAlign);
                        $len = mb_strlen($strVal);
                    }

                    if ($len > ($maxLens[$i] ?? 0)) {
                        $maxLens[$i] = $len;
                    }
                }

                $rowStyle = [
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => self::COLOR_BORDER],
                        ],
                    ],
                ];

                if ($rowIndex % 2 === 1) {
                    $rowStyle['fill'] = [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => self::COLOR_ZEBRA],
                    ];
                }

                $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->applyFromArray($rowStyle);
                $sheet->getRowDimension($currentRow)->setRowHeight(19);
                $currentRow++;
            }

            $lastDataRow = $currentRow - 1;

            // Opsional Baris Total / Ringkasan
            if ($totalsRow !== null) {
                foreach ($columns as $i => $col) {
                    $colLetter = Coordinate::stringFromColumnIndex($i + 1);
                    $coord     = "{$colLetter}{$currentRow}";
                    $type      = (string) ($col['type'] ?? 'text');
                    $val       = $totalsRow[$i] ?? '';

                    if (($type === 'number' || $type === 'int') && $val !== '' && $val !== null && is_numeric($val)) {
                        $numVal = $type === 'int' ? (int) $val : (float) $val;
                        $sheet->setCellValueExplicit($coord, $numVal, DataType::TYPE_NUMERIC);
                        $fmt = ($type === 'int' || abs($numVal - round($numVal)) < 0.0001)
                            ? '#,##0'
                            : '#,##0.00';
                        $sheet->getStyle($coord)->getNumberFormat()->setFormatCode($fmt);
                        $sheet->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    } else {
                        $sheet->setCellValueExplicit($coord, (string) $val, DataType::TYPE_STRING);
                        $hAlign = $type === 'center' ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT;
                        $sheet->getStyle($coord)->getAlignment()->setHorizontal($hAlign);
                    }
                }

                $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['rgb' => self::COLOR_NAVY],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => self::COLOR_TOTAL_BG],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => self::COLOR_NAVY],
                        ],
                    ],
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(21);
            }
        }

        // Lebar Kolom Rapi + AutoFilter + Freeze Header
        foreach ($columns as $i => $col) {
            $colLetter = Coordinate::stringFromColumnIndex($i + 1);
            $width     = isset($col['width'])
                ? (int) $col['width']
                : min(46, max(10, (int) (($maxLens[$i] ?? 12) + 3)));
            $sheet->getColumnDimension($colLetter)->setWidth($width);
        }

        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastDataRow}");
        $sheet->freezePane('A' . ($headerRow + 1));

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
    }
}