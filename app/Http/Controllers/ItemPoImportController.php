<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ItemPoImportController extends Controller
{
    private const SESSION_KEY   = 'po_import_preview';
    private const ALLOWED_TYPES = ['FG', 'FG/KOMPONEN', 'TRANSIT'];

    public function index(Request $request): View
    {
        $eligibleCount = Item::whereIn('type', self::ALLOWED_TYPES)->count();
        $preview       = $request->session()->get(self::SESSION_KEY);

        return view('items.po-import', compact('eligibleCount', 'preview'));
    }

    /**
     * Unduh Template Excel (.xlsx) pre-filled berisi seluruh item aktif bertipe
     * FG, FG/KOMPONEN, dan TRANSIT. PPIC hanya mengisi kolom I (Qty PO Baru).
     */
    public function template(): StreamedResponse
    {
        $items = Item::with('company')
            ->whereIn('type', self::ALLOWED_TYPES)
            ->orderByRaw("CASE type WHEN 'FG' THEN 1 WHEN 'FG/KOMPONEN' THEN 2 ELSE 3 END")
            ->orderBy('part_name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('DRC Stock Control')
            ->setCompany('PT. Dian Raya Cipta')
            ->setTitle('Template Update Qty PO Bulanan');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Update Qty PO');

        // Judul & Panduan (Baris 1-3)
        $sheet->setCellValueExplicit('A1', 'TEMPLATE IMPORT UPDATE QTY PO BULANAN — DRC STOCK CONTROL', DataType::TYPE_STRING);
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '0F1E33']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->setCellValueExplicit(
            'A2',
            'PETUNJUK: Hanya isi kolom I (Qty PO Baru) dengan angka >= 0. Kosongkan baris yang tidak ingin diubah. Jangan ubah kolom ID / Part No.',
            DataType::TYPE_STRING
        );
        $sheet->mergeCells('A2:I2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9.5, 'color' => ['rgb' => '1E40AF']],
        ]);

        $sheet->setCellValueExplicit(
            'A3',
            'Diunduh: ' . Carbon::now()->format('d M Y, H:i') . ' WIB  ·  Total Item Berlaku PO: ' . $items->count() . ' item (WD & Kanban tidak berubah lewat import ini).',
            DataType::TYPE_STRING
        );
        $sheet->mergeCells('A3:I3');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['size' => 9.5, 'color' => ['rgb' => '475569']],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(8);

        // Header Baris 5
        $headers = [
            'A' => 'ID',
            'B' => 'Part No',
            'C' => 'Part Name',
            'D' => 'Tipe',
            'E' => 'Perusahaan',
            'F' => 'WD',
            'G' => 'Kanban',
            'H' => 'Qty PO Lama',
            'I' => 'Qty PO Baru',
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValueExplicit("{$col}5", $text, DataType::TYPE_STRING);
        }

        $sheet->getStyle('A5:H5')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F1E33']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0F1E33']]],
        ]);
        $sheet->getStyle('I5')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15803D']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '15803D']]],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(24);

        // Isi Item Aktif (Baris 6..N)
        $row = 6;
        foreach ($items as $it) {
            $sheet->setCellValueExplicit("A{$row}", $it->id, DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("B{$row}", $it->part_no ?: '-', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$row}", $it->part_name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$row}", $it->type, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$row}", $it->company->company_code ?? 'INTERNAL', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", (int) $it->working_days, DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("G{$row}", (float) $it->qty_kanban, DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("H{$row}", (float) $it->qty_po, DataType::TYPE_NUMERIC);
            $sheet->setCellValue("I{$row}", null);

            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            ]);
            $sheet->getStyle("I{$row}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_RIGHT],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '86EFAC']]],
            ]);

            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("G{$row}:I{$row}")->getNumberFormat()->setFormatCode('#,##0.##');

            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        $widths = ['A' => 8, 'B' => 18, 'C' => 32, 'D' => 14, 'E' => 16, 'F' => 8, 'G' => 11, 'H' => 15, 'I' => 16];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $sheet->freezePane('A6');

        $filename = 'Template_Update_QtyPO_' . Carbon::now()->format('Ymd') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Parsing file .xlsx unggahan PPIC & susun layar Preview perbandingan
     * Qty PO Lama vs Qty PO Baru serta dampak Std Day 1 sebelum disimpan.
     */
    public function preview(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:5120',
        ], [
            'file.required' => 'Pilih file Excel (.xlsx) terlebih dahulu.',
            'file.mimes'    => 'Format file wajib Excel (.xlsx), bukan CSV atau format lain.',
            'file.max'      => 'Ukuran file maksimal 5 MB.',
        ]);

        $uploaded = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($uploaded->getRealPath());
        } catch (Throwable $e) {
            return back()->with('error', 'File Excel tidak dapat dibaca. Pastikan file .xlsx valid dan tidak terproteksi password.');
        }

        $sheet      = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        // Cari baris header (mendukung template resmi di baris 5 maupun sheet sederhana di baris 1)
        $headerRow = null;
        $colId     = null;
        $colPartNo = null;
        $colNewPo  = null;

        $scanMax = min(10, $highestRow);
        for ($r = 1; $r <= $scanMax; $r++) {
            $foundId     = null;
            $foundPartNo = null;
            $foundNewPo  = null;

            for ($c = 1; $c <= $highestCol; $c++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $label     = mb_strtolower(trim((string) $sheet->getCell("{$colLetter}{$r}")->getValue()), 'UTF-8');
                $label     = preg_replace('/\s+/', ' ', $label);

                if ($label === 'id' || $label === 'item id') {
                    $foundId = $colLetter;
                } elseif (in_array($label, ['part no', 'part_no', 'partno'], true)) {
                    $foundPartNo = $colLetter;
                } elseif (in_array($label, ['qty po baru', 'qty_po_baru', 'qty po', 'qty_po'], true)) {
                    $foundNewPo = $colLetter;
                }
            }

            if ($foundNewPo !== null && ($foundId !== null || $foundPartNo !== null)) {
                $headerRow = $r;
                $colId     = $foundId;
                $colPartNo = $foundPartNo;
                $colNewPo  = $foundNewPo;
                break;
            }
        }

        if ($headerRow === null) {
            $spreadsheet->disconnectWorksheets();
            return back()->with(
                'error',
                'Header tabel tidak dikenali. Gunakan Template Excel resmi (memuat kolom ID / Part No dan kolom "Qty PO Baru").'
            );
        }

        // Preload seluruh item aktif untuk pencocokan cepat tanpa N+1
        $activeItems   = Item::with('company')->get();
        $itemsById     = $activeItems->keyBy('id');
        $itemsByPartNo = $activeItems
            ->filter(fn ($it) => !empty($it->part_no))
            ->keyBy(fn ($it) => mb_strtoupper(trim((string) $it->part_no), 'UTF-8'));

        $changes      = [];
        $unchanged    = [];
        $errors       = [];
        $seenItemIds  = [];
        $blankSkipped = 0;

        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $rawId     = $colId ? trim((string) $sheet->getCell("{$colId}{$r}")->getValue()) : '';
            $rawPartNo = $colPartNo ? trim((string) $sheet->getCell("{$colPartNo}{$r}")->getValue()) : '';
            $cellPo    = $sheet->getCell("{$colNewPo}{$r}");

            // Lindungi pembacaan sel formula rusak (mis. =1/0 atau #REF!) agar tidak memicu HTTP 500
            try {
                $rawPoVal = $cellPo->getCalculatedValue();
            } catch (Throwable $e) {
                $errors[] = [
                    'row'     => $r,
                    'ref'     => $rawPartNo ?: ($rawId !== '' ? ('ID #' . $rawId) : "Baris {$r}"),
                    'message' => 'Formula pada kolom Qty PO Baru tidak valid atau menghasilkan error Excel.',
                ];
                continue;
            }

            $rawPoStr = $rawPoVal === null ? '' : trim((string) $rawPoVal);

            // Lewati baris kosong total
            if ($rawId === '' && $rawPartNo === '' && $rawPoStr === '') {
                continue;
            }

            // Jika kolom Qty PO Baru dikosongkan -> lewati aman (tidak diubah)
            if ($rawPoStr === '') {
                $blankSkipped++;
                continue;
            }

            // 1. Cocokkan Item Aktif
            $item = null;
            if ($rawId !== '') {
                if (!ctype_digit($rawId)) {
                    $errors[] = [
                        'row'     => $r,
                        'ref'     => $rawPartNo ?: $rawId,
                        'message' => "Kolom ID \"{$rawId}\" tidak valid (wajib angka bulat).",
                    ];
                    continue;
                }

                $item = $itemsById->get((int) $rawId);
                if (!$item) {
                    $errors[] = [
                        'row'     => $r,
                        'ref'     => $rawPartNo ?: ('ID #' . $rawId),
                        'message' => "Item ID #{$rawId} tidak ditemukan atau sudah dinonaktifkan.",
                    ];
                    continue;
                }

                // Verifikasi silang Part No bila kolom Part No juga terisi agar tidak salah geser baris
                if ($rawPartNo !== '' && $rawPartNo !== '-' && !empty($item->part_no)) {
                    if (mb_strtoupper($rawPartNo, 'UTF-8') !== mb_strtoupper((string) $item->part_no, 'UTF-8')) {
                        $errors[] = [
                            'row'     => $r,
                            'ref'     => "{$rawId} / {$rawPartNo}",
                            'message' => "Ketidakcocokan baris: ID #{$rawId} milik {$item->part_no}, bukan {$rawPartNo}.",
                        ];
                        continue;
                    }
                }
            } elseif ($rawPartNo !== '' && $rawPartNo !== '-') {
                $item = $itemsByPartNo->get(mb_strtoupper($rawPartNo, 'UTF-8'));
                if (!$item) {
                    $errors[] = [
                        'row'     => $r,
                        'ref'     => $rawPartNo,
                        'message' => "Part No \"{$rawPartNo}\" tidak ditemukan pada Master Item aktif.",
                    ];
                    continue;
                }
            } else {
                $errors[] = [
                    'row'     => $r,
                    'ref'     => '-',
                    'message' => 'Kolom ID dan Part No kosong padahal Qty PO Baru terisi.',
                ];
                continue;
            }

            // 2. Tolak item bertipe RM / KOMPONEN (mengikuti BOM, bukan Qty PO)
            if (!in_array($item->type, self::ALLOWED_TYPES, true)) {
                $errors[] = [
                    'row'     => $r,
                    'ref'     => $item->part_no ?: $item->part_name,
                    'message' => "Item bertipe {$item->type} mengikuti perhitungan BOM, tidak menggunakan Qty PO.",
                ];
                continue;
            }

            // 3. Cek duplikasi item dalam 1 file unggahan
            if (isset($seenItemIds[$item->id])) {
                $errors[] = [
                    'row'     => $r,
                    'ref'     => $item->part_no ?: $item->part_name,
                    'message' => "Item duplikat dalam file (sudah muncul di baris {$seenItemIds[$item->id]}).",
                ];
                continue;
            }
            $seenItemIds[$item->id] = $r;

            // 4. Validasi angka Qty PO Baru (>= 0 & <= kapasitas decimal(10,2))
            $normalized = $this->parseNumericPo($rawPoVal);
            if ($normalized === null || $normalized < 0) {
                $errors[] = [
                    'row'     => $r,
                    'ref'     => $item->part_no ?: $item->part_name,
                    'message' => "Nilai Qty PO Baru \"{$rawPoStr}\" tidak valid (wajib angka >= 0).",
                ];
                continue;
            }

            if ($normalized > Item::MAX_QTY) {
                $errors[] = [
                    'row'     => $r,
                    'ref'     => $item->part_no ?: $item->part_name,
                    'message' => "Nilai Qty PO Baru \"{$rawPoStr}\" melebihi batas maksimal 99.999.999,99.",
                ];
                continue;
            }

            $newQtyPo = round($normalized, 2);
            $oldQtyPo = round((float) $item->qty_po, 2);
            $wd       = (int) $item->working_days;
            $kanban   = (float) $item->qty_kanban;

            $oldCalc = Item::calculatePoKanbanStd($oldQtyPo, $wd, $kanban);
            $newCalc = Item::calculatePoKanbanStd($newQtyPo, $wd, $kanban);

            $rowPayload = [
                'row'          => $r,
                'item_id'      => $item->id,
                'part_no'      => $item->part_no ?: '-',
                'part_name'    => $item->part_name,
                'type'         => $item->type,
                'uom'          => $item->uom,
                'company_code' => $item->company->company_code ?? 'INTERNAL',
                'working_days' => $wd,
                'qty_kanban'   => $kanban,
                'old_qty_po'   => $oldQtyPo,
                'new_qty_po'   => $newQtyPo,
                'diff_qty_po'  => round($newQtyPo - $oldQtyPo, 2),
                'old_std_1'    => $oldCalc['std_day_1'],
                'new_std_1'    => $newCalc['std_day_1'],
            ];

            if (abs($newQtyPo - $oldQtyPo) < 0.0001) {
                $unchanged[] = $rowPayload;
            } else {
                $changes[] = $rowPayload;
            }
        }

        $spreadsheet->disconnectWorksheets();

        $request->session()->put(self::SESSION_KEY, [
            'token'         => (string) Str::uuid(),
            'filename'      => $uploaded->getClientOriginalName(),
            'uploaded_at'   => Carbon::now()->format('d M Y, H:i') . ' WIB',
            'changes'       => $changes,
            'unchanged'     => $unchanged,
            'errors'        => $errors,
            'blank_skipped' => $blankSkipped,
        ]);

        return redirect()->route('items.po_import.index');
    }

    /**
     * Eksekusi simpan perubahan Qty PO secara atomik (hanya memperbarui qty_po,
     * std_day_1_po_wd, std_day_1, lalu menyegarkan status stok seluruh item termasuk RM/KOMPONEN via BOM).
     */
    public function commit(Request $request): RedirectResponse
    {
        $preview = $request->session()->get(self::SESSION_KEY);
        $token   = (string) $request->input('preview_token', '');

        if (!$preview || empty($preview['token']) || !hash_equals((string) $preview['token'], $token)) {
            return redirect()->route('items.po_import.index')
                ->with('error', 'Sesi pratinjau import sudah kedaluwarsa. Unggah ulang file Excel Anda.');
        }

        $changes = $preview['changes'] ?? [];
        if (empty($changes)) {
            $request->session()->forget(self::SESSION_KEY);
            return redirect()->route('items.po_import.index')
                ->with('error', 'Tidak ada perubahan Qty PO yang perlu disimpan.');
        }

        if (!empty($preview['errors']) && !$request->boolean('ignore_errors')) {
            return redirect()->route('items.po_import.index')
                ->with('error', 'Masih terdapat baris error pada file Excel. Perbaiki file atau centang opsi lewati baris error untuk melanjutkan.');
        }

        $updatedCount = 0;

        DB::transaction(function () use ($changes, &$updatedCount) {
            foreach ($changes as $row) {
                $item = Item::lockForUpdate()->find((int) $row['item_id']);
                if (!$item || !in_array($item->type, self::ALLOWED_TYPES, true)) {
                    continue;
                }

                $newQtyPo = min(Item::MAX_QTY, max(0, round((float) $row['new_qty_po'], 2)));
                $calc     = Item::calculatePoKanbanStd(
                    $newQtyPo,
                    (int) $item->working_days,
                    (float) $item->qty_kanban
                );

                // Hanya mengubah Qty PO dan hasil turunan Std Day (WD & Kanban tetap utuh)
                $item->update([
                    'qty_po'          => $newQtyPo,
                    'std_day_1_po_wd' => $calc['std_day_1_po_wd'],
                    'std_day_1'       => $calc['std_day_1'],
                ]);

                $updatedCount++;
            }

            // Segarkan status_stock seluruh item (termasuk RM & KOMPONEN yang ikut berubah via BOM)
            InventoryStock::with('item')->get()->each(function ($st) {
                $st->update(['status_stock' => $st->calculateDynamicStatus()]);
            });
        });

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('items.index')
            ->with('success', "Berhasil memperbarui Qty PO pada {$updatedCount} item. Standar stok harian (Std Day 1/2/3) dan status seluruh item telah dihitung ulang.");
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('items.po_import.index')
            ->with('success', 'Pratinjau import Qty PO dibatalkan.');
    }

    private function parseNumericPo(mixed $raw): ?float
    {
        if (is_int($raw) || is_float($raw)) {
            return is_finite((float) $raw) ? (float) $raw : null;
        }

        $str = trim((string) $raw);
        if ($str === '') {
            return null;
        }

        $str = str_replace(' ', '', $str);

        if (is_numeric($str)) {
            return (float) $str;
        }

        // Format Indonesia: 1.500,50 -> 1500.50
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $str)) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
            return is_numeric($str) ? (float) $str : null;
        }

        // Format koma desimal tunggal: 1500,5 -> 1500.5
        if (preg_match('/^\d+,\d+$/', $str)) {
            $str = str_replace(',', '.', $str);
            return is_numeric($str) ? (float) $str : null;
        }

        // Format ribuan koma: 1,500.50 -> 1500.50
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $str)) {
            $str = str_replace(',', '', $str);
            return is_numeric($str) ? (float) $str : null;
        }

        return null;
    }
}