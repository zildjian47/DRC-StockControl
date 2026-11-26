<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\Process;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ItemCardAndQrTest extends TestCase
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

    private function setupSampleItemsViaHttp(): array
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

        // RM tanpa Part No (termasuk tanda kutip ")
        $this->post(route('items.store'), [
            'part_no'      => null,
            'part_name'    => 'PIPA 2" SCH40',
            'type'         => 'RM',
            'uom'          => 'BATANG',
            'company_id'   => $supKs->id,
            'qty_po'       => 0,
            'working_days' => 20,
            'qty_kanban'   => 0,
        ])->assertSessionHasNoErrors();

        // Komponen hasil produksi dari RM
        $this->post(route('items.store'), [
            'part_no'      => 'KMP-BRK-01',
            'part_name'    => 'BRACKET BASE',
            'type'         => 'KOMPONEN',
            'uom'          => 'PCS',
            'company_id'   => $intDrc->id,
            'qty_po'       => 0,
            'working_days' => 20,
            'qty_kanban'   => 0,
        ])->assertSessionHasNoErrors();

        // FG dengan Qty PO unik (4321) agar mudah diverifikasi tersembunyi bagi non-PPIC
        // std_day_1 = ceil((4321/20)/50)*50 = 250 (angka 4321 tidak muncul di Std Day)
        $this->post(route('items.store'), [
            'part_no'      => 'FG-STAY-01',
            'part_name'    => 'STAY LOWER ASSY',
            'type'         => 'FG',
            'uom'          => 'PCS',
            'company_id'   => $cusDpm->id,
            'qty_po'       => 4321,
            'working_days' => 20,
            'qty_kanban'   => 50,
        ])->assertSessionHasNoErrors();

        $rm  = Item::where('part_name', 'PIPA 2" SCH40')->firstOrFail();
        $kmp = Item::where('part_no', 'KMP-BRK-01')->firstOrFail();
        $fg  = Item::where('part_no', 'FG-STAY-01')->firstOrFail();

        // BOM SINGLE: PIPA 2" SCH40 (0.5 BATANG) -> KMP-BRK-01
        $this->post(route('boms.store'), [
            'process_id'    => $pStamp->id,
            'trigger_index' => 0,
            'inputs'        => [
                ['item_id' => $rm->id, 'qty_usage' => '0.5'],
            ],
            'outputs'       => [
                ['item_id' => $kmp->id],
            ],
        ])->assertSessionHas('success');

        return compact('supKs', 'intDrc', 'cusDpm', 'pStamp', 'rm', 'kmp', 'fg');
    }

    public function test_01_portal_scanner_menampilkan_cek_info_stok_untuk_semua_role(): void
    {
        foreach ([$this->ppic, $this->staffMaterial, $this->staffFg] as $user) {
            $this->actingAs($user)
                ->get(route('scanner.portal'))
                ->assertOk()
                ->assertSee('Cek Info Stok')
                ->assertSee(route('item_cards.scanner'));

            $this->actingAs($user)
                ->get(route('item_cards.scanner'))
                ->assertOk()
                ->assertSee('Scan QR Rak atau Label Part');
        }
    }

    public function test_02_lookup_pintar_kebal_ganti_domain_kebal_ganti_nama_dan_dukung_stiker_lama(): void
    {
        $m  = $this->setupSampleItemsViaHttp();
        $rm = $m['rm'];
        $fg = $m['fg'];

        $this->actingAs($this->staffMaterial);

        // 1. Scan QR URL dari domain / tunnel lama -> domain lama dibuang, langsung arahkan ke domain aktif
        $this->getJson(route('item_cards.lookup', [
            'code' => "https://tunnel-lama-999.trycloudflare.com/item-card/{$fg->id}?ref=rak",
        ]))
            ->assertOk()
            ->assertJson([
                'ok'  => true,
                'url' => route('item_cards.show', $fg->id),
                'item' => [
                    'id'      => $fg->id,
                    'part_no' => 'FG-STAY-01',
                ],
            ]);

        // 2. Scan kode pendek ITEM:{id}
        $this->getJson(route('item_cards.lookup', ['code' => "ITEM:{$rm->id}"]))
            ->assertOk()
            ->assertJson([
                'ok'  => true,
                'url' => route('item_cards.show', $rm->id),
            ]);

        // 3. Scan stiker transaksi lama (Part No huruf kecil & Part Name RM mengandung kutip ganda ")
        $this->getJson(route('item_cards.lookup', ['code' => 'fg-stay-01']))
            ->assertOk()
            ->assertJson([
                'ok'  => true,
                'url' => route('item_cards.show', $fg->id),
            ]);

        $this->getJson(route('item_cards.lookup', ['code' => 'PIPA 2" SCH40']))
            ->assertOk()
            ->assertJson([
                'ok'  => true,
                'url' => route('item_cards.show', $rm->id),
            ]);

        // 4. Uji kebal ganti nama & ganti Part No: PPIC ubah nama item FG (belum ada riwayat)
        $this->actingAs($this->ppic);
        $this->put(route('items.update', $fg->id), [
            'part_no'      => 'FG-STAY-REV2',
            'part_name'    => 'STAY LOWER ASSY REVISI 2',
            'type'         => 'FG',
            'uom'          => 'PCS',
            'company_id'   => $m['cusDpm']->id,
            'qty_po'       => 4321,
            'working_days' => 20,
            'qty_kanban'   => 50,
        ])->assertSessionHas('success');

        // QR stiker lama yang berisi URL ID permanen tetap menemukan nama baru tanpa cetak ulang
        $this->actingAs($this->staffFg);
        $this->getJson(route('item_cards.lookup', [
            'code' => "https://domain-lama.drc.co.id/item-card/{$fg->id}",
        ]))
            ->assertOk()
            ->assertJson([
                'ok'   => true,
                'url'  => route('item_cards.show', $fg->id),
                'item' => [
                    'part_no'   => 'FG-STAY-REV2',
                    'part_name' => 'STAY LOWER ASSY REVISI 2',
                ],
            ]);

        // 5. Item yang dinonaktifkan (soft-deleted) atau kode kosong/tidak ada wajib ditolak
        $this->actingAs($this->ppic);
        $this->delete(route('items.destroy', $m['kmp']->id))->assertSessionHas('success');
        $this->assertSoftDeleted('items', ['id' => $m['kmp']->id]);

        $this->actingAs($this->staffMaterial);
        $this->getJson(route('item_cards.lookup', ['code' => "https://stok.drc.co.id/item-card/{$m['kmp']->id}"]))
            ->assertOk()
            ->assertJson(['ok' => false]);

        $this->getJson(route('item_cards.lookup', ['code' => '']))
            ->assertOk()
            ->assertJson(['ok' => false]);

        $this->getJson(route('item_cards.lookup', ['code' => 'TIDAK-TERDAFTAR']))
            ->assertOk()
            ->assertJson(['ok' => false]);
    }

    public function test_03_privasi_qty_po_hanya_tampil_untuk_ppic_dan_tersembunyi_untuk_staff(): void
    {
        $m  = $this->setupSampleItemsViaHttp();
        $fg = $m['fg'];

        // PPIC boleh melihat Parameter PO (Qty PO = 4321) dan tombol Cetak QR
        $ppicResp = $this->actingAs($this->ppic)
            ->get(route('item_cards.show', $fg->id))
            ->assertOk()
            ->assertSee('STAY LOWER ASSY')
            ->assertSee('Parameter PO (Khusus PPIC)')
            ->assertSee('4321')
            ->assertSee('Cetak QR');

        $this->assertTrue($ppicResp->viewData('canViewPo'));
        $this->assertEquals(4321.0, (float) $ppicResp->viewData('poParams')['qty_po']);

        // STAFF_MATERIAL dan STAFF_FG tidak boleh melihat blok PO, angka 4321, maupun tombol Cetak QR
        foreach ([$this->staffMaterial, $this->staffFg] as $staff) {
            $resp = $this->actingAs($staff)
                ->get(route('item_cards.show', $fg->id))
                ->assertOk()
                ->assertSee('STAY LOWER ASSY')
                ->assertSee('Stock Saat Ini')
                ->assertSee('Stock 07:00')
                ->assertSee('250') // Std Day 1 tetap tampil
                ->assertDontSee('Parameter PO (Khusus PPIC)')
                ->assertDontSee('Qty PO')
                ->assertDontSee('4321')
                ->assertDontSee('Cetak QR');

            $this->assertFalse($resp->viewData('canViewPo'));
            $this->assertNull($resp->viewData('poParams'));
        }
    }

    public function test_04_smart_redirect_login_kamera_hp_ke_item_card_dan_tolak_dashboard_staff(): void
    {
        $m  = $this->setupSampleItemsViaHttp();
        $fg = $m['fg'];

        // Logout dulu agar menjadi guest (simulasi HP operator belum login saat scan kamera bawaan)
        $this->post(route('logout'));
        $this->assertGuest();

        // 1. Guest scan QR rak /item-card/{id} -> diminta login -> setelah login Staff langsung mendarat ke kartu item
        $this->get(route('item_cards.show', $fg->id))
            ->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email'    => 'andi.rm',
            'password' => 'password123',
        ])->assertRedirect(route('item_cards.show', $fg->id));
        $this->assertAuthenticatedAs($this->staffMaterial);

        // 2. Guest buka /dashboard -> diminta login -> jika yang login Staff FG, TIDAK boleh diarahkan ke /dashboard
        $this->post(route('logout'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email'    => 'riko.fg',
            'password' => 'password123',
        ])->assertRedirect(route('scanner.portal', absolute: false));
        $this->assertAuthenticatedAs($this->staffFg);

        // 3. Guest scan QR rak /item-card/{id} -> login PPIC -> juga langsung mendarat ke kartu item
        $this->post(route('logout'));
        $this->get(route('item_cards.show', $fg->id))->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email'    => 'ppic',
            'password' => 'password123',
        ])->assertRedirect(route('item_cards.show', $fg->id));
        $this->assertAuthenticatedAs($this->ppic);
    }

    public function test_05_kartu_info_item_menampilkan_stock_0700_dan_lot_wip_aktif_secara_akurat(): void
    {
        // Bekukan waktu pukul 06:30 WIB (sebelum cut-off 07:00)
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 6, 30, 0, config('app.timezone')));

        $m   = $this->setupSampleItemsViaHttp();
        $rm  = $m['rm'];
        $kmp = $m['kmp'];

        // Transaksi sebelum 07:00 -> saldo 07:00: RM = 100 BATANG, KMP = 0 PCS
        $this->actingAs($this->staffMaterial);
        $this->post(route('inbounds.store'), [
            'item_id' => $rm->id,
            'qty'     => '100',
        ])->assertSessionHas('success');

        // Maju ke 09:00 WIB (setelah cut-off 07:00): Material Out 40 BATANG -> Lot K260001 (est 80 PCS)
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 9, 0, 0, config('app.timezone')));
        $this->post(route('material_outs.store'), [
            'material_item_id' => $rm->id,
            'target_item_id'   => $kmp->id,
            'lot_number'       => 'K260001',
            'qty'              => '40',
        ])->assertSessionHas('success');

        // Produksi 30 PCS oleh STAFF_FG -> saldo KMP = 30, sisa WIP Lot K260001 = 50 PCS
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 10, 0, 0, config('app.timezone')));
        $this->actingAs($this->staffFg);
        $this->post(route('productions.store'), [
            'item_id'    => $kmp->id,
            'lot_number' => 'K260001',
            'qty'        => '30',
        ])->assertSessionHas('success');

        // Cek Kartu Info RM oleh STAFF_MATERIAL
        $rmResp = $this->actingAs($this->staffMaterial)
            ->get(route('item_cards.show', $rm->id))
            ->assertOk()
            ->assertSee('K260001')
            ->assertSee('DIPAKAI DI MESIN');

        $this->assertEquals(100.0, (float) $rmResp->viewData('stock07'));
        $this->assertEquals(60.0,  (float) $rmResp->viewData('info')['current_stock']);
        $this->assertCount(1, $rmResp->viewData('activeLots'));
        $this->assertSame('MATERIAL', $rmResp->viewData('activeLots')[0]['role']);
        $this->assertEquals(50.0, (float) $rmResp->viewData('activeLots')[0]['qty_wip']);

        // Cek Kartu Info KMP oleh STAFF_FG
        $kmpResp = $this->actingAs($this->staffFg)
            ->get(route('item_cards.show', $kmp->id))
            ->assertOk()
            ->assertSee('K260001')
            ->assertSee('SEDANG DIPRODUKSI');

        $this->assertEquals(0.0,  (float) $kmpResp->viewData('stock07'));
        $this->assertEquals(30.0, (float) $kmpResp->viewData('info')['current_stock']);
        $this->assertCount(1, $kmpResp->viewData('activeLots'));
        $this->assertSame('OUTPUT', $kmpResp->viewData('activeLots')[0]['role']);
        $this->assertEquals(30.0, (float) $kmpResp->viewData('activeLots')[0]['actual_qty_prod']);
        $this->assertEquals(50.0, (float) $kmpResp->viewData('activeLots')[0]['qty_wip']);
    }

    public function test_06_cetak_label_qr_khusus_ppic_mendukung_satuan_batch_dan_semua(): void
    {
        $m   = $this->setupSampleItemsViaHttp();
        $rm  = $m['rm'];
        $kmp = $m['kmp'];
        $fg  = $m['fg'];

        // Non-PPIC wajib ditolak 403
        $this->actingAs($this->staffMaterial)
            ->get(route('items.qr_print', ['all' => 1]))
            ->assertForbidden();

        $this->actingAs($this->staffFg)
            ->get(route('items.qr_print', ['item_id' => $fg->id]))
            ->assertForbidden();

        // PPIC tanpa memilih item -> kembali ke Master Item dengan pesan error
        $this->actingAs($this->ppic)
            ->get(route('items.qr_print'))
            ->assertRedirect(route('items.index'))
            ->assertSessionHas('error');

        // PPIC cetak 1 item -> memuat URL permanen /item-card/{id}
        $singleResp = $this->actingAs($this->ppic)
            ->get(route('items.qr_print', ['item_id' => $fg->id]))
            ->assertOk()
            ->assertSee('STAY LOWER ASSY')
            ->assertSee(route('item_cards.show', $fg->id));
        $this->assertCount(1, $singleResp->viewData('labels'));
        $this->assertSame('a4_3', $singleResp->viewData('layout'));

        // PPIC cetak batch terpilih + layout termal
        $batchResp = $this->actingAs($this->ppic)
            ->get(route('items.qr_print', [
                'ids'    => "{$rm->id},{$kmp->id}",
                'layout' => 'thermal',
            ]))
            ->assertOk()
            ->assertSee('PIPA 2&quot; SCH40', false)
            ->assertSee('BRACKET BASE');
        $this->assertCount(2, $batchResp->viewData('labels'));
        $this->assertSame('thermal', $batchResp->viewData('layout'));

        // PPIC cetak semua dengan filter tipe RM
        $allRmResp = $this->actingAs($this->ppic)
            ->get(route('items.qr_print', ['all' => 1, 'type' => 'RM']))
            ->assertOk();
        $this->assertCount(1, $allRmResp->viewData('labels'));
        $this->assertSame($rm->id, $allRmResp->viewData('labels')[0]['id']);
    }
}