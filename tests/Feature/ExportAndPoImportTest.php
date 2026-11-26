<?php

namespace Tests\Feature;

use App\Http\Controllers\ProblemDelayController;
use App\Models\Company;
use App\Models\Inbound;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\LotTracer;
use App\Models\MaterialOut;
use App\Models\ProblemDelay;
use App\Models\Process;
use App\Models\ProductionNg;
use App\Models\StockAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExportAndPoImportTest extends TestCase
{
    use RefreshDatabase;

    private User $ppic;
    private User $staffMaterial;
    private User $staffFg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->ppic = User::create([
            'name'              => 'Budi Santoso (PPIC)',
            'username'          => 'ppic',
            'email'             => 'ppic@drc.co.id',
            'role'              => User::ROLE_PPIC,
            'status'            => User::STATUS_ACTIVE,
            'password'          => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->staffMaterial = User::create([
            'name'              => 'Andi Pratama (Material)',
            'username'          => 'andi.rm',
            'email'             => 'andi.rm@drc.co.id',
            'role'              => User::ROLE_STAFF_MATERIAL,
            'status'            => User::STATUS_ACTIVE,
            'password'          => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->staffFg = User::create([
            'name'              => 'Riko Saputra (FG)',
            'username'          => 'riko.fg',
            'email'             => 'riko.fg@drc.co.id',
            'role'              => User::ROLE_STAFF_FG,
            'status'            => User::STATUS_ACTIVE,
            'password'          => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function readSpreadsheetFromStreamedResponse($response): Spreadsheet
    {
        $tmp = tempnam(sys_get_temp_dir(), 'drc_xlsx_');
        file_put_contents($tmp, $response->streamedContent());

        try {
            return IOFactory::load($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    private function writeSpreadsheetToUploadedFile(Spreadsheet $spreadsheet, string $name = 'po_import.xlsx'): UploadedFile
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('drc_up_', true) . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmp);

        return new UploadedFile(
            $tmp,
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function seedMasterFixture(): array
    {
        $this->actingAs($this->ppic);

        foreach ([
            ['company_code' => 'SUP-KS',  'company_name' => 'PT Krakatau Steel',   'type_company' => 'SUPPLIER'],
            ['company_code' => 'INT-DRC', 'company_name' => 'PT Dian Raya Cipta',  'type_company' => 'INTERNAL'],
            ['company_code' => 'CUS-DPM', 'company_name' => 'PT Dharma Polimetal', 'type_company' => 'CUSTOMER'],
        ] as $comp) {
            $this->post(route('companies.store'), $comp)->assertSessionHasNoErrors();
        }

        $supKs  = Company::where('company_code', 'SUP-KS')->firstOrFail();
        $intDrc = Company::where('company_code', 'INT-DRC')->firstOrFail();
        $cusDpm = Company::where('company_code', 'CUS-DPM')->firstOrFail();

        $this->post(route('processes.store'), ['process_name' => 'STAMPING'])->assertSessionHasNoErrors();
        $pStamp = Process::where('process_name', 'STAMPING')->firstOrFail();

        // RM Coil (KG)
        $this->post(route('items.store'), [
            'part_no'      => null,
            'part_name'    => 'COIL SPHC 2.0',
            'type'         => 'RM',
            'uom'          => 'KG',
            'company_id'   => $supKs->id,
            'qty_po'       => 0,
            'working_days' => 20,
            'qty_kanban'   => 0,
        ])->assertSessionHasNoErrors();

        // Komponen internal
        $this->post(route('items.store'), [
            'part_no'      => 'KMP-PLATE-01',
            'part_name'    => 'PLATE BASE',
            'type'         => 'KOMPONEN',
            'uom'          => 'PCS',
            'company_id'   => $intDrc->id,
            'qty_po'       => 0,
            'working_days' => 20,
            'qty_kanban'   => 0,
        ])->assertSessionHasNoErrors();

        // FG 1: qty_po = 1000, wd = 20, kanban = 50 -> std_day_1 = 50
        $this->post(route('items.store'), [
            'part_no'      => 'FG-BRK-01',
            'part_name'    => 'BRACKET LEFT',
            'type'         => 'FG',
            'uom'          => 'PCS',
            'company_id'   => $cusDpm->id,
            'qty_po'       => 1000,
            'working_days' => 20,
            'qty_kanban'   => 50,
        ])->assertSessionHasNoErrors();

        // FG 2: qty_po = 600, wd = 20, kanban = 20 -> std_day_1 = 40
        $this->post(route('items.store'), [
            'part_no'      => 'FG-BRK-02',
            'part_name'    => 'BRACKET RIGHT',
            'type'         => 'FG',
            'uom'          => 'PCS',
            'company_id'   => $cusDpm->id,
            'qty_po'       => 600,
            'working_days' => 20,
            'qty_kanban'   => 20,
        ])->assertSessionHasNoErrors();

        // TRANSIT: qty_po = 400, wd = 20, kanban = 10 -> std_day_1 = 20
        $this->post(route('items.store'), [
            'part_no'      => 'TRN-BOLT-01',
            'part_name'    => 'BOLT M8 FLANGE',
            'type'         => 'TRANSIT',
            'uom'          => 'PCS',
            'company_id'   => $supKs->id,
            'qty_po'       => 400,
            'working_days' => 20,
            'qty_kanban'   => 10,
        ])->assertSessionHasNoErrors();

        $rm      = Item::where('part_name', 'COIL SPHC 2.0')->firstOrFail();
        $kmp     = Item::where('part_no', 'KMP-PLATE-01')->firstOrFail();
        $fg1     = Item::where('part_no', 'FG-BRK-01')->firstOrFail();
        $fg2     = Item::where('part_no', 'FG-BRK-02')->firstOrFail();
        $transit = Item::where('part_no', 'TRN-BOLT-01')->firstOrFail();

        // BOM SINGLE: RM (usage 2 KG) -> FG-BRK-01
        $this->post(route('boms.store'), [
            'process_id'    => $pStamp->id,
            'trigger_index' => 0,
            'inputs'        => [
                ['item_id' => $rm->id, 'qty_usage' => '2'],
            ],
            'outputs'       => [
                ['item_id' => $fg1->id],
            ],
        ])->assertSessionHas('success');

        return compact('supKs', 'intDrc', 'cusDpm', 'pStamp', 'rm', 'kmp', 'fg1', 'fg2', 'transit');
    }

    public function test_01_semua_rute_export_dan_import_po_terkunci_khusus_ppic(): void
    {
        $m = $this->seedMasterFixture();

        $getRoutes = [
            route('exports.stock'),
            route('exports.lot_tracers'),
            route('exports.inbounds'),
            route('exports.material_outs'),
            route('exports.material_returns'),
            route('exports.productions'),
            route('exports.deliveries'),
            route('exports.return_to_suppliers'),
            route('exports.stock_adjustments'),
            route('exports.production_ngs'),
            route('exports.problem_delays'),
            route('exports.items'),
            route('exports.boms'),
            route('exports.item_history', $m['fg1']->id),
            route('items.po_import.index'),
            route('items.po_import.template'),
        ];

        foreach ([$this->staffMaterial, $this->staffFg] as $staff) {
            $this->actingAs($staff);

            foreach ($getRoutes as $url) {
                $this->get($url)->assertForbidden();
            }

            $this->post(route('items.po_import.preview'))->assertForbidden();
            $this->post(route('items.po_import.commit'))->assertForbidden();
            $this->post(route('items.po_import.cancel'))->assertForbidden();
        }
    }

    public function test_02_export_stock_excel_dan_pdf_mengikuti_filter_aktif_dan_prioritas_0700(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 6, 30, 0, config('app.timezone')));
        $m = $this->seedMasterFixture();

        // Set stok sebelum 07:00:
        // FG-BRK-01 (std1=50, std2=100, std3=150): stock 20 -> VERY LOW (masuk prioritas)
        // FG-BRK-02 (std1=40, std2=80, std3=120): stock 100 -> NORMAL STOCK (tidak masuk prioritas)
        // RM (std1=100): stock 250 -> NORMAL STOCK
        InventoryStock::where('item_id', $m['fg1']->id)->update(['current_stock' => 20, 'status_stock' => 'VERY LOW']);
        InventoryStock::where('item_id', $m['fg2']->id)->update(['current_stock' => 100, 'status_stock' => 'NORMAL STOCK']);
        InventoryStock::where('item_id', $m['rm']->id)->update(['current_stock' => 250, 'status_stock' => 'NORMAL STOCK']);

        // Maju ke 09:00 WIB -> tambah stok FG-BRK-01 lewat adjustment agar stock_07 tetap 20 sementara current_stock = 120
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 9, 0, 0, config('app.timezone')));
        StockAdjustment::create([
            'date_time'       => now(),
            'item_id'         => $m['fg1']->id,
            'user_id'         => $this->ppic->id,
            'adjustment_type' => 'SURPLUS',
            'qty_system'      => 20,
            'qty_actual'      => 120,
            'qty_diff'        => 100,
            'notes'           => 'Temuan rak pagi',
        ]);
        InventoryStock::where('item_id', $m['fg1']->id)->update(['current_stock' => 120, 'status_stock' => 'NORMAL STOCK']);

        $this->actingAs($this->ppic);

        // 1. Export Prioritas Stock 07:00 khusus TARGET_PROD (FG + FG/KOMPONEN)
        // Hanya FG-BRK-01 (stock_07=20, VERY LOW) yang boleh masuk; FG-BRK-02 (NORMAL) & RM/TRANSIT harus tersaring keluar
        $respXlsx = $this->get(route('exports.stock', [
            'format'        => 'xlsx',
            'snapshot_mode' => '07',
            'priority_only' => '1',
            'group'         => 'TARGET_PROD',
        ]))->assertOk();

        $sheet = $this->readSpreadsheetFromStreamedResponse($respXlsx)->getActiveSheet();
        $this->assertStringContainsString('Prioritas Stock 07:00', (string) $sheet->getCell('A3')->getValue());
        $this->assertSame('FG-BRK-01', (string) $sheet->getCell('B6')->getValue());
        $this->assertEquals(20.0, (float) $sheet->getCell('G6')->getValue());   // Stock 07:00
        $this->assertSame('VERY LOW', (string) $sheet->getCell('H6')->getValue());
        $this->assertEquals(120.0, (float) $sheet->getCell('I6')->getValue());  // Current Stock
        $this->assertSame('TOTAL', (string) $sheet->getCell('B7')->getValue()); // Baris 7 langsung TOTAL (hanya 1 item)

        // 2. Export PDF dengan filter tipe FG
        $respPdf = $this->get(route('exports.stock', [
            'format'        => 'pdf',
            'snapshot_mode' => '07',
            'type'          => 'FG',
        ]))->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $respPdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', (string) $respPdf->getContent());
    }

    public function test_03_export_transaksi_dan_kartu_mutasi_sesuai_filter_tanggal_jam_status_dan_bom_type(): void
    {
        $m = $this->seedMasterFixture();

        // Inbound 1: 20 Nov 2026 08:15 (TERCATAT)
        Carbon::setTestNow(Carbon::create(2026, 11, 20, 8, 15, 0, config('app.timezone')));
        $this->actingAs($this->staffMaterial);
        $this->post(route('inbounds.store'), [
            'item_id' => $m['rm']->id,
            'qty'     => '100',
        ])->assertSessionHas('success');

        // Inbound 2: 25 Nov 2026 14:30 -> lalu dikoreksi PPIC menjadi 60 (DIKOREKSI)
        Carbon::setTestNow(Carbon::create(2026, 11, 25, 14, 30, 0, config('app.timezone')));
        $this->post(route('inbounds.store'), [
            'item_id' => $m['rm']->id,
            'qty'     => '50',
        ])->assertSessionHas('success');

        $inb2 = Inbound::orderByDesc('id')->firstOrFail();
        $this->actingAs($this->ppic);
        $this->post(route('inbounds.verify_batch'), [
            'items' => [
                $inb2->id => [
                    'qty_check'        => 'DIKOREKSI',
                    'actual_qty'       => '60',
                    'correction_notes' => 'Sesuai timbangan aktual',
                ],
            ],
        ])->assertSessionHas('success');

        // 1. Export Inbound filter status DIKOREKSI -> hanya 1 baris (qty 60, qty_original 50)
        $respCorr = $this->get(route('exports.inbounds', [
            'qty_check' => 'DIKOREKSI',
        ]))->assertOk();

        $sheetCorr = $this->readSpreadsheetFromStreamedResponse($respCorr)->getActiveSheet();
        $this->assertSame('2026-11-25', (string) $sheetCorr->getCell('B6')->getValue());
        $this->assertEquals(60.0, (float) $sheetCorr->getCell('H6')->getValue());
        $this->assertSame('DIKOREKSI', (string) $sheetCorr->getCell('K6')->getValue());
        $this->assertEquals(50.0, (float) $sheetCorr->getCell('L6')->getValue());
        $this->assertSame('TOTAL', (string) $sheetCorr->getCell('B7')->getValue());

        // 2. Export Inbound filter tanggal & jam pagi (20 Nov 08:00-09:00) -> hanya Inbound 1
        $respDate = $this->get(route('exports.inbounds', [
            'start_date' => '2026-11-20',
            'end_date'   => '2026-11-20',
            'start_time' => '08:00',
            'end_time'   => '09:00',
        ]))->assertOk();

        $sheetDate = $this->readSpreadsheetFromStreamedResponse($respDate)->getActiveSheet();
        $this->assertSame('2026-11-20', (string) $sheetDate->getCell('B6')->getValue());
        $this->assertEquals(100.0, (float) $sheetDate->getCell('H6')->getValue());
        $this->assertSame('TERCATAT', (string) $sheetDate->getCell('K6')->getValue());
        $this->assertSame('TOTAL', (string) $sheetDate->getCell('B7')->getValue());

        // 3. Export Material Out filter bom_type (SINGLE vs MULTI-OUTPUT)
        MaterialOut::create([
            'date_time'        => now(),
            'material_item_id' => $m['rm']->id,
            'target_item_id'   => $m['fg1']->id,
            'user_id'          => $this->staffMaterial->id,
            'lot_number'       => 'S260001',
            'bom_group'        => 'SG-001',
            'qty'              => 10,
            'qty_check'        => 'TERCATAT',
        ]);
        MaterialOut::create([
            'date_time'        => now(),
            'material_item_id' => $m['rm']->id,
            'target_item_id'   => $m['fg2']->id,
            'user_id'          => $this->staffMaterial->id,
            'lot_number'       => 'M260002',
            'bom_group'        => 'MO-002',
            'qty'              => 20,
            'qty_check'        => 'TERCATAT',
        ]);

        $respMo = $this->get(route('exports.material_outs', [
            'bom_type' => 'MULTI-OUTPUT',
        ]))->assertOk();

        $sheetMo = $this->readSpreadsheetFromStreamedResponse($respMo)->getActiveSheet();
        $this->assertSame('M260002', (string) $sheetMo->getCell('D6')->getValue());
        $this->assertSame('MULTI-OUTPUT', (string) $sheetMo->getCell('F6')->getValue());
        $this->assertSame('TOTAL', (string) $sheetMo->getCell('B7')->getValue());

        // 4. Export Kartu Mutasi per Item dengan filter pencarian "timbangan" -> hanya menangkap baris Inbound 2
        $respHist = $this->get(route('exports.item_history', [
            'item' => $m['rm']->id,
            'q'    => 'timbangan',
        ]))->assertOk();

        $sheetHist = $this->readSpreadsheetFromStreamedResponse($respHist)->getActiveSheet();
        $this->assertSame('Inbound', (string) $sheetHist->getCell('D6')->getValue());
        $this->assertEquals(60.0, (float) $sheetHist->getCell('G6')->getValue());
        $this->assertSame('TOTAL', (string) $sheetHist->getCell('B7')->getValue());
    }

    public function test_04_export_form_ng_delay_delivery_dan_stock_adjustment_excel_multi_sheet_dan_pdf(): void
    {
        $m = $this->seedMasterFixture();
        $this->actingAs($this->ppic);

        $ngReasons    = $this->get(route('production_ngs.index'))->viewData('reasons') ?? ['MATERIAL', 'PROSES'];
        $ngReason1    = $ngReasons[0] ?? 'MATERIAL';
        $ngReason2    = $ngReasons[1] ?? $ngReason1;
        $delayReason1 = ProblemDelayController::REASONS[0] ?? 'MATERIAL';
        $delayReason2 = ProblemDelayController::REASONS[1] ?? $delayReason1;

        // Stock Adjustments: 1 LOSS + 1 SURPLUS
        StockAdjustment::create([
            'date_time'       => '2026-11-26 09:00:00',
            'item_id'         => $m['fg1']->id,
            'user_id'         => $this->ppic->id,
            'adjustment_type' => 'LOSS',
            'qty_system'      => 50,
            'qty_actual'      => 45,
            'qty_diff'        => -5,
            'notes'           => 'Selisih opname',
        ]);
        StockAdjustment::create([
            'date_time'       => '2026-11-26 10:00:00',
            'item_id'         => $m['fg2']->id,
            'user_id'         => $this->ppic->id,
            'adjustment_type' => 'SURPLUS',
            'qty_system'      => 20,
            'qty_actual'      => 30,
            'qty_diff'        => 10,
            'notes'           => 'Temuan stok',
        ]);

        // Production NGs
        ProductionNg::create([
            'date_time'      => '2026-11-26 10:15:00',
            'lot_number'     => 'L260001',
            'item_id'        => $m['fg1']->id,
            'user_id'        => $this->staffFg->id,
            'qty_ng'         => 4,
            'problem_reason' => $ngReason1,
            'qty_check'      => 'TERCATAT',
        ]);
        ProductionNg::create([
            'date_time'      => '2026-11-26 11:15:00',
            'lot_number'     => 'L260002',
            'item_id'        => $m['fg2']->id,
            'user_id'        => $this->staffFg->id,
            'qty_ng'         => 7,
            'problem_reason' => $ngReason2,
            'qty_check'      => 'TERCATAT',
        ]);

        // Problem Delays: 1 di bulan Januari, 1 di bulan November
        ProblemDelay::create([
            'date_time'      => '2026-01-15 09:00:00',
            'incident_date'  => '2026-01-15',
            'company_id'     => $m['cusDpm']->id,
            'item_id'        => $m['fg1']->id,
            'user_id'        => $this->ppic->id,
            'problem_reason' => $delayReason1,
        ]);
        ProblemDelay::create([
            'date_time'      => '2026-11-20 13:00:00',
            'incident_date'  => '2026-11-20',
            'company_id'     => $m['cusDpm']->id,
            'item_id'        => $m['fg2']->id,
            'user_id'        => $this->ppic->id,
            'problem_reason' => $delayReason2,
        ]);

        // 1. Stock Adjustment Excel (filter LOSS) + PDF Berita Acara
        $adjXlsx = $this->get(route('exports.stock_adjustments', ['adjustment_type' => 'LOSS']))->assertOk();
        $adjSheet = $this->readSpreadsheetFromStreamedResponse($adjXlsx)->getActiveSheet();
        $this->assertSame('LOSS', (string) $adjSheet->getCell('D6')->getValue());
        $this->assertEquals(-5.0, (float) $adjSheet->getCell('J6')->getValue());
        $this->assertSame('TOTAL', (string) $adjSheet->getCell('B7')->getValue());

        $adjPdf = $this->get(route('exports.stock_adjustments', ['format' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $adjPdf->getContent());

        // 2. Form NG Excel (2 sheet: Detail NG + Rekap Penyebab, filter problem_reason) + PDF
        $ngXlsx = $this->get(route('exports.production_ngs', ['problem_reason' => $ngReason1]))->assertOk();
        $ngBook = $this->readSpreadsheetFromStreamedResponse($ngXlsx);
        $this->assertSame(2, $ngBook->getSheetCount());
        $this->assertSame('Detail NG', $ngBook->getSheet(0)->getTitle());
        $this->assertSame('Rekap Penyebab', $ngBook->getSheet(1)->getTitle());
        $this->assertSame('L260001', (string) $ngBook->getSheet(0)->getCell('D6')->getValue());
        $this->assertSame('TOTAL', (string) $ngBook->getSheet(0)->getCell('B7')->getValue());

        $ngPdf = $this->get(route('exports.production_ngs', ['format' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $ngPdf->getContent());

        // 3. Delay Delivery Excel (2 sheet, filter multi-bulan months=11) + PDF
        $dlXlsx = $this->get(route('exports.problem_delays', ['year' => '2026', 'months' => '11']))->assertOk();
        $dlBook = $this->readSpreadsheetFromStreamedResponse($dlXlsx);
        $this->assertSame(2, $dlBook->getSheetCount());
        $this->assertSame('Detail Delay', $dlBook->getSheet(0)->getTitle());
        $this->assertSame('Rekap Penyebab', $dlBook->getSheet(1)->getTitle());
        $this->assertSame('2026-11-20', (string) $dlBook->getSheet(0)->getCell('B6')->getValue());
        $this->assertSame('TOTAL', (string) $dlBook->getSheet(0)->getCell('B7')->getValue());

        $dlPdf = $this->get(route('exports.problem_delays', ['format' => 'pdf', 'year' => '2026']))->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $dlPdf->getContent());
    }

    public function test_05_import_qty_po_template_preview_validasi_error_dan_commit_rekalkulasi_bom(): void
    {
        $m   = $this->seedMasterFixture();
        $rm  = $m['rm'];
        $fg1 = $m['fg1'];
        $fg2 = $m['fg2'];
        $trn = $m['transit'];

        // Set stok RM = 250 KG. Awalnya FG1 std1=50 -> RM std1 = 50*2 = 100 (std2=200, std3=300) -> RM = NORMAL STOCK
        $rmStock = InventoryStock::where('item_id', $rm->id)->firstOrFail();
        $rmStock->current_stock = 250;
        $rmStock->updateDynamicStatus();
        $this->assertSame('NORMAL STOCK', $rmStock->fresh()->status_stock);

        $this->actingAs($this->ppic);

        // 1. Unduh template Excel pre-filled -> wajib hanya berisi FG, FG/KOMPONEN, TRANSIT (3 item)
        $tplResp  = $this->get(route('items.po_import.template'))->assertOk();
        $tplBook  = $this->readSpreadsheetFromStreamedResponse($tplResp);
        $tplSheet = $tplBook->getActiveSheet();

        $this->assertSame('Qty PO Baru', (string) $tplSheet->getCell('I5')->getValue());
        $templateIds = [];
        for ($r = 6; $r <= $tplSheet->getHighestDataRow(); $r++) {
            $idVal = (int) $tplSheet->getCell("A{$r}")->getValue();
            if ($idVal > 0) {
                $templateIds[$idVal] = $r;
            }
        }
        $this->assertCount(3, $templateIds);
        $this->assertArrayHasKey($fg1->id, $templateIds);
        $this->assertArrayHasKey($fg2->id, $templateIds);
        $this->assertArrayHasKey($trn->id, $templateIds);
        $this->assertArrayNotHasKey($rm->id, $templateIds);

        // 2. Modifikasi file template:
        //    - FG1: ubah Qty PO 1000 -> 4000 (berubah -> std_day_1 jadi 200; RM std_day_1 jadi 400 -> RM jadi VERY LOW)
        //    - FG2: isi sama 600 (unchanged)
        //    - TRANSIT: biarkan kosong (blank_skipped)
        //    - Tambah baris error: RM item (ditolak karena mengikuti BOM) & Part No fiktif
        $tplSheet->setCellValue('I' . $templateIds[$fg1->id], 4000);
        $tplSheet->setCellValue('I' . $templateIds[$fg2->id], 600);
        // TRANSIT dibiarkan kosong di kolom I

        $errRow1 = $tplSheet->getHighestDataRow() + 1;
        $tplSheet->setCellValue("A{$errRow1}", $rm->id);
        $tplSheet->setCellValue("B{$errRow1}", '-');
        $tplSheet->setCellValue("I{$errRow1}", 500);

        $errRow2 = $errRow1 + 1;
        $tplSheet->setCellValue("B{$errRow2}", 'PART-TIDAK-ADA');
        $tplSheet->setCellValue("I{$errRow2}", 100);

        $uploadedFile = $this->writeSpreadsheetToUploadedFile($tplBook);

        try {
            // 3. Preview
            $this->post(route('items.po_import.preview'), ['file' => $uploadedFile])
                ->assertRedirect(route('items.po_import.index'));

            $preview = session('po_import_preview');
            $this->assertNotNull($preview);
            $this->assertCount(1, $preview['changes']);
            $this->assertCount(1, $preview['unchanged']);
            $this->assertSame(1, $preview['blank_skipped']);
            $this->assertCount(2, $preview['errors']);
            $this->assertSame($fg1->id, $preview['changes'][0]['item_id']);
            $this->assertEquals(200.0, (float) $preview['changes'][0]['new_std_1']);

            // 4. Commit tanpa ignore_errors saat masih ada error -> wajib ditolak, DB belum berubah
            $this->post(route('items.po_import.commit'), [
                'preview_token' => $preview['token'],
            ])->assertRedirect(route('items.po_import.index'))
              ->assertSessionHas('error');

            $this->assertEquals(1000.0, (float) $fg1->fresh()->qty_po);

            // 5. Commit dengan ignore_errors=1 -> berhasil update HANYA qty_po & kalkulasi ulang BOM cascade
            $this->post(route('items.po_import.commit'), [
                'preview_token' => $preview['token'],
                'ignore_errors' => '1',
            ])->assertRedirect(route('items.index'))
              ->assertSessionHas('success');

            $fg1Fresh = $fg1->fresh();
            $this->assertEquals(4000.0, (float) $fg1Fresh->qty_po);
            $this->assertSame(20, (int) $fg1Fresh->working_days);    // WD tidak tersentuh
            $this->assertEquals(50.0, (float) $fg1Fresh->qty_kanban); // Kanban tidak tersentuh
            $this->assertEquals(200.0, (float) $fg1Fresh->std_day_1);

            // Verifikasi item lain tidak berubah
            $this->assertEquals(600.0, (float) $fg2->fresh()->qty_po);
            $this->assertEquals(400.0, (float) $trn->fresh()->qty_po);

            // Verifikasi RM status_stock otomatis berubah dari NORMAL STOCK -> VERY LOW akibat BOM cascade (250 < 400)
            $this->assertEquals(400.0, (float) $rm->fresh()->calculated_std_day_1);
            $this->assertSame('VERY LOW', $rmStock->fresh()->status_stock);

            // Sesi preview wajib bersih setelah commit
            $this->assertNull(session('po_import_preview'));
        } finally {
            @unlink($uploadedFile->getPathname());
        }
    }

    public function test_06_halaman_error_403_dan_404_tampil_ramah_berbahasa_indonesia(): void
    {
        // Staff buka Dashboard -> 403 kustom Bahasa Indonesia + tombol kembali ke Portal Scanner
        $this->actingAs($this->staffMaterial)
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertSee('Akses Ditolak')
            ->assertSee('Kembali ke Portal Scanner');

        // PPIC buka rute tidak ada -> 404 kustom Bahasa Indonesia + tombol kembali ke Dashboard
        $this->actingAs($this->ppic)
            ->get('/halaman-tidak-terdaftar-xyz')
            ->assertNotFound()
            ->assertSee('Halaman Tidak Ditemukan')
            ->assertSee('Kembali ke Dashboard');
    }
}