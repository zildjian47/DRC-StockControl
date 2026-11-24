<?php

namespace Tests\Feature;

use App\Models\Bom;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Inbound;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\LotTracer;
use App\Models\MaterialOut;
use App\Models\MaterialReturn;
use App\Models\ProblemDelay;
use App\Models\Process;
use App\Models\Production;
use App\Models\ProductionNg;
use App\Models\ReturnToSupplier;
use App\Models\StockAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IndustrialScenarioTest extends TestCase
{
    use RefreshDatabase;

    private User $ppic;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->ppic = User::create([
            'name'              => 'Budi Santoso (PPIC)',
            'username'          => 'ppic',
            'email'             => 'ppic@drc.co.id',
            'role'              => 'PPIC',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $this->operator = User::create([
            'name'              => 'Andi Pratama (Operator)',
            'username'          => 'operator',
            'email'             => 'operator@drc.co.id',
            'role'              => 'STAFF',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper membangun seluruh Master Data (Company, Process, 12 Item, 4 Tipe BOM)
     * melalui request HTTP asli tanpa seeder.
     */
    private function setupIndustrialMasterViaHttp(): array
    {
        $this->actingAs($this->ppic);

        // 1. Master Companies
        foreach ([
            ['company_code' => 'SUP-KS',  'company_name' => 'PT Krakatau Steel',   'type_company' => 'SUPPLIER'],
            ['company_code' => 'SUP-BJT', 'company_name' => 'PT Baja Teknik',      'type_company' => 'SUPPLIER'],
            ['company_code' => 'INT-DRC', 'company_name' => 'PT Dian Raya Cipta',  'type_company' => 'INTERNAL'],
            ['company_code' => 'CUS-DPM', 'company_name' => 'PT Dharma Polimetal', 'type_company' => 'CUSTOMER'],
        ] as $comp) {
            $this->post(route('companies.store'), $comp)->assertSessionHasNoErrors();
        }

        $supKs  = Company::where('company_code', 'SUP-KS')->firstOrFail();
        $supBjt = Company::where('company_code', 'SUP-BJT')->firstOrFail();
        $intDrc = Company::where('company_code', 'INT-DRC')->firstOrFail();
        $cusDpm = Company::where('company_code', 'CUS-DPM')->firstOrFail();

        // 2. Master Processes
        foreach (['STAMPING', 'SPOT WELDING', 'ASSY WELDING'] as $procName) {
            $this->post(route('processes.store'), ['process_name' => $procName])->assertSessionHasNoErrors();
        }

        $pStamp = Process::where('process_name', 'STAMPING')->firstOrFail();
        $pSpot  = Process::where('process_name', 'SPOT WELDING')->firstOrFail();
        $pAssy  = Process::where('process_name', 'ASSY WELDING')->firstOrFail();

        // 3. Master Items (5 Tipe + RM Coil + Karakter kutip ganda ")
        $itemsData = [
            ['part_no' => null,          'part_name' => 'COIL SPHC 2.3 X 120',   'type' => 'RM',          'uom' => 'KG',     'company_id' => $supKs->id,  'qty_po' => 0,    'working_days' => 20, 'qty_kanban' => 0],
            ['part_no' => null,          'part_name' => 'SHEET SPHC 2.0 X 1000', 'type' => 'RM',          'uom' => 'SHEET',  'company_id' => $supKs->id,  'qty_po' => 0,    'working_days' => 20, 'qty_kanban' => 0],
            ['part_no' => null,          'part_name' => 'PIPA 2" SCH40',         'type' => 'RM',          'uom' => 'BATANG', 'company_id' => $supKs->id,  'qty_po' => 0,    'working_days' => 20, 'qty_kanban' => 0],
            ['part_no' => 'KMP-NUT-M8',  'part_name' => 'WELD NUT M8',           'type' => 'KOMPONEN',    'uom' => 'PCS',    'company_id' => $supBjt->id, 'qty_po' => 0,    'working_days' => 20, 'qty_kanban' => 0],
            ['part_no' => 'KMP-BRK-01',  'part_name' => 'BRACKET BASE',          'type' => 'KOMPONEN',    'uom' => 'PCS',    'company_id' => $intDrc->id, 'qty_po' => 0,    'working_days' => 20, 'qty_kanban' => 0],
            ['part_no' => 'FGK-PLT-01',  'part_name' => 'PLATE LOWER',           'type' => 'FG/KOMPONEN', 'uom' => 'PCS',    'company_id' => $intDrc->id, 'qty_po' => 2000, 'working_days' => 20, 'qty_kanban' => 50],
            ['part_no' => 'FG-STAY-01',  'part_name' => 'STAY LOWER ASSY',       'type' => 'FG',          'uom' => 'PCS',    'company_id' => $cusDpm->id, 'qty_po' => 2000, 'working_days' => 20, 'qty_kanban' => 50],
            ['part_no' => 'FG-GUS-L',    'part_name' => 'GUSSET LH',             'type' => 'FG',          'uom' => 'PCS',    'company_id' => $cusDpm->id, 'qty_po' => 1000, 'working_days' => 20, 'qty_kanban' => 25],
            ['part_no' => 'FG-GUS-R',    'part_name' => 'GUSSET RH',             'type' => 'FG',          'uom' => 'PCS',    'company_id' => $cusDpm->id, 'qty_po' => 1200, 'working_days' => 20, 'qty_kanban' => 25],
            ['part_no' => 'FG-ASSY-A',   'part_name' => 'FRAME ASSY A',          'type' => 'FG',          'uom' => 'PCS',    'company_id' => $cusDpm->id, 'qty_po' => 800,  'working_days' => 20, 'qty_kanban' => 20],
            ['part_no' => 'FG-ASSY-B',   'part_name' => 'FRAME ASSY B',          'type' => 'FG',          'uom' => 'PCS',    'company_id' => $cusDpm->id, 'qty_po' => 800,  'working_days' => 20, 'qty_kanban' => 20],
            ['part_no' => 'TRS-BOLT-01', 'part_name' => 'SPECIAL BOLT M10',      'type' => 'TRANSIT',     'uom' => 'PCS',    'company_id' => $supBjt->id, 'qty_po' => 400,  'working_days' => 20, 'qty_kanban' => 20],
        ];

        foreach ($itemsData as $it) {
            $this->post(route('items.store'), $it)->assertSessionHasNoErrors();
        }

        $items = [
            'coil'   => Item::where('part_name', 'COIL SPHC 2.3 X 120')->firstOrFail(),
            'sheet'  => Item::where('part_name', 'SHEET SPHC 2.0 X 1000')->firstOrFail(),
            'pipa'   => Item::where('part_name', 'PIPA 2" SCH40')->firstOrFail(),
            'nut'    => Item::where('part_no', 'KMP-NUT-M8')->firstOrFail(),
            'brk'    => Item::where('part_no', 'KMP-BRK-01')->firstOrFail(),
            'fgk'    => Item::where('part_no', 'FGK-PLT-01')->firstOrFail(),
            'stay'   => Item::where('part_no', 'FG-STAY-01')->firstOrFail(),
            'gusL'   => Item::where('part_no', 'FG-GUS-L')->firstOrFail(),
            'gusR'   => Item::where('part_no', 'FG-GUS-R')->firstOrFail(),
            'assyA'  => Item::where('part_no', 'FG-ASSY-A')->firstOrFail(),
            'assyB'  => Item::where('part_no', 'FG-ASSY-B')->firstOrFail(),
            'transit'=> Item::where('part_no', 'TRS-BOLT-01')->firstOrFail(),
        ];

        // 4. Master BOM (4 Tipe: SINGLE, MULTI-INPUT, MULTI-OUTPUT pecahan 1/3, KOMBINASI)
        // 4a. SINGLE: COIL (0.5 KG) -> KMP-BRK-01
        $this->post(route('boms.store'), [
            'process_id'    => $pStamp->id,
            'trigger_index' => 0,
            'inputs'        => [
                ['item_id' => $items['coil']->id, 'qty_usage' => '0.5'],
            ],
            'outputs'       => [
                ['item_id' => $items['brk']->id],
            ],
        ])->assertSessionHas('success');

        // 4b. MULTI-INPUT: KMP-BRK-01 (Trigger, 1) + KMP-NUT-M8 (Pendamping, 2) -> FG-STAY-01
        $this->post(route('boms.store'), [
            'process_id'    => $pSpot->id,
            'trigger_index' => 0,
            'inputs'        => [
                ['item_id' => $items['brk']->id, 'qty_usage' => '1'],
                ['item_id' => $items['nut']->id, 'qty_usage' => '2'],
            ],
            'outputs'       => [
                ['item_id' => $items['stay']->id],
            ],
        ])->assertSessionHas('success');

        // 4c. MULTI-OUTPUT (pecahan 1/3): SHEET SPHC -> FG-GUS-L & FG-GUS-R
        $this->post(route('boms.store'), [
            'process_id'    => $pStamp->id,
            'trigger_index' => 0,
            'inputs'        => [
                ['item_id' => $items['sheet']->id],
            ],
            'outputs'       => [
                ['item_id' => $items['gusL']->id, 'usages' => [0 => '1/3']],
                ['item_id' => $items['gusR']->id, 'usages' => [0 => '1/3']],
            ],
        ])->assertSessionHas('success');

        // 4d. KOMBINASI: PIPA 2" SCH40 (Trigger 0.5 & 0.5) + KMP-NUT-M8 (Pendamping 2 & 1) -> FG-ASSY-A & FG-ASSY-B
        $this->post(route('boms.store'), [
            'process_id'    => $pAssy->id,
            'trigger_index' => 0,
            'inputs'        => [
                ['item_id' => $items['pipa']->id],
                ['item_id' => $items['nut']->id],
            ],
            'outputs'       => [
                ['item_id' => $items['assyA']->id, 'usages' => [0 => '0.5', 1 => '2']],
                ['item_id' => $items['assyB']->id, 'usages' => [0 => '0.5', 1 => '1']],
            ],
        ])->assertSessionHas('success');

        return [
            'companies' => compact('supKs', 'supBjt', 'intDrc', 'cusDpm'),
            'processes' => compact('pStamp', 'pSpot', 'pAssy'),
            'items'     => $items,
        ];
    }

    private function stockOf(Item $item): float
    {
        return (float) (InventoryStock::where('item_id', $item->id)->value('current_stock') ?? 0);
    }

    /**
     * KELOMPOK 1: Master Data, Proteksi Integritas, Std Day 1/2/3 Rekursif, & Soft Delete / Restore
     */
    public function test_01_master_data_proteksi_integritas_dan_std_day_rekursif(): void
    {
        $m = $this->setupIndustrialMasterViaHttp();
        $items = $m['items'];
        $companies = $m['companies'];
        $processes = $m['processes'];

        // Cek part karakter khusus kutip ganda tersimpan utuh
        $this->assertSame('PIPA 2" SCH40', $items['pipa']->part_name);

        // Cek hitungan Std Day 1/2/3 rekursif dari FG -> Komponen -> RM (termasuk MO- & CB- sharedMax)
        $this->assertEquals(100.0, $items['stay']->fresh()->calculated_std_day_1);
        $this->assertEquals(50.0,  $items['gusL']->fresh()->calculated_std_day_1);
        $this->assertEquals(75.0,  $items['gusR']->fresh()->calculated_std_day_1); // ceil(60/25)*25 = 75
        $this->assertEquals(100.0, $items['brk']->fresh()->calculated_std_day_1);  // 100 * 1
        $this->assertEquals(50.0,  $items['coil']->fresh()->calculated_std_day_1); // 100 * 0.5
        $this->assertEquals(100.0, $items['coil']->fresh()->std_day_2);
        $this->assertEquals(150.0, $items['coil']->fresh()->std_day_3);
        $this->assertEquals(25.0,  $items['sheet']->fresh()->calculated_std_day_1); // max(50/3, 75/3) = 25
        $this->assertEquals(20.0,  $items['pipa']->fresh()->calculated_std_day_1);  // max(40*0.5, 40*0.5) = 20
        $this->assertEquals(320.0, $items['nut']->fresh()->calculated_std_day_1);   // 100*2 + 40*2 + 40*1 = 320

        // Proteksi duplikat Part No
        $this->post(route('items.store'), [
            'part_no'    => 'FG-STAY-01',
            'part_name'  => 'DUPLIKAT STAY',
            'type'       => 'FG',
            'uom'        => 'PCS',
            'company_id' => $companies['cusDpm']->id,
        ])->assertSessionHasErrors('part_no');

        // Proteksi salah pasangan tipe Item vs tipe Company (FG wajib CUSTOMER, bukan SUPPLIER)
        $this->post(route('items.store'), [
            'part_no'    => 'FG-SALAH-01',
            'part_name'  => 'FG SALAH COMPANY',
            'type'       => 'FG',
            'uom'        => 'PCS',
            'company_id' => $companies['supKs']->id,
        ])->assertSessionHas('error');
        $this->assertDatabaseMissing('items', ['part_no' => 'FG-SALAH-01']);

        // Proteksi hapus Company & Process yang sudah terpakai
        $this->delete(route('companies.destroy', $companies['supKs']->id))->assertSessionHas('error');
        $this->assertDatabaseHas('companies', ['id' => $companies['supKs']->id, 'deleted_at' => null]);

        $this->delete(route('processes.destroy', $processes['pStamp']->id))->assertSessionHas('error');
        $this->assertDatabaseHas('processes', ['id' => $processes['pStamp']->id]);

        // Item tanpa riwayat -> hapus permanen (atau soft delete sesuai controller) vs Item ber-riwayat BOM -> soft delete & restore
        $this->post(route('items.store'), [
            'part_no'    => 'TEMP-DEL-01',
            'part_name'  => 'ITEM SEMENTARA',
            'type'       => 'TRANSIT',
            'uom'        => 'PCS',
            'company_id' => $companies['supBjt']->id,
        ])->assertSessionHasNoErrors();
        $tempItem = Item::where('part_no', 'TEMP-DEL-01')->firstOrFail();

        $this->delete(route('items.destroy', $tempItem->id))->assertSessionHas('success');
        $this->assertDatabaseMissing('items', ['id' => $tempItem->id]);

        // Item yang sudah terdaftar di BOM -> soft delete (arsip), lalu restore kembali
        $this->delete(route('items.destroy', $items['stay']->id))->assertSessionHas('success');
        $this->assertSoftDeleted('items', ['id' => $items['stay']->id]);

        $this->post(route('items.restore', $items['stay']->id))->assertSessionHas('success');
        $this->assertDatabaseHas('items', ['id' => $items['stay']->id, 'deleted_at' => null]);
    }

    /**
     * KELOMPOK 2: Siklus Normal (Happy Path) 4 Tipe BOM, Kalkulator, Delivery, & Tutup Lot (Top/End Coil & Sibling)
     */
    public function test_02_siklus_normal_4_tipe_bom_delivery_dan_finish_lot(): void
    {
        $m = $this->setupIndustrialMasterViaHttp();
        $it = $m['items'];

        // 1. Inbound pakai ekspresi kalkulator
        $this->actingAs($this->operator);
        $this->post(route('inbounds.store'), ['item_id' => $it['coil']->id,    'qty' => '4*25', 'mode' => 'scanner'])->assertSessionHas('success');
        $this->post(route('inbounds.store'), ['item_id' => $it['sheet']->id,   'qty' => '50',   'mode' => 'scanner'])->assertSessionHas('success');
        $this->post(route('inbounds.store'), ['item_id' => $it['pipa']->id,    'qty' => '50',   'mode' => 'scanner'])->assertSessionHas('success');
        $this->post(route('inbounds.store'), ['item_id' => $it['nut']->id,     'qty' => '500',  'mode' => 'scanner'])->assertSessionHas('success');
        $this->post(route('inbounds.store'), ['item_id' => $it['transit']->id, 'qty' => '100',  'mode' => 'scanner'])->assertSessionHas('success');

        $this->assertEquals(100.0, $this->stockOf($it['coil']));
        $this->assertEquals(500.0, $this->stockOf($it['nut']));

        // 2. Material Out SINGLE (Coil): 60 KG -> est 120 PCS KMP-BRK-01
        $this->post(route('material_outs.store'), [
            'material_item_id' => $it['coil']->id,
            'target_item_id'   => $it['brk']->id,
            'lot_number'       => 'A260001',
            'qty'              => '60',
            'mode'             => 'scanner',
        ])->assertSessionHas('success');

        $this->assertEquals(40.0, $this->stockOf($it['coil']));
        $lotCoil = LotTracer::where('lot_number', 'A260001')->firstOrFail();
        $this->assertEquals(120.0, (float) $lotCoil->target_qty_estimasi);
        $this->assertEquals(120.0, (float) $lotCoil->qty_wip);

        // 3. Production bertahap (60 + 40 = 100 PCS) -> sisa coil di mesin = 10 KG
        $this->post(route('productions.store'), ['item_id' => $it['brk']->id, 'lot_number' => 'A260001', 'qty' => '60'])->assertSessionHas('success');
        $this->post(route('productions.store'), ['item_id' => $it['brk']->id, 'lot_number' => 'A260001', 'qty' => '40'])->assertSessionHas('success');

        $this->assertEquals(100.0, $this->stockOf($it['brk']));
        $lotCoil->refresh();
        $this->assertEquals(100.0, (float) $lotCoil->actual_qty_prod);
        $this->assertEquals(20.0,  (float) $lotCoil->qty_wip);
        $this->assertEquals(10.0,  (float) $lotCoil->material_saat_ini);

        // Tutup Lot Coil -> sisa 10 KG berpindah jadi Top/End Coil, Material Saat Ini & WIP jadi 0
        $this->actingAs($this->ppic);
        $this->patch(route('lot_tracers.set_finish', $lotCoil->id))->assertSessionHas('success');
        $lotCoil->refresh();
        $this->assertSame('FINISH', $lotCoil->status_lot);
        $this->assertEquals(0.0,  (float) $lotCoil->qty_wip);
        $this->assertEquals(0.0,  (float) $lotCoil->material_saat_ini);
        $this->assertEquals(10.0, (float) $lotCoil->top_end_coil);

        // 4. Material Out MULTI-INPUT: 40 KMP-BRK-01 + otomatis potong 80 KMP-NUT-M8 -> FG-STAY-01
        $this->actingAs($this->operator);
        $this->post(route('material_outs.store'), [
            'material_item_id' => $it['brk']->id,
            'target_item_id'   => $it['stay']->id,
            'lot_number'       => 'B260002',
            'qty'              => '40',
        ])->assertSessionHas('success');

        $this->assertEquals(60.0,  $this->stockOf($it['brk']));
        $this->assertEquals(420.0, $this->stockOf($it['nut'])); // 500 - (40*2)

        // Production 40 FG-STAY-01 & Delivery FG + TRANSIT (pakai kalkulator)
        $this->post(route('productions.store'), ['item_id' => $it['stay']->id, 'lot_number' => 'B260002', 'qty' => '40'])->assertSessionHas('success');
        $this->post(route('deliveries.store'),  ['item_id' => $it['stay']->id, 'qty' => '15'])->assertSessionHas('success');
        $this->post(route('deliveries.store'),  ['item_id' => $it['transit']->id, 'qty' => '2*15'])->assertSessionHas('success');

        $this->assertEquals(25.0, $this->stockOf($it['stay']));
        $this->assertEquals(70.0, $this->stockOf($it['transit']));

        // 5. Material Out MULTI-OUTPUT (pecahan 1/3): 10 SHEET -> 30 GUS-L & 30 GUS-R (persis 30, tidak 29.99)
        $this->post(route('material_outs.store'), [
            'material_item_id' => $it['sheet']->id,
            'target_item_id'   => $it['gusL']->id,
            'lot_number'       => 'C260003',
            'qty'              => '10',
        ])->assertSessionHas('success');

        $this->assertEquals(40.0, $this->stockOf($it['sheet']));
        $moLots = LotTracer::where('lot_number', 'C260003')->get();
        $this->assertCount(2, $moLots);
        foreach ($moLots as $row) {
            $this->assertEquals(30.0, (float) $row->target_qty_estimasi);
            $this->assertEquals(30.0, (float) $row->qty_wip);
        }

        // Tutup Lot Multi-Output lewat 1 baris -> seluruh sibling wajib ikut FINISH
        $this->actingAs($this->ppic);
        $this->patch(route('lot_tracers.set_finish', $moLots->first()->id))->assertSessionHas('success');
        foreach (LotTracer::where('lot_number', 'C260003')->get() as $row) {
            $this->assertSame('FINISH', $row->status_lot);
            $this->assertEquals(0.0, (float) $row->qty_wip);
            $this->assertEquals(0.0, (float) $row->material_saat_ini);
        }

        // 6. Material Out KOMBINASI: 10 BATANG PIPA -> 20 ASSY-A (40 Nut) + 20 ASSY-B (20 Nut) = potong 60 Nut
        $this->actingAs($this->operator);
        $this->post(route('material_outs.store'), [
            'material_item_id' => $it['pipa']->id,
            'target_item_id'   => $it['assyA']->id,
            'lot_number'       => 'D260004',
            'qty'              => '10',
        ])->assertSessionHas('success');

        $this->assertEquals(40.0,  $this->stockOf($it['pipa']));
        $this->assertEquals(360.0, $this->stockOf($it['nut'])); // 420 - 60
        $this->assertCount(2, LotTracer::where('lot_number', 'D260004')->get());
    }

    /**
     * KELOMPOK 3: Guardrail Kekurangan Pendamping (Rollback Total), Over-Yield Scanner, & Anti Stok Minus
     */
    public function test_03_guardrail_pendamping_kurang_over_yield_dan_anti_stok_minus(): void
    {
        $m = $this->setupIndustrialMasterViaHttp();
        $it = $m['items'];

        // Siapkan stok: KMP-BRK-01 = 50 (lewat coil -> prod), tapi KMP-NUT-M8 hanya 10 (butuh 40 untuk 20 BRK)
        $this->actingAs($this->operator);
        $this->post(route('inbounds.store'), ['item_id' => $it['coil']->id, 'qty' => '35'])->assertSessionHas('success');
        $this->post(route('inbounds.store'), ['item_id' => $it['nut']->id,  'qty' => '10'])->assertSessionHas('success');
        $this->post(route('material_outs.store'), ['material_item_id' => $it['coil']->id, 'target_item_id' => $it['brk']->id, 'lot_number' => 'E260001', 'qty' => '25'])->assertSessionHas('success');
        $this->post(route('productions.store'), ['item_id' => $it['brk']->id, 'lot_number' => 'E260001', 'qty' => '50'])->assertSessionHas('success');

        // 1. Material Out gagal karena pendamping kurang -> stok Trigger TIDAK boleh ikut terpotong (rollback penuh)
        $this->post(route('material_outs.store'), [
            'material_item_id' => $it['brk']->id,
            'target_item_id'   => $it['stay']->id,
            'lot_number'       => 'E260002',
            'qty'              => '20',
        ])->assertSessionHas('error');

        $this->assertEquals(50.0, $this->stockOf($it['brk']));
        $this->assertEquals(10.0, $this->stockOf($it['nut']));
        $this->assertDatabaseMissing('lot_tracers', ['lot_number' => 'E260002']);

        // 2. Nomor Lot duplikat & format Lot tidak valid harus ditolak
        $this->post(route('material_outs.store'), [
            'material_item_id' => $it['coil']->id,
            'target_item_id'   => $it['brk']->id,
            'lot_number'       => 'E260001',
            'qty'              => '5',
        ])->assertSessionHas('error');

        $this->post(route('material_outs.store'), [
            'material_item_id' => $it['coil']->id,
            'target_item_id'   => $it['brk']->id,
            'lot_number'       => '1234567',
            'qty'              => '5',
        ])->assertSessionHasErrors('lot_number');

        // 3. Over-yield di scanner Production (melebihi WIP) wajib ditolak
        $this->post(route('material_outs.store'), [
            'material_item_id' => $it['coil']->id,
            'target_item_id'   => $it['brk']->id,
            'lot_number'       => 'E260003',
            'qty'              => '10', // est 20 PCS
        ])->assertSessionHas('success');

        $this->post(route('productions.store'), [
            'item_id'    => $it['brk']->id,
            'lot_number' => 'E260003',
            'qty'        => '25', // > 20 WIP
        ])->assertSessionHas('error');
        $this->assertEquals(50.0, $this->stockOf($it['brk']));

        // 4. Delivery & Return to Supplier melebihi stok wajib ditolak
        $this->post(route('deliveries.store'), ['item_id' => $it['stay']->id, 'qty' => '1'])->assertSessionHas('error');
        $this->assertEquals(0.0, $this->stockOf($it['stay']));

        $this->post(route('return_to_suppliers.store'), ['item_id' => $it['coil']->id, 'qty' => '5'])->assertSessionHas('error');
        $this->assertEquals(0.0, $this->stockOf($it['coil']));
    }

    /**
     * KELOMPOK 4: Multi-Return (2x Retur/Lot), Koreksi NG, Return to Supplier, 5 Kategori Stock Adjustment & Delay CRUD
     */
    public function test_04_multi_return_ng_koreksi_rts_stock_adjustment_dan_delay_crud(): void
    {
        $m = $this->setupIndustrialMasterViaHttp();
        $it = $m['items'];
        $cusDpm = $m['companies']['cusDpm'];

        // Siapkan stok: KMP-BRK-01 = 100, KMP-NUT-M8 = 300, COIL = 50, TRANSIT = 100
        $this->actingAs($this->operator);
        $this->post(route('inbounds.store'), ['item_id' => $it['coil']->id,    'qty' => '100'])->assertSessionHas('success');
        $this->post(route('inbounds.store'), ['item_id' => $it['nut']->id,     'qty' => '300'])->assertSessionHas('success');
        $this->post(route('inbounds.store'), ['item_id' => $it['transit']->id, 'qty' => '100'])->assertSessionHas('success');

        $this->post(route('material_outs.store'), ['material_item_id' => $it['coil']->id, 'target_item_id' => $it['brk']->id, 'lot_number' => 'F260000', 'qty' => '50'])->assertSessionHas('success');
        $this->post(route('productions.store'),   ['item_id' => $it['brk']->id, 'lot_number' => 'F260000', 'qty' => '100'])->assertSessionHas('success');

        // Buka Lot MULTI-INPUT F260001: 50 BRK (+100 NUT) -> est 50 FG-STAY-01
        $this->post(route('material_outs.store'), [
            'material_item_id' => $it['brk']->id,
            'target_item_id'   => $it['stay']->id,
            'lot_number'       => 'F260001',
            'qty'              => '50',
        ])->assertSessionHas('success');

        $this->assertEquals(50.0,  $this->stockOf($it['brk']));
        $this->assertEquals(200.0, $this->stockOf($it['nut']));

        // Produksi 20 PCS -> sisa di mesin = 30 BRK
        $this->post(route('productions.store'), ['item_id' => $it['stay']->id, 'lot_number' => 'F260001', 'qty' => '20'])->assertSessionHas('success');

        // MULTI-RETURN #1: Retur 10 BRK (+20 NUT)
        $this->post(route('material_returns.store'), [
            'material_item_id' => $it['brk']->id,
            'target_item_id'   => $it['stay']->id,
            'lot_number'       => 'F260001',
            'qty'              => '10',
        ])->assertSessionHas('success');

        // MULTI-RETURN #2 pada Lot yang sama (sebelumnya diblokir 1x, kini diizinkan selama ON PROGRESS): Retur 5 BRK (+10 NUT)
        $this->post(route('material_returns.store'), [
            'material_item_id' => $it['brk']->id,
            'target_item_id'   => $it['stay']->id,
            'lot_number'       => 'F260001',
            'qty'              => '5',
        ])->assertSessionHas('success');

        $this->assertEquals(65.0,  $this->stockOf($it['brk'])); // 50 + 10 + 5
        $this->assertEquals(230.0, $this->stockOf($it['nut'])); // 200 + 20 + 10

        $lotF = LotTracer::where('lot_number', 'F260001')->firstOrFail();
        $this->assertEquals(15.0, (float) $lotF->qty_material_return);
        $this->assertEquals(35.0, (float) $lotF->target_qty_estimasi);
        $this->assertEquals(15.0, (float) $lotF->qty_wip);

        // Pastikan 2 return_batch berbeda tercatat
        $mrRows = MaterialReturn::where('lot_number', 'F260001')->orderBy('id')->get();
        $this->assertCount(2, $mrRows);
        $this->assertNotSame($mrRows[0]->return_batch, $mrRows[1]->return_batch);

        // Retur #3 melebihi sisa 15 -> wajib ditolak
        $this->post(route('material_returns.store'), [
            'material_item_id' => $it['brk']->id,
            'target_item_id'   => $it['stay']->id,
            'lot_number'       => 'F260001',
            'qty'              => '20',
        ])->assertSessionHas('error');

        // PPIC koreksi Retur #2 dari 5 -> 8 (+3 BRK, +6 NUT) + Audit Trail
        $this->actingAs($this->ppic);
        $this->post(route('material_returns.verify_batch'), [
            'items' => [
                $mrRows[1]->id => [
                    'qty_check'        => 'DIKOREKSI',
                    'actual_qty'       => '8',
                    'correction_notes' => 'Timbang ulang retur shift 2',
                ],
            ],
        ])->assertSessionHas('success');

        $this->assertEquals(68.0,  $this->stockOf($it['brk']));
        $this->assertEquals(236.0, $this->stockOf($it['nut']));
        $lotF->refresh();
        $this->assertEquals(18.0, (float) $lotF->qty_material_return);
        $this->assertEquals(32.0, (float) $lotF->target_qty_estimasi);
        $this->assertEquals(12.0, (float) $lotF->qty_wip);

        $mr2 = $mrRows[1]->fresh();
        $this->assertEquals(5.0, (float) $mr2->qty_original);
        $this->assertEquals(8.0, (float) $mr2->qty);
        $this->assertSame('DIKOREKSI', $mr2->qty_check);
        $this->assertSame($this->ppic->id, $mr2->corrected_by);
        $this->assertSame('Timbang ulang retur shift 2', $mr2->correction_notes);

        // PRODUCTION NG + KOREKSI NG (Tahap A)
        // Input melebihi Act FG (20) -> ditolak
        $this->post(route('production_ngs.store'), [
            'lot_tracer_id'  => $lotF->id,
            'qty_ng'         => '25',
            'problem_reason' => 'MATERIAL',
        ])->assertSessionHas('error');

        // Input NG valid = 4
        $this->post(route('production_ngs.store'), [
            'lot_tracer_id'  => $lotF->id,
            'qty_ng'         => '4',
            'problem_reason' => 'MATERIAL',
        ])->assertSessionHas('success');

        $this->assertEquals(16.0, $this->stockOf($it['stay']));
        $ngRow = ProductionNg::firstOrFail();

        // Koreksi NG: 4 -> 6, reason -> DIES/TOOLING
        $this->post(route('production_ngs.verify_batch'), [
            'items' => [
                $ngRow->id => [
                    'qty_check'        => 'DIKOREKSI',
                    'actual_qty'       => '6',
                    'problem_reason'   => 'DIES/TOOLING',
                    'correction_notes' => 'Tambahan reject hasil sortir ulang QC',
                ],
            ],
        ])->assertSessionHas('success');

        $this->assertEquals(14.0, $this->stockOf($it['stay']));
        $lotF->refresh();
        $this->assertEquals(6.0,  (float) $lotF->qty_ng);
        $this->assertEquals(14.0, (float) $lotF->act_fg);
        $ngRow->refresh();
        $this->assertEquals(4.0, (float) $ngRow->qty_original);
        $this->assertSame('DIES/TOOLING', $ngRow->problem_reason);
        $this->assertSame('DIKOREKSI', $ngRow->qty_check);

        // RETURN TO SUPPLIER + KOREKSI
        $this->post(route('return_to_suppliers.store'), ['item_id' => $it['coil']->id, 'qty' => '12'])->assertSessionHas('success');
        $this->assertEquals(38.0, $this->stockOf($it['coil'])); // 50 - 12
        $rtsRow = ReturnToSupplier::firstOrFail();
        $this->post(route('return_to_suppliers.verify_batch'), [
            'items' => [
                $rtsRow->id => ['qty_check' => 'DIKOREKSI', 'actual_qty' => '10', 'correction_notes' => 'Revisi surat retur'],
            ],
        ])->assertSessionHas('success');
        $this->assertEquals(40.0, $this->stockOf($it['coil']));
        $this->assertEquals(12.0, (float) $rtsRow->fresh()->qty_original);

        // STOCK ADJUSTMENT (5 Kategori + Validasi Arah + Uji Konkurensi input_mode=diff)
        // TRANSIT saat ini = 100
        $this->post(route('stock_adjustments.store'), ['item_id' => $it['transit']->id, 'adjustment_type' => 'LOSS', 'input_mode' => 'actual', 'qty_actual' => '110'])->assertSessionHas('error');
        $this->post(route('stock_adjustments.store'), ['item_id' => $it['transit']->id, 'adjustment_type' => 'LOSS', 'input_mode' => 'actual', 'qty_actual' => '95', 'notes' => 'Hilang rak'])->assertSessionHas('success');
        $this->assertEquals(95.0, $this->stockOf($it['transit']));

        $this->post(route('stock_adjustments.store'), ['item_id' => $it['transit']->id, 'adjustment_type' => 'SURPLUS', 'input_mode' => 'actual', 'qty_actual' => '90'])->assertSessionHas('error');
        $this->post(route('stock_adjustments.store'), ['item_id' => $it['transit']->id, 'adjustment_type' => 'SURPLUS', 'input_mode' => 'diff', 'qty_diff' => '+5'])->assertSessionHas('success');
        $this->post(route('stock_adjustments.store'), ['item_id' => $it['transit']->id, 'adjustment_type' => 'FOUND',   'input_mode' => 'diff', 'qty_diff' => '+10'])->assertSessionHas('success');
        $this->post(route('stock_adjustments.store'), ['item_id' => $it['transit']->id, 'adjustment_type' => 'REPAIR',  'input_mode' => 'diff', 'qty_diff' => '+5'])->assertSessionHas('success');
        $this->assertEquals(115.0, $this->stockOf($it['transit']));

        $this->post(route('stock_adjustments.store'), ['item_id' => $it['transit']->id, 'adjustment_type' => 'STOCK OPNAME', 'input_mode' => 'actual', 'qty_actual' => '115'])->assertSessionHas('error');
        $this->post(route('stock_adjustments.store'), ['item_id' => $it['transit']->id, 'adjustment_type' => 'STOCK OPNAME', 'input_mode' => 'actual', 'qty_actual' => '112'])->assertSessionHas('success');
        $this->assertEquals(112.0, $this->stockOf($it['transit']));

        // Simulasi konkurensi: Perangkat B Delivery -12 (stok jadi 100), Perangkat A kirim input_mode=diff -5 (qty_actual lama 107)
        $this->post(route('deliveries.store'), ['item_id' => $it['transit']->id, 'qty' => '12'])->assertSessionHas('success');
        $this->assertEquals(100.0, $this->stockOf($it['transit']));
        $this->post(route('stock_adjustments.store'), [
            'item_id'         => $it['transit']->id,
            'adjustment_type' => 'LOSS',
            'input_mode'      => 'diff',
            'qty_actual'      => '107', // nilai basi sebelum perangkat B kirim
            'qty_diff'        => '-5',
        ])->assertSessionHas('success');
        $this->assertEquals(95.0, $this->stockOf($it['transit'])); // 100 - 5 = 95, bukan 107

        // PROBLEM DELAY CRUD (Tambah, Edit, Hapus)
        $this->post(route('problem_delays.store'), [
            'incident_date'  => '2026-11-23',
            'company_id'     => $cusDpm->id,
            'item_id'        => $it['stay']->id,
            'problem_reason' => 'MATERIAL',
        ])->assertSessionHas('success');
        $delay = ProblemDelay::firstOrFail();

        $this->put(route('problem_delays.update', $delay->id), [
            'incident_date'  => '2026-11-24',
            'company_id'     => $cusDpm->id,
            'item_id'        => $it['stay']->id,
            'problem_reason' => 'QC',
        ])->assertSessionHas('success');
        $this->assertSame('QC', $delay->fresh()->problem_reason);

        $this->delete(route('problem_delays.destroy', $delay->id))->assertSessionHas('success');
        $this->assertDatabaseMissing('problem_delays', ['id' => $delay->id]);
    }

    /**
     * KELOMPOK 5: Salah Scan 8 Lookup, Override Over-Yield PPIC, Audit Trail 2x Koreksi, Batas Koreksi & Kunci Lot FINISH
     */
    public function test_05_salah_scan_override_ppic_batas_koreksi_kunci_finish_dan_audit_trail(): void
    {
        $m = $this->setupIndustrialMasterViaHttp();
        $it = $m['items'];

        // 1. Uji Salah Scan Lintas Tipe di 8 Endpoint AJAX Lookup
        $this->actingAs($this->operator);
        $this->getJson(route('inbounds.lookup', ['code' => 'FG-STAY-01']))->assertOk()->assertJson(['ok' => false]);
        $this->getJson(route('deliveries.lookup', ['code' => 'COIL SPHC 2.3 X 120']))->assertOk()->assertJson(['ok' => false]);
        $this->getJson(route('return_to_suppliers.lookup', ['code' => 'FG-STAY-01']))->assertOk()->assertJson(['ok' => false]);
        $this->getJson(route('productions.lookup', ['code' => 'COIL SPHC 2.3 X 120']))->assertOk()->assertJson(['ok' => false]);
        $this->getJson(route('production_ngs.lookup', ['code' => 'COIL SPHC 2.3 X 120']))->assertOk()->assertJson(['ok' => false]);
        $this->getJson(route('material_outs.lookup', ['step' => 1, 'code' => 'FG-STAY-01']))->assertOk()->assertJson(['ok' => false]);
        $this->getJson(route('material_returns.lookup', ['step' => 1, 'code' => 'TRS-BOLT-01']))->assertOk()->assertJson(['ok' => false]);
        $this->getJson(route('stock_adjustments.lookup', ['code' => 'KODE-TIDAK-ADA']))->assertOk()->assertJson(['ok' => false]);

        // 2. Setup Lot G260001 untuk uji Override Over-Yield & Audit Trail 2x koreksi
        $this->post(route('inbounds.store'), ['item_id' => $it['coil']->id, 'qty' => '100'])->assertSessionHas('success');
        $inbRow = Inbound::firstOrFail();

        $this->post(route('material_outs.store'), [
            'material_item_id' => $it['coil']->id,
            'target_item_id'   => $it['brk']->id,
            'lot_number'       => 'G260001',
            'qty'              => '20', // est 40 PCS
        ])->assertSessionHas('success');
        $mo1 = MaterialOut::where('lot_number', 'G260001')->firstOrFail();

        $this->post(route('productions.store'), ['item_id' => $it['brk']->id, 'lot_number' => 'G260001', 'qty' => '40'])->assertSessionHas('success');
        $prod1 = Production::where('lot_number', 'G260001')->firstOrFail();

        // Override Over-Yield PPIC: 40 -> 45 PCS (> estimasi 40) harus lolos dan WIP tetap 0 (tidak minus)
        $this->actingAs($this->ppic);
        $this->post(route('productions.verify_batch'), [
            'items' => [
                $prod1->id => [
                    'qty_check'        => 'DIKOREKSI',
                    'actual_qty'       => '45',
                    'correction_notes' => 'Over-yield coil aktual di mesin',
                ],
            ],
        ])->assertSessionHas('success');

        $lotG1 = LotTracer::where('lot_number', 'G260001')->firstOrFail();
        $this->assertEquals(45.0, (float) $lotG1->actual_qty_prod);
        $this->assertEquals(0.0,  (float) $lotG1->qty_wip);
        $this->assertEquals(45.0, $this->stockOf($it['brk']));

        // Koreksi ke-2: 45 -> 44 -> qty_original WAJIB tetap 40 (angka scan awal), tidak tertimpa 45
        $this->post(route('productions.verify_batch'), [
            'items' => [
                $prod1->id => [
                    'qty_check'        => 'DIKOREKSI',
                    'actual_qty'       => '44',
                    'correction_notes' => 'Revisi hitung ulang box akhir',
                ],
            ],
        ])->assertSessionHas('success');

        $prod1->refresh();
        $this->assertEquals(40.0, (float) $prod1->qty_original);
        $this->assertEquals(44.0, (float) $prod1->qty);
        $this->assertSame('Revisi hitung ulang box akhir', $prod1->correction_notes);
        $this->assertSame($this->ppic->id, $prod1->corrected_by);

        // Input NG 4 PCS di Lot G260001
        $this->post(route('production_ngs.store'), [
            'lot_tracer_id'  => $lotG1->id,
            'qty_ng'         => '4',
            'problem_reason' => 'PROSES',
        ])->assertSessionHas('success');

        // 3. Guardrail batas koreksi turun
        // 3a. Koreksi Production di bawah qty_ng (4) -> ditolak
        $this->post(route('productions.verify_batch'), [
            'items' => [$prod1->id => ['qty_check' => 'DIKOREKSI', 'actual_qty' => '2']],
        ])->assertSessionHas('error');

        // 3b. Koreksi Material Out di bawah terpakai produksi (44 * 0.5 = 22) -> ditolak
        $this->post(route('material_outs.verify_batch'), [
            'items' => [$mo1->id => ['qty_check' => 'DIKOREKSI', 'actual_qty' => '10']],
        ])->assertSessionHas('error');

        // 3c. Koreksi Inbound turun melebihi stok gudang (stok 80, turun -90 -> minus) -> ditolak
        $this->post(route('inbounds.verify_batch'), [
            'items' => [$inbRow->id => ['qty_check' => 'DIKOREKSI', 'actual_qty' => '10']],
        ])->assertSessionHas('error');

        // 4. Kunci Lot FINISH pada 4 modul (Material Out, Material Return, Production, Production NG)
        $this->actingAs($this->operator);
        $this->post(route('material_outs.store'), ['material_item_id' => $it['coil']->id, 'target_item_id' => $it['brk']->id, 'lot_number' => 'G260002', 'qty' => '20'])->assertSessionHas('success');
        $this->post(route('material_returns.store'), ['material_item_id' => $it['coil']->id, 'target_item_id' => $it['brk']->id, 'lot_number' => 'G260002', 'qty' => '5'])->assertSessionHas('success');
        $this->post(route('productions.store'), ['item_id' => $it['brk']->id, 'lot_number' => 'G260002', 'qty' => '10'])->assertSessionHas('success');

        $lotG2 = LotTracer::where('lot_number', 'G260002')->firstOrFail();
        $this->post(route('production_ngs.store'), ['lot_tracer_id' => $lotG2->id, 'qty_ng' => '2', 'problem_reason' => 'MATERIAL'])->assertSessionHas('success');

        $mo2   = MaterialOut::where('lot_number', 'G260002')->firstOrFail();
        $mr2   = MaterialReturn::where('lot_number', 'G260002')->firstOrFail();
        $prod2 = Production::where('lot_number', 'G260002')->firstOrFail();
        $ng2   = ProductionNg::where('lot_number', 'G260002')->firstOrFail();

        // Tutup Lot G260002 -> FINISH
        $this->actingAs($this->ppic);
        $this->patch(route('lot_tracers.set_finish', $lotG2->id))->assertSessionHas('success');

        // Keempat modul wajib menolak koreksi pada Lot FINISH
        $this->post(route('material_outs.verify_batch'),    ['items' => [$mo2->id   => ['qty_check' => 'DIKOREKSI', 'actual_qty' => '25']]])->assertSessionHas('error');
        $this->post(route('material_returns.verify_batch'), ['items' => [$mr2->id   => ['qty_check' => 'DIKOREKSI', 'actual_qty' => '6']]])->assertSessionHas('error');
        $this->post(route('productions.verify_batch'),      ['items' => [$prod2->id => ['qty_check' => 'DIKOREKSI', 'actual_qty' => '12']]])->assertSessionHas('error');
        $this->post(route('production_ngs.verify_batch'),   ['items' => [$ng2->id   => ['qty_check' => 'DIKOREKSI', 'actual_qty' => '3']]])->assertSessionHas('error');

        $this->assertEquals(20.0, (float) $mo2->fresh()->qty);
        $this->assertEquals(5.0,  (float) $mr2->fresh()->qty);
        $this->assertEquals(10.0, (float) $prod2->fresh()->qty);
        $this->assertEquals(2.0,  (float) $ng2->fresh()->qty_ng);

        // 5. Pastikan JSON Modal Informasi Item memuat Audit Trail koreksi
        $infoRes = $this->getJson(route('items.info', $it['brk']->id))->assertOk()->json();
        $correctedEvent = collect($infoRes['history'])->first(fn ($h) => $h['type_label'] === 'Production' && $h['status'] === 'DIKOREKSI');
        $this->assertNotNull($correctedEvent);
        $this->assertEquals(40.0, (float) $correctedEvent['qty_original']);
        $this->assertSame($this->ppic->name, $correctedEvent['corrected_by']);
        $this->assertSame('Revisi hitung ulang box akhir', $correctedEvent['correction_notes']);
    }

    /**
     * KELOMPOK 6: Verifikasi Dashboard, Snapshot Stock 07:00 (Trigger & Pendamping + Multi-Return), & Neraca Riwayat Item
     */
    public function test_06_dashboard_snapshot_0700_pendamping_multi_return_dan_neraca_riwayat(): void
    {
        // Bekukan waktu pukul 06:30 WIB (sebelum cut-off 07:00)
        Carbon::setTestNow(Carbon::create(2026, 11, 24, 6, 30, 0, config('app.timezone')));

        $m = $this->setupIndustrialMasterViaHttp();
        $it = $m['items'];

        // Transaksi sebelum 07:00 -> saldo tepat pukul 07:00: COIL=50, BRK=100, NUT=200, STAY=0
        $this->actingAs($this->operator);
        $this->post(route('inbounds.store'), ['item_id' => $it['coil']->id, 'qty' => '100'])->assertSessionHas('success');
        $this->post(route('inbounds.store'), ['item_id' => $it['nut']->id,  'qty' => '200'])->assertSessionHas('success');
        $this->post(route('material_outs.store'), ['material_item_id' => $it['coil']->id, 'target_item_id' => $it['brk']->id, 'lot_number' => 'H260000', 'qty' => '50'])->assertSessionHas('success');
        $this->post(route('productions.store'),   ['item_id' => $it['brk']->id, 'lot_number' => 'H260000', 'qty' => '100'])->assertSessionHas('success');

        // Maju ke 08:30 WIB (setelah cut-off 07:00): Material Out Multi-Input + 2x Multi-Return + Prod + NG + Delivery
        Carbon::setTestNow(Carbon::create(2026, 11, 24, 8, 30, 0, config('app.timezone')));
        $this->post(route('material_outs.store'), [
            'material_item_id' => $it['brk']->id,
            'target_item_id'   => $it['stay']->id,
            'lot_number'       => 'H260001',
            'qty'              => '40', // BRK: 100->60, NUT: 200->120
        ])->assertSessionHas('success');

        Carbon::setTestNow(Carbon::create(2026, 11, 24, 9, 0, 0, config('app.timezone')));
        $this->post(route('material_returns.store'), [
            'material_item_id' => $it['brk']->id,
            'target_item_id'   => $it['stay']->id,
            'lot_number'       => 'H260001',
            'qty'              => '10', // BRK: 60->70, NUT: 120->140
        ])->assertSessionHas('success');

        Carbon::setTestNow(Carbon::create(2026, 11, 24, 9, 30, 0, config('app.timezone')));
        $this->post(route('material_returns.store'), [
            'material_item_id' => $it['brk']->id,
            'target_item_id'   => $it['stay']->id,
            'lot_number'       => 'H260001',
            'qty'              => '5', // BRK: 70->75, NUT: 140->150
        ])->assertSessionHas('success');

        Carbon::setTestNow(Carbon::create(2026, 11, 24, 10, 0, 0, config('app.timezone')));
        $this->post(route('productions.store'), ['item_id' => $it['stay']->id, 'lot_number' => 'H260001', 'qty' => '20'])->assertSessionHas('success');

        $lotH = LotTracer::where('lot_number', 'H260001')->firstOrFail();
        $this->post(route('production_ngs.store'), ['lot_tracer_id' => $lotH->id, 'qty_ng' => '2', 'problem_reason' => 'MATERIAL'])->assertSessionHas('success');
        $this->post(route('deliveries.store'), ['item_id' => $it['stay']->id, 'qty' => '8'])->assertSessionHas('success');

        // Pukul 11:00 WIB: Cek Dashboard + Stock 07:00
        Carbon::setTestNow(Carbon::create(2026, 11, 24, 11, 0, 0, config('app.timezone')));
        $this->actingAs($this->ppic);
        $resp = $this->get(route('dashboard'))->assertOk();

        $dashItems = collect($resp->viewData('dashboardItems'))->keyBy('id');

        // Verifikasi Trigger (BRK): sekarang 75, pukul 07:00 = 100
        $this->assertEquals(75.0,  (float) $dashItems[$it['brk']->id]['current_stock']);
        $this->assertEquals(100.0, (float) $dashItems[$it['brk']->id]['stock_07']);

        // Verifikasi Pendamping (NUT): sekarang 150, pukul 07:00 = 200 (akumulasi MO + 2x Multi-Return tepat!)
        $this->assertEquals(150.0, (float) $dashItems[$it['nut']->id]['current_stock']);
        $this->assertEquals(200.0, (float) $dashItems[$it['nut']->id]['stock_07']);

        // Verifikasi FG (STAY): sekarang 10, pukul 07:00 = 0
        $this->assertEquals(10.0, (float) $dashItems[$it['stay']->id]['current_stock']);
        $this->assertEquals(0.0,  (float) $dashItems[$it['stay']->id]['stock_07']);

        // Verifikasi neraca linimasa Informasi Item (jumlah seluruh mutasi == current_stock)
        foreach ([$it['coil'], $it['brk'], $it['nut'], $it['stay']] as $checkItem) {
            $info = $this->getJson(route('items.info', $checkItem->id))->assertOk()->json();
            $sumHistory = round(collect($info['history'])->sum('qty_change'), 2);
            $this->assertEquals(
                round((float) $info['current_stock'], 2),
                $sumHistory,
                "Neraca riwayat mutasi tidak cocok dengan saldo gudang untuk {$checkItem->part_name}"
            );
        }
    }
}