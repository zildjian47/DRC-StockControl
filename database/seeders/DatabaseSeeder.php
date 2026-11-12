<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Company;
use App\Models\Process;
use App\Models\Item;
use App\Models\Bom;
use App\Models\InventoryStock;
use App\Models\LotTracer;
use App\Models\Inbound;
use App\Models\MaterialOut;
use App\Models\MaterialReturn;
use App\Models\Production;
use App\Models\ProductionNg;
use App\Models\Delivery;
use App\Models\ProblemDelay;
use App\Models\StockAdjustment;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Pengguna (PPIC & Operator Scanner)
        $ppic = User::create([
            'name'     => 'Budi Santoso (PPIC)',
            'username' => 'ppic',
            'email'    => 'ppic@drc.co.id',
            'role'     => 'PPIC',
            'password' => Hash::make('password'),
        ]);

        $staff = User::create([
            'name'     => 'Andi Pratama (Operator)',
            'username' => 'operator',
            'email'    => 'operator@drc.co.id',
            'role'     => 'STAFF',
            'password' => Hash::make('password'),
        ]);

        // 2. Master Companies (SUPPLIER, INTERNAL, CUSTOMER)
        $compKrakatau = Company::create([
            'company_code' => 'KRS',
            'company_name' => 'PT. Krakatau Steel Tbk.',
            'type_company' => 'SUPPLIER',
        ]);

        $compFastener = Company::create([
            'company_code' => 'MFS',
            'company_name' => 'PT. Mitra Fastener Supplier',
            'type_company' => 'SUPPLIER',
        ]);

        $compInternal = Company::create([
            'company_code' => 'DRC',
            'company_name' => 'PT. Dian Raya Cipta',
            'type_company' => 'INTERNAL',
        ]);

        $compDharma = Company::create([
            'company_code' => 'DPM',
            'company_name' => 'PT. Dharma Polimetal Tbk.',
            'type_company' => 'CUSTOMER',
        ]);

        $compAstra = Company::create([
            'company_code' => 'AHM',
            'company_name' => 'PT. Astra Honda Motor',
            'type_company' => 'CUSTOMER',
        ]);

        // 3. Manufacture Processes
        $procStamping = Process::create(['process_name' => 'STAMPING']);
        $procShearing = Process::create(['process_name' => 'SHEARING BLANKING']);
        $procSpotWeld = Process::create(['process_name' => 'SPOT WELDING']);
        $procAssyWeld = Process::create(['process_name' => 'ASSY WELDING']);

        // 4. Master Items
        // A. Tipe FG (CUSTOMER)
        $fgBracket = Item::create([
            'part_no'         => 'FG-BKT-001',
            'part_name'       => 'BRACKET MUFFLER ASSY FG',
            'type'            => 'FG',
            'uom'             => 'PCS',
            'company_id'      => $compDharma->id,
            'qty_po'          => 1000,
            'working_days'    => 20,
            'qty_kanban'      => 30,
            'std_day_1_po_wd' => 50,
            'std_day_1'       => 60,
        ]);

        $fgCover = Item::create([
            'part_no'         => 'FG-CVR-002',
            'part_name'       => 'COVER ENGINE PROTECTOR FG',
            'type'            => 'FG',
            'uom'             => 'PCS',
            'company_id'      => $compAstra->id,
            'qty_po'          => 600,
            'working_days'    => 20,
            'qty_kanban'      => 15,
            'std_day_1_po_wd' => 30,
            'std_day_1'       => 30,
        ]);

        $fgGussetR = Item::create([
            'part_no'         => 'FG-GUS-R',
            'part_name'       => 'GUSSET FRAME RIGHT (R)',
            'type'            => 'FG',
            'uom'             => 'PCS',
            'company_id'      => $compDharma->id,
            'qty_po'          => 1000,
            'working_days'    => 20,
            'qty_kanban'      => 25,
            'std_day_1_po_wd' => 50,
            'std_day_1'       => 50,
        ]);

        $fgGussetL = Item::create([
            'part_no'         => 'FG-GUS-L',
            'part_name'       => 'GUSSET FRAME LEFT (L)',
            'type'            => 'FG',
            'uom'             => 'PCS',
            'company_id'      => $compDharma->id,
            'qty_po'          => 1000,
            'working_days'    => 20,
            'qty_kanban'      => 25,
            'std_day_1_po_wd' => 50,
            'std_day_1'       => 50,
        ]);

        $fgGussetC = Item::create([
            'part_no'         => 'FG-GUS-C',
            'part_name'       => 'GUSSET FRAME CENTER (C)',
            'type'            => 'FG',
            'uom'             => 'PCS',
            'company_id'      => $compDharma->id,
            'qty_po'          => 800,
            'working_days'    => 20,
            'qty_kanban'      => 20,
            'std_day_1_po_wd' => 40,
            'std_day_1'       => 40,
        ]);

        $fgArmRh = Item::create([
            'part_no'         => 'FG-ARM-RH',
            'part_name'       => 'SUSPENSION ARM ASSY RH',
            'type'            => 'FG',
            'uom'             => 'PCS',
            'company_id'      => $compAstra->id,
            'qty_po'          => 800,
            'working_days'    => 20,
            'qty_kanban'      => 20,
            'std_day_1_po_wd' => 40,
            'std_day_1'       => 40,
        ]);

        $fgArmLh = Item::create([
            'part_no'         => 'FG-ARM-LH',
            'part_name'       => 'SUSPENSION ARM ASSY LH',
            'type'            => 'FG',
            'uom'             => 'PCS',
            'company_id'      => $compAstra->id,
            'qty_po'          => 800,
            'working_days'    => 20,
            'qty_kanban'      => 20,
            'std_day_1_po_wd' => 40,
            'std_day_1'       => 40,
        ]);

        $fgTestDel = Item::create([
            'part_no'         => 'FG-TEST-DEL',
            'part_name'       => 'BRACKET SAMPLE PROTOTYPE (SIAP EDIT/HAPUS)',
            'type'            => 'FG',
            'uom'             => 'PCS',
            'company_id'      => $compAstra->id,
            'qty_po'          => 200,
            'working_days'    => 20,
            'qty_kanban'      => 10,
            'std_day_1_po_wd' => 10,
            'std_day_1'       => 10,
        ]);

        // B. Tipe FG/KOMPONEN (INTERNAL)
        $fgkPlate = Item::create([
            'part_no'         => 'FGK-PLT-001',
            'part_name'       => 'BASE PLATE STAMPING MUFFLER',
            'type'            => 'FG/KOMPONEN',
            'uom'             => 'PCS',
            'company_id'      => $compInternal->id,
            'qty_po'          => 400,
            'working_days'    => 20,
            'qty_kanban'      => 25,
            'std_day_1_po_wd' => 20,
            'std_day_1'       => 25,
        ]);

        $fgkSharedBase = Item::create([
            'part_no'         => 'FGK-ARM-BASE',
            'part_name'       => 'SHARED PLATE ARM RH/LH',
            'type'            => 'FG/KOMPONEN',
            'uom'             => 'PCS',
            'company_id'      => $compInternal->id,
            'qty_po'          => 200,
            'working_days'    => 20,
            'qty_kanban'      => 10,
            'std_day_1_po_wd' => 10,
            'std_day_1'       => 10,
        ]);

        // C. Tipe KOMPONEN (INTERNAL & SUPPLIER)
        $kmpStay = Item::create([
            'part_no'      => 'KMP-STAY-001',
            'part_name'    => 'REINFORCE STAY BRACKET',
            'type'         => 'KOMPONEN',
            'uom'          => 'PCS',
            'company_id'   => $compInternal->id,
            'qty_po'       => 0,
            'working_days' => 0,
            'qty_kanban'   => 0,
        ]);

        $kmpClipCoil = Item::create([
            'part_no'      => 'KMP-CLP-002',
            'part_name'    => 'CLIP RETAINER STAMPING (DARI COIL)',
            'type'         => 'KOMPONEN',
            'uom'          => 'PCS',
            'company_id'   => $compInternal->id,
            'qty_po'       => 0,
            'working_days' => 0,
            'qty_kanban'   => 0,
        ]);

        $kmpNut = Item::create([
            'part_no'      => 'KMP-NUT-M8',
            'part_name'    => 'WELD NUT M8 X 1.25',
            'type'         => 'KOMPONEN',
            'uom'          => 'PCS',
            'company_id'   => $compFastener->id,
            'qty_po'       => 0,
            'working_days' => 0,
            'qty_kanban'   => 0,
        ]);

        $kmpCollar = Item::create([
            'part_no'      => 'KMP-CLR-10',
            'part_name'    => 'BUSHING COLLAR 10MM',
            'type'         => 'KOMPONEN',
            'uom'          => 'PCS',
            'company_id'   => $compFastener->id,
            'qty_po'       => 0,
            'working_days' => 0,
            'qty_kanban'   => 0,
        ]);

        // D. Tipe RM (SUPPLIER — Sheet Metal & Coil tanpa Part No)
        $rmSphc = Item::create([
            'part_no'      => null,
            'part_name'    => 'SHEET METAL SPHC 2.0MM',
            'type'         => 'RM',
            'uom'          => 'SHEET',
            'company_id'   => $compKrakatau->id,
            'qty_po'       => 0,
            'working_days' => 0,
            'qty_kanban'   => 0,
        ]);

        $rmSpcc = Item::create([
            'part_no'      => null,
            'part_name'    => 'SHEET METAL SPCC 1.2MM',
            'type'         => 'RM',
            'uom'          => 'SHEET',
            'company_id'   => $compKrakatau->id,
            'qty_po'       => 0,
            'working_days' => 0,
            'qty_kanban'   => 0,
        ]);

        $rmTrapesium = Item::create([
            'part_no'      => null,
            'part_name'    => 'SHEET TRAPESIUM SPHC 2.3MM',
            'type'         => 'RM',
            'uom'          => 'SHEET',
            'company_id'   => $compKrakatau->id,
            'qty_po'       => 0,
            'working_days' => 0,
            'qty_kanban'   => 0,
        ]);

        $rmCoil = Item::create([
            'part_no'      => null,
            'part_name'    => 'COIL SPHC 2.0 X 150MM',
            'type'         => 'RM',
            'uom'          => 'KG',
            'company_id'   => $compKrakatau->id,
            'qty_po'       => 0,
            'working_days' => 0,
            'qty_kanban'   => 0,
        ]);

        // E. Tipe TRANSIT (SUPPLIER)
        $trsBolt = Item::create([
            'part_no'         => 'TRS-BOLT-01',
            'part_name'       => 'FLANGE BOLT M10 TRANSIT',
            'type'            => 'TRANSIT',
            'uom'             => 'PCS',
            'company_id'      => $compFastener->id,
            'qty_po'          => 500,
            'working_days'    => 20,
            'qty_kanban'      => 25,
            'std_day_1_po_wd' => 25,
            'std_day_1'       => 25,
        ]);

        // 5. Struktur BOM (4 Mode + Resep Coil)
        Bom::create([
            'bom_group'      => 'SG-PLT-1',
            'process_id'     => $procStamping->id,
            'output_item_id' => $fgkPlate->id,
            'input_item_id'  => $rmSphc->id,
            'qty_usage'      => 0.20000000,
            'is_trigger'     => true,
        ]);

        Bom::create([
            'bom_group'      => 'SG-STAY-1',
            'process_id'     => $procStamping->id,
            'output_item_id' => $kmpStay->id,
            'input_item_id'  => $rmSphc->id,
            'qty_usage'      => 0.10000000,
            'is_trigger'     => true,
        ]);

        Bom::create([
            'bom_group'      => 'SG-CVR-1',
            'process_id'     => $procStamping->id,
            'output_item_id' => $fgCover->id,
            'input_item_id'  => $rmSpcc->id,
            'qty_usage'      => Bom::parseCalculatorValue('1/3'),
            'is_trigger'     => true,
        ]);

        // Resep Coil (1 PCS Clip membutuhkan 0.5 KG Coil -> 1 KG Coil menghasilkan 2 PCS Clip)
        Bom::create([
            'bom_group'      => 'SG-COIL-1',
            'process_id'     => $procStamping->id,
            'output_item_id' => $kmpClipCoil->id,
            'input_item_id'  => $rmCoil->id,
            'qty_usage'      => 0.50000000,
            'is_trigger'     => true,
        ]);

        // Mode 2: MULTI-INPUT
        Bom::create([
            'bom_group'      => 'MI-ASSY-1',
            'process_id'     => $procAssyWeld->id,
            'output_item_id' => $fgBracket->id,
            'input_item_id'  => $fgkPlate->id,
            'qty_usage'      => 2.00000000,
            'is_trigger'     => true,
        ]);
        Bom::create([
            'bom_group'      => 'MI-ASSY-1',
            'process_id'     => $procAssyWeld->id,
            'output_item_id' => $fgBracket->id,
            'input_item_id'  => $kmpStay->id,
            'qty_usage'      => 1.00000000,
            'is_trigger'     => false,
        ]);
        Bom::create([
            'bom_group'      => 'MI-ASSY-1',
            'process_id'     => $procAssyWeld->id,
            'output_item_id' => $fgBracket->id,
            'input_item_id'  => $kmpNut->id,
            'qty_usage'      => 4.00000000,
            'is_trigger'     => false,
        ]);

        // Mode 3: MULTI-OUTPUT (Grup 1: R & L, Grup 2: R & C)
        Bom::create([
            'bom_group'      => 'MO-TRAP-RL',
            'process_id'     => $procShearing->id,
            'output_item_id' => $fgGussetR->id,
            'input_item_id'  => $rmTrapesium->id,
            'qty_usage'      => 1.00000000,
            'is_trigger'     => true,
        ]);
        Bom::create([
            'bom_group'      => 'MO-TRAP-RL',
            'process_id'     => $procShearing->id,
            'output_item_id' => $fgGussetL->id,
            'input_item_id'  => $rmTrapesium->id,
            'qty_usage'      => 1.00000000,
            'is_trigger'     => true,
        ]);

        Bom::create([
            'bom_group'      => 'MO-TRAP-RC',
            'process_id'     => $procShearing->id,
            'output_item_id' => $fgGussetR->id,
            'input_item_id'  => $rmTrapesium->id,
            'qty_usage'      => 1.00000000,
            'is_trigger'     => true,
        ]);
        Bom::create([
            'bom_group'      => 'MO-TRAP-RC',
            'process_id'     => $procShearing->id,
            'output_item_id' => $fgGussetC->id,
            'input_item_id'  => $rmTrapesium->id,
            'qty_usage'      => 0.50000000,
            'is_trigger'     => true,
        ]);

        // Mode 4: KOMBINASI (Multi-Input & Multi-Output)
        Bom::create([
            'bom_group'      => 'CB-ARM-RHLH',
            'process_id'     => $procSpotWeld->id,
            'output_item_id' => $fgArmRh->id,
            'input_item_id'  => $fgkSharedBase->id,
            'qty_usage'      => 1.00000000,
            'is_trigger'     => true,
        ]);
        Bom::create([
            'bom_group'      => 'CB-ARM-RHLH',
            'process_id'     => $procSpotWeld->id,
            'output_item_id' => $fgArmRh->id,
            'input_item_id'  => $kmpNut->id,
            'qty_usage'      => 2.00000000,
            'is_trigger'     => false,
        ]);
        Bom::create([
            'bom_group'      => 'CB-ARM-RHLH',
            'process_id'     => $procSpotWeld->id,
            'output_item_id' => $fgArmRh->id,
            'input_item_id'  => $kmpCollar->id,
            'qty_usage'      => 1.00000000,
            'is_trigger'     => false,
        ]);

        Bom::create([
            'bom_group'      => 'CB-ARM-RHLH',
            'process_id'     => $procSpotWeld->id,
            'output_item_id' => $fgArmLh->id,
            'input_item_id'  => $fgkSharedBase->id,
            'qty_usage'      => 1.00000000,
            'is_trigger'     => true,
        ]);
        Bom::create([
            'bom_group'      => 'CB-ARM-RHLH',
            'process_id'     => $procSpotWeld->id,
            'output_item_id' => $fgArmLh->id,
            'input_item_id'  => $kmpNut->id,
            'qty_usage'      => 2.00000000,
            'is_trigger'     => false,
        ]);
        Bom::create([
            'bom_group'      => 'CB-ARM-RHLH',
            'process_id'     => $procSpotWeld->id,
            'output_item_id' => $fgArmLh->id,
            'input_item_id'  => $kmpCollar->id,
            'qty_usage'      => 1.00000000,
            'is_trigger'     => false,
        ]);

        // 6. Saldo Stok Gudang
        $stockMap = [
            [$fgBracket, 140],      // NORMAL STOCK
            [$fgCover, 20],         // VERY LOW
            [$fgGussetR, 110],      // NORMAL STOCK
            [$fgGussetL, 105],      // NORMAL STOCK
            [$fgGussetC, 90],       // NORMAL STOCK
            [$fgArmRh, 95],         // NORMAL STOCK
            [$fgArmLh, 95],         // NORMAL STOCK
            [$fgTestDel, 0],        // EMPTY STOCK
            [$fgkPlate, 350],       // NORMAL STOCK
            [$fgkSharedBase, 150],  //OVERSTOCK
            [$kmpStay, 200],        // OVERSTOCK
            [$kmpClipCoil, 190],    // Hasil produksi dari Coil
            [$kmpNut, 900],         // NORMAL STOCK
            [$kmpCollar, 300],      // OVERSTOCK
            [$rmSphc, 50],          // LOW STOCK
            [$rmSpcc, 60],          // OVERSTOCK
            [$rmTrapesium, 120],    // NORMAL STOCK
            [$rmCoil, 450],         // Stok Coil (KG)
            [$trsBolt, 60],         // NORMAL STOCK
        ];

        foreach ($stockMap as [$itm, $qty]) {
            $st = InventoryStock::create([
                'item_id'       => $itm->id,
                'current_stock' => $qty,
                'status_stock'  => 'EMPTY STOCK',
            ]);
            $st->status_stock = $st->calculateDynamicStatus();
            $st->save();
        }

        // 7. Data Transaksi & Siklus Lot Tracer (Termasuk Kasus Coil L261006)
        Inbound::create([
            'date_time' => now()->subHours(5),
            'item_id'   => $rmSphc->id,
            'user_id'   => $staff->id,
            'qty'       => 70,
            'qty_check' => 'SESUAI',
        ]);
        Inbound::create([
            'date_time' => now()->subHours(3),
            'item_id'   => $rmCoil->id,
            'user_id'   => $staff->id,
            'qty'       => 550,
            'qty_check' => 'SESUAI',
        ]);
        Inbound::create([
            'date_time' => now()->subHours(2),
            'item_id'   => $kmpNut->id,
            'user_id'   => $staff->id,
            'qty'       => 500,
            'qty_check' => 'PENDING',
        ]);

        // Lot 1: A261001 — Stamping Base Plate (Single 1:1)
        MaterialOut::create([
            'date_time'        => now()->subHours(4),
            'material_item_id' => $rmSphc->id,
            'target_item_id'   => $fgkPlate->id,
            'user_id'          => $staff->id,
            'lot_number'       => 'A261001',
            'qty'              => 20,
            'qty_check'        => 'SESUAI',
        ]);
        LotTracer::create([
            'lot_number'          => 'A261001',
            'process_id'          => $procStamping->id,
            'material_item_id'    => $rmSphc->id,
            'target_item_id'      => $fgkPlate->id,
            'qty_material_out'    => 20,
            'qty_material_return' => 0,
            'target_qty_estimasi' => 100,
            'actual_qty_prod'     => 60,
            'qty_ng'              => 2,
            'qty_wip'             => 40,
            'status_lot'          => 'ON PROGRESS',
        ]);
        Production::create([
            'date_time'  => now()->subHours(3),
            'item_id'    => $fgkPlate->id,
            'user_id'    => $staff->id,
            'lot_number' => 'A261001',
            'qty'        => 60,
            'qty_check'  => 'SESUAI',
        ]);
        ProductionNg::create([
            'date_time'      => now()->subHours(2),
            'lot_number'     => 'A261001',
            'item_id'        => $fgkPlate->id,
            'user_id'        => $ppic->id,
            'qty_ng'         => 2,
            'problem_reason' => 'HANDLING/OPERATOR',
        ]);

        // Lot 2: B261002 — Assy Welding Bracket (Multi-Input 1:3)
        MaterialOut::create([
            'date_time'        => now()->subHours(3),
            'material_item_id' => $fgkPlate->id,
            'target_item_id'   => $fgBracket->id,
            'user_id'          => $staff->id,
            'lot_number'       => 'B261002',
            'qty'              => 100,
            'qty_check'        => 'PENDING',
        ]);
        LotTracer::create([
            'lot_number'          => 'B261002',
            'process_id'          => $procAssyWeld->id,
            'material_item_id'    => $fgkPlate->id,
            'target_item_id'      => $fgBracket->id,
            'qty_material_out'    => 100,
            'qty_material_return' => 0,
            'target_qty_estimasi' => 50,
            'actual_qty_prod'     => 30,
            'qty_ng'              => 0,
            'qty_wip'             => 20,
            'status_lot'          => 'ON PROGRESS',
        ]);
        Production::create([
            'date_time'  => now()->subHours(1),
            'item_id'    => $fgBracket->id,
            'user_id'    => $staff->id,
            'lot_number' => 'B261002',
            'qty'        => 30,
            'qty_check'  => 'PENDING',
        ]);

        // Lot 3: M261003 — Shearing Trapesium (Multi-Output R & L)
        MaterialOut::create([
            'date_time'        => now()->subHours(2),
            'material_item_id' => $rmTrapesium->id,
            'target_item_id'   => $fgGussetR->id,
            'user_id'          => $staff->id,
            'lot_number'       => 'M261003',
            'qty'              => 20,
            'qty_check'        => 'PENDING',
        ]);
        MaterialOut::create([
            'date_time'        => now()->subHours(2),
            'material_item_id' => $rmTrapesium->id,
            'target_item_id'   => $fgGussetL->id,
            'user_id'          => $staff->id,
            'lot_number'       => 'M261003',
            'qty'              => 20,
            'qty_check'        => 'PENDING',
        ]);
        LotTracer::create([
            'lot_number'          => 'M261003',
            'process_id'          => $procShearing->id,
            'material_item_id'    => $rmTrapesium->id,
            'target_item_id'      => $fgGussetR->id,
            'qty_material_out'    => 20,
            'qty_material_return' => 0,
            'target_qty_estimasi' => 20,
            'actual_qty_prod'     => 10,
            'qty_ng'              => 0,
            'qty_wip'             => 10,
            'status_lot'          => 'ON PROGRESS',
        ]);
        LotTracer::create([
            'lot_number'          => 'M261003',
            'process_id'          => $procShearing->id,
            'material_item_id'    => $rmTrapesium->id,
            'target_item_id'      => $fgGussetL->id,
            'qty_material_out'    => 20,
            'qty_material_return' => 0,
            'target_qty_estimasi' => 20,
            'actual_qty_prod'     => 10,
            'qty_ng'              => 0,
            'qty_wip'             => 10,
            'status_lot'          => 'ON PROGRESS',
        ]);

        // Lot 4: K261004 — Spot Welding Suspension Arm (KOMBINASI Multi-Input & Multi-Output)
        MaterialOut::create([
            'date_time'        => now()->subHours(1),
            'material_item_id' => $fgkSharedBase->id,
            'target_item_id'   => $fgArmRh->id,
            'user_id'          => $staff->id,
            'lot_number'       => 'K261004',
            'qty'              => 25,
            'qty_check'        => 'PENDING',
        ]);
        MaterialOut::create([
            'date_time'        => now()->subHours(1),
            'material_item_id' => $fgkSharedBase->id,
            'target_item_id'   => $fgArmLh->id,
            'user_id'          => $staff->id,
            'lot_number'       => 'K261004',
            'qty'              => 25,
            'qty_check'        => 'PENDING',
        ]);
        LotTracer::create([
            'lot_number'          => 'K261004',
            'process_id'          => $procSpotWeld->id,
            'material_item_id'    => $fgkSharedBase->id,
            'target_item_id'      => $fgArmRh->id,
            'qty_material_out'    => 25,
            'qty_material_return' => 0,
            'target_qty_estimasi' => 25,
            'actual_qty_prod'     => 15,
            'qty_ng'              => 0,
            'qty_wip'             => 10,
            'status_lot'          => 'ON PROGRESS',
        ]);
        LotTracer::create([
            'lot_number'          => 'K261004',
            'process_id'          => $procSpotWeld->id,
            'material_item_id'    => $fgkSharedBase->id,
            'target_item_id'      => $fgArmLh->id,
            'qty_material_out'    => 25,
            'qty_material_return' => 0,
            'target_qty_estimasi' => 25,
            'actual_qty_prod'     => 15,
            'qty_ng'              => 0,
            'qty_wip'             => 10,
            'status_lot'          => 'ON PROGRESS',
        ]);

        // Lot 5: L261006 — Stamping Clip dari COIL SPHC 2.0 X 150MM (Siap Uji Set FINISH -> Top / End Coil!)
        // Mat. Out = 100 KG Coil, qty_usage = 0.5 KG/PCS -> Target Est = 200 PCS.
        // Sudah diproduksi 190 PCS (terpakai 95 KG), sisa di feeder (Top/End Coil) = 5 KG (WIP = 10 PCS).
        MaterialOut::create([
            'date_time'        => now()->subMinutes(50),
            'material_item_id' => $rmCoil->id,
            'target_item_id'   => $kmpClipCoil->id,
            'user_id'          => $staff->id,
            'lot_number'       => 'L261006',
            'qty'              => 100,
            'qty_check'        => 'SESUAI',
        ]);
        LotTracer::create([
            'lot_number'          => 'L261006',
            'process_id'          => $procStamping->id,
            'material_item_id'    => $rmCoil->id,
            'target_item_id'      => $kmpClipCoil->id,
            'qty_material_out'    => 100,
            'qty_material_return' => 0,
            'target_qty_estimasi' => 200,
            'actual_qty_prod'     => 190,
            'qty_ng'              => 0,
            'qty_wip'             => 10,
            'status_lot'          => 'ON PROGRESS',
        ]);
        Production::create([
            'date_time'  => now()->subMinutes(25),
            'item_id'    => $kmpClipCoil->id,
            'user_id'    => $staff->id,
            'lot_number' => 'L261006',
            'qty'        => 190,
            'qty_check'  => 'SESUAI',
        ]);

        Delivery::create([
            'date_time' => now()->subMinutes(45),
            'item_id'   => $fgBracket->id,
            'user_id'   => $staff->id,
            'qty'       => 30,
            'qty_check' => 'PENDING',
        ]);

        ProblemDelay::create([
            'date_time'      => now()->subMinutes(30),
            'company_id'     => $compAstra->id,
            'item_id'        => $fgCover->id,
            'user_id'        => $ppic->id,
            'problem_reason' => 'PROSES',
        ]);

        StockAdjustment::create([
            'date_time'       => now()->subMinutes(20),
            'item_id'         => $fgBracket->id,
            'user_id'         => $ppic->id,
            'qty_system'      => 135,
            'qty_actual'      => 140,
            'qty_diff'        => 5,
            'adjustment_type' => 'REPAIR',
            'notes'           => 'Penambahan hasil selesai repair las bracket muffler.',
        ]);
    }
}