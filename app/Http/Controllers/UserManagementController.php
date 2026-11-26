<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::with('approvedBy')
            ->orderByRaw("CASE status WHEN 'PENDING' THEN 1 WHEN 'ACTIVE' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->get();

        $pendingCount  = $users->where('status', User::STATUS_PENDING)->count();
        $activeCount   = $users->where('status', User::STATUS_ACTIVE)->count();
        $inactiveCount = $users->where('status', User::STATUS_INACTIVE)->count();

        return view('users.index', compact(
            'users',
            'pendingCount',
            'activeCount',
            'inactiveCount'
        ));
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        if (!$user->isPending()) {
            return back()->with('error', "Akun {$user->name} tidak sedang berstatus Menunggu ACC.");
        }

        $request->merge([
            'name'     => trim((string) $request->input('name')),
            'username' => Str::lower(trim((string) $request->input('username'))),
            'email'    => Str::lower(trim((string) $request->input('email'))),
            'role'     => strtoupper(trim((string) $request->input('role'))),
        ]);

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'lowercase', 'min:3', 'max:40', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username,' . $user->id],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role'     => ['required', 'in:' . implode(',', User::ROLES)],
        ], [
            'username.regex'  => 'Username hanya boleh berisi huruf kecil, angka, titik (.), garis bawah (_), atau tanda hubung (-).',
            'username.unique' => 'Username sudah dipakai akun lain.',
            'email.unique'    => 'Email sudah dipakai akun lain.',
        ]);

        $user->update([
            'name'              => $validated['name'],
            'username'          => $validated['username'],
            'email'             => $validated['email'],
            'role'              => $validated['role'],
            'status'            => User::STATUS_ACTIVE,
            'approved_by'       => Auth::id(),
            'approved_at'       => now(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        return redirect()->route('users.index')
            ->with('success', "Akun {$user->name} ({$user->role_label}) berhasil di-ACC dan diaktifkan.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->isPending()) {
            return back()->with('error', 'Akun masih berstatus Menunggu ACC. Gunakan tombol ACC untuk memverifikasi dan mengaktifkannya.');
        }

        $request->merge([
            'name'     => trim((string) $request->input('name')),
            'username' => Str::lower(trim((string) $request->input('username'))),
            'email'    => Str::lower(trim((string) $request->input('email'))),
            'role'     => strtoupper(trim((string) $request->input('role'))),
            'status'   => strtoupper(trim((string) $request->input('status'))),
        ]);

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'lowercase', 'min:3', 'max:40', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username,' . $user->id],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role'     => ['required', 'in:' . implode(',', User::ROLES)],
            'status'   => ['required', 'in:' . User::STATUS_ACTIVE . ',' . User::STATUS_INACTIVE],
        ], [
            'username.regex'  => 'Username hanya boleh berisi huruf kecil, angka, titik (.), garis bawah (_), atau tanda hubung (-).',
            'username.unique' => 'Username sudah dipakai akun lain.',
            'email.unique'    => 'Email sudah dipakai akun lain.',
        ]);

        // Guardrail anti self-lockout PPIC
        if ((int) $user->id === (int) Auth::id()) {
            if ($validated['status'] !== User::STATUS_ACTIVE) {
                return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
            }
            if ($validated['role'] !== User::ROLE_PPIC) {
                return back()->with('error', 'Anda tidak dapat menurunkan role akun PPIC Anda sendiri.');
            }
        }

        // Guardrail wajib minimal 1 akun PPIC aktif
        $wasActivePpic = $user->isPpic() && $user->isActive();
        $willRemainActivePpic = $validated['role'] === User::ROLE_PPIC && $validated['status'] === User::STATUS_ACTIVE;

        if ($wasActivePpic && !$willRemainActivePpic) {
            $hasOtherActivePpic = User::where('role', User::ROLE_PPIC)
                ->where('status', User::STATUS_ACTIVE)
                ->where('id', '!=', $user->id)
                ->exists();

            if (!$hasOtherActivePpic) {
                return back()->with('error', 'Minimal harus ada 1 akun PPIC aktif di sistem.');
            }
        }

        $payload = [
            'name'     => $validated['name'],
            'username' => $validated['username'],
            'email'    => $validated['email'],
            'role'     => $validated['role'],
            'status'   => $validated['status'],
        ];

        if ($validated['status'] === User::STATUS_ACTIVE && $user->email_verified_at === null) {
            $payload['email_verified_at'] = now();
        }

        $user->update($payload);

        return redirect()->route('users.index')
            ->with('success', "Data akun {$user->name} berhasil diperbarui.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if (!$user->isPending()) {
            return back()->with('error', 'Akun yang sudah aktif atau pernah disetujui tidak boleh dihapus permanen demi menjaga jejak audit transaksi. Gunakan status Nonaktif (INACTIVE).');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', "Pendaftaran akun {$name} telah ditolak dan dihapus.");
    }

    public function sendResetLink(User $user): RedirectResponse
    {
        if (!$user->isActive()) {
            return back()->with('error', 'Tautan reset password hanya dapat dikirim ke akun yang berstatus AKTIF.');
        }

        try {
            $status = Password::broker()->sendResetLink(['email' => $user->email]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengirim email reset password. Periksa konfigurasi Gmail SMTP + App Password di file .env (' . $e->getMessage() . ').');
        }

        if ($status === Password::RESET_LINK_SENT) {
            return redirect()->route('users.index')
                ->with('success', "Tautan reset password telah dikirim ke {$user->email}.");
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->with('error', 'Tautan reset baru saja dikirim. Mohon tunggu sebentar sebelum mengirim ulang.');
        }

        return back()->with('error', 'Gagal mengirim tautan reset password (' . __($status) . ').');
    }
}