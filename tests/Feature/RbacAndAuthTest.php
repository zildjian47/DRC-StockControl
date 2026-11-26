<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RbacAndAuthTest extends TestCase
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

    public function test_01_registrasi_mandiri_status_pending_tanpa_auto_login_dan_blokir_pengajuan_ppic(): void
    {
        $this->get(route('register'))->assertOk();

        // Pendaftar publik tidak boleh mengajukan role PPIC
        $this->post(route('register'), [
            'name'                  => 'Penyusup PPIC',
            'username'              => 'penyusup',
            'email'                 => 'penyusup@gmail.com',
            'requested_role'        => User::ROLE_PPIC,
            'password'              => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
        ])->assertSessionHasErrors('requested_role');

        $this->assertDatabaseMissing('users', ['email' => 'penyusup@gmail.com']);

        // Pendaftaran valid sebagai STAFF_MATERIAL -> tersimpan PENDING dan tidak auto-login
        $this->post(route('register'), [
            'name'                  => 'Deni Kurniawan',
            'username'              => 'Deni.RM',
            'email'                 => 'Deni.RM@gmail.com',
            'requested_role'        => User::ROLE_STAFF_MATERIAL,
            'password'              => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
        ])->assertRedirect(route('login'))
          ->assertSessionHas('status');

        $this->assertGuest();

        $pending = User::where('email', 'deni.rm@gmail.com')->firstOrFail();
        $this->assertSame('deni.rm', $pending->username);
        $this->assertSame(User::STATUS_PENDING, $pending->status);
        $this->assertSame(User::ROLE_STAFF_MATERIAL, $pending->requested_role);

        // Akun PENDING wajib ditolak saat mencoba login pakai username maupun email
        $this->post(route('login'), [
            'email'    => 'deni.rm',
            'password' => 'Rahasia123!',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login'), [
            'email'    => 'deni.rm@gmail.com',
            'password' => 'Rahasia123!',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_02_ppic_acc_pendaftar_login_username_email_dan_redirect_beranda_sesuai_role(): void
    {
        $pending = User::create([
            'name'           => 'Fajar Hidayat',
            'username'       => 'fajar.op',
            'email'          => 'fajar.op@gmail.com',
            'role'           => User::ROLE_STAFF_MATERIAL,
            'requested_role' => User::ROLE_STAFF_MATERIAL,
            'status'         => User::STATUS_PENDING,
            'password'       => Hash::make('Rahasia123!'),
        ]);

        // PPIC membuka halaman Manajemen User & ACC sambil menetapkan role final STAFF_FG
        $this->actingAs($this->ppic)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Fajar Hidayat');

        $this->actingAs($this->ppic)
            ->patch(route('users.approve', $pending->id), [
                'name'     => 'Fajar Hidayat (FG)',
                'username' => 'fajar.fg',
                'email'    => 'fajar.op@gmail.com',
                'role'     => User::ROLE_STAFF_FG,
            ])->assertRedirect(route('users.index'))
              ->assertSessionHas('success');

        $pending->refresh();
        $this->assertSame(User::STATUS_ACTIVE, $pending->status);
        $this->assertSame(User::ROLE_STAFF_FG, $pending->role);
        $this->assertSame('fajar.fg', $pending->username);
        $this->assertSame($this->ppic->id, $pending->approved_by);
        $this->assertNotNull($pending->approved_at);
        $this->assertNotNull($pending->email_verified_at);

        // Logout PPIC, lalu Fajar login pakai USERNAME -> langsung ke Portal Scanner
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email'    => 'fajar.fg',
            'password' => 'Rahasia123!',
        ])->assertRedirect(route('scanner.portal', absolute: false));
        $this->assertAuthenticatedAs($pending);

        // Akses '/' sebagai Staff -> arahkan ke Portal Scanner
        $this->get('/')->assertRedirect(route('scanner.portal'));

        // Logout lalu login pakai EMAIL -> juga berhasil ke Portal Scanner
        $this->post(route('logout'));
        $this->post(route('login'), [
            'email'    => 'fajar.op@gmail.com',
            'password' => 'Rahasia123!',
        ])->assertRedirect(route('scanner.portal', absolute: false));
        $this->assertAuthenticatedAs($pending);

        // Login PPIC pakai USERNAME -> langsung ke Dashboard Desktop
        $this->post(route('logout'));
        $this->post(route('login'), [
            'email'    => 'ppic',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($this->ppic);
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_03_ppic_tolak_pendaftar_pending_dan_larang_hapus_permanen_akun_aktif(): void
    {
        $pending = User::create([
            'name'           => 'Akun Tidak Dikenal',
            'username'       => 'unknown.user',
            'email'          => 'unknown@gmail.com',
            'role'           => User::ROLE_STAFF_MATERIAL,
            'requested_role' => User::ROLE_STAFF_MATERIAL,
            'status'         => User::STATUS_PENDING,
            'password'       => Hash::make('Rahasia123!'),
        ]);

        // Akun PENDING boleh ditolak & dihapus
        $this->actingAs($this->ppic)
            ->delete(route('users.destroy', $pending->id))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $pending->id]);

        // Akun ACTIVE dilarang dihapus permanen demi jejak audit transaksi
        $this->actingAs($this->ppic)
            ->delete(route('users.destroy', $this->staffMaterial->id))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->staffMaterial->id]);
    }

    public function test_04_matriks_rbac_3_role_redirect_scanner_otomatis_dan_blokir_lintas_divisi(): void
    {
        // A. STAFF_MATERIAL: hanya boleh 4 modul material (selalu mode scanner)
        $this->actingAs($this->staffMaterial);
        $this->get(route('scanner.portal'))
            ->assertOk()
            ->assertSee('Inbound')
            ->assertSee('Material Out')
            ->assertSee('Material Return')
            ->assertSee('Return to Supplier')
            ->assertDontSee('Stock Adjustment');

        // Akses desktop tanpa ?mode=scanner otomatis dialihkan ke ?mode=scanner
        $this->get(route('inbounds.index'))
            ->assertRedirect(route('inbounds.index', ['mode' => 'scanner']));
        $this->get(route('material_outs.index'))
            ->assertRedirect(route('material_outs.index', ['mode' => 'scanner']));

        // Akses 4 modul scanner miliknya -> 200 OK
        $this->get(route('inbounds.index', ['mode' => 'scanner']))->assertOk()->assertSee('Portal Scanner');
        $this->get(route('material_outs.index', ['mode' => 'scanner']))->assertOk();
        $this->get(route('material_returns.index', ['mode' => 'scanner']))->assertOk();
        $this->get(route('return_to_suppliers.index', ['mode' => 'scanner']))->assertOk();

        // Diblokir (403) pada menu PPIC & modul STAFF_FG & koreksi verify_batch
        $this->get(route('dashboard'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('items.index'))->assertForbidden();
        $this->get(route('boms.index'))->assertForbidden();
        $this->get(route('inventory_stocks.index'))->assertForbidden();
        $this->get(route('lot_tracers.index'))->assertForbidden();
        $this->get(route('stock_adjustments.index'))->assertForbidden();
        $this->get(route('problem_delays.index'))->assertForbidden();
        $this->get(route('productions.index', ['mode' => 'scanner']))->assertForbidden();
        $this->get(route('production_ngs.index', ['mode' => 'scanner']))->assertForbidden();
        $this->get(route('deliveries.index', ['mode' => 'scanner']))->assertForbidden();
        $this->post(route('inbounds.verify_batch'), ['items' => []])->assertForbidden();
        $this->getJson(route('deliveries.lookup', ['code' => 'TEST']))
            ->assertForbidden()
            ->assertJson(['ok' => false]);

        // B. STAFF_FG: hanya boleh 3 modul FG (selalu mode scanner)
        $this->actingAs($this->staffFg);
        $this->get(route('scanner.portal'))
            ->assertOk()
            ->assertSee('Production')
            ->assertSee('Form NG')
            ->assertSee('Delivery')
            ->assertDontSee('Material Out');

        $this->get(route('productions.index'))
            ->assertRedirect(route('productions.index', ['mode' => 'scanner']));
        $this->get(route('deliveries.index'))
            ->assertRedirect(route('deliveries.index', ['mode' => 'scanner']));

        $this->get(route('productions.index', ['mode' => 'scanner']))->assertOk()->assertSee('Portal Scanner');
        $this->get(route('production_ngs.index', ['mode' => 'scanner']))->assertOk();
        $this->get(route('deliveries.index', ['mode' => 'scanner']))->assertOk();

        // Diblokir (403) pada menu PPIC & modul STAFF_MATERIAL & koreksi verify_batch
        $this->get(route('dashboard'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('stock_adjustments.index'))->assertForbidden();
        $this->get(route('inbounds.index', ['mode' => 'scanner']))->assertForbidden();
        $this->get(route('material_outs.index', ['mode' => 'scanner']))->assertForbidden();
        $this->get(route('material_returns.index', ['mode' => 'scanner']))->assertForbidden();
        $this->get(route('return_to_suppliers.index', ['mode' => 'scanner']))->assertForbidden();
        $this->post(route('productions.verify_batch'), ['items' => []])->assertForbidden();
        $this->getJson(route('inbounds.lookup', ['code' => 'TEST']))
            ->assertForbidden()
            ->assertJson(['ok' => false]);

        // C. PPIC: akses penuh Desktop, Manajemen User, Stock Adjustment, dan Portal Scanner
        $this->actingAs($this->ppic);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('users.index'))->assertOk();
        $this->get(route('inbounds.index'))->assertOk();
        $this->get(route('productions.index'))->assertOk();
        $this->get(route('stock_adjustments.index'))->assertOk();
        $this->get(route('scanner.portal'))->assertOk()->assertSee('Stock Adjustment');
    }

    public function test_05_nonaktifkan_akun_auto_logout_instan_anti_self_lockout_dan_kunci_hapus_profil(): void
    {
        // Guardrail anti self-lockout: PPIC tidak bisa menonaktifkan atau menurunkan role sendiri
        $this->actingAs($this->ppic)
            ->put(route('users.update', $this->ppic->id), [
                'name'     => $this->ppic->name,
                'username' => $this->ppic->username,
                'email'    => $this->ppic->email,
                'role'     => User::ROLE_PPIC,
                'status'   => User::STATUS_INACTIVE,
            ])->assertSessionHas('error');
        $this->assertSame(User::STATUS_ACTIVE, $this->ppic->fresh()->status);

        $this->actingAs($this->ppic)
            ->put(route('users.update', $this->ppic->id), [
                'name'     => $this->ppic->name,
                'username' => $this->ppic->username,
                'email'    => $this->ppic->email,
                'role'     => User::ROLE_STAFF_MATERIAL,
                'status'   => User::STATUS_ACTIVE,
            ])->assertSessionHas('error');
        $this->assertSame(User::ROLE_PPIC, $this->ppic->fresh()->role);

        // Profil staff tampil memakai layout scanner (bukan sidebar desktop) & hapus akun mandiri terkunci 403
        $this->actingAs($this->staffMaterial)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Portal Scanner')
            ->assertDontSee('Manajemen User');

        $this->actingAs($this->staffMaterial)
            ->delete(route('profile.destroy'), ['password' => 'password123'])
            ->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $this->staffMaterial->id]);

        // PPIC menonaktifkan akun Staff Material (misal resign)
        $this->actingAs($this->ppic)
            ->put(route('users.update', $this->staffMaterial->id), [
                'name'     => $this->staffMaterial->name,
                'username' => $this->staffMaterial->username,
                'email'    => $this->staffMaterial->email,
                'role'     => User::ROLE_STAFF_MATERIAL,
                'status'   => User::STATUS_INACTIVE,
            ])->assertRedirect(route('users.index'))
              ->assertSessionHas('success');
        $this->assertSame(User::STATUS_INACTIVE, $this->staffMaterial->fresh()->status);

        // Auto-logout instan (middleware active) saat sesi lama karyawan nonaktif melakukan request
        $this->actingAs($this->staffMaterial->fresh())
            ->get(route('scanner.portal'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Login ulang juga ditolak
        $this->post(route('login'), [
            'email'    => 'andi.rm',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_06_ppic_kirim_tautan_reset_password_hanya_untuk_akun_aktif(): void
    {
        Notification::fake();

        // Kirim tautan reset password ke akun ACTIVE -> sukses & notifikasi ResetPassword terkirim
        $this->actingAs($this->ppic)
            ->post(route('users.send_reset_link', $this->staffFg->id))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        Notification::assertSentTo($this->staffFg, ResetPassword::class);

        // Akun PENDING tidak boleh dikirimi tautan reset password
        $pending = User::create([
            'name'           => 'Calon Staff',
            'username'       => 'calon.fg',
            'email'          => 'calon.fg@gmail.com',
            'role'           => User::ROLE_STAFF_FG,
            'requested_role' => User::ROLE_STAFF_FG,
            'status'         => User::STATUS_PENDING,
            'password'       => Hash::make('Rahasia123!'),
        ]);

        $this->actingAs($this->ppic)
            ->post(route('users.send_reset_link', $pending->id))
            ->assertSessionHas('error');

        Notification::assertNotSentTo($pending, ResetPassword::class);
    }
}