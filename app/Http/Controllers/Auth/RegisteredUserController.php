<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Pendaftaran mandiri: akun disimpan berstatus PENDING (tanpa auto-login)
     * hingga diverifikasi & disetujui (ACC) oleh PPIC.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'name'           => trim((string) $request->input('name')),
            'username'       => Str::lower(trim((string) $request->input('username'))),
            'email'          => Str::lower(trim((string) $request->input('email'))),
            'requested_role' => strtoupper(trim((string) $request->input('requested_role'))),
        ]);

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'username'       => ['required', 'string', 'lowercase', 'min:3', 'max:40', 'regex:/^[a-z0-9._-]+$/', 'unique:'.User::class],
            'email'          => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'requested_role' => ['required', 'in:'.implode(',', User::STAFF_ROLES)],
            'password'       => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'username.regex'    => 'Username hanya boleh berisi huruf kecil, angka, titik (.), garis bawah (_), atau tanda hubung (-).',
            'username.unique'   => 'Username sudah digunakan, silakan pilih username lain.',
            'email.unique'      => 'Email sudah terdaftar di sistem.',
            'requested_role.in' => 'Pilih divisi pengajuan yang valid (Staff Material atau Staff FG).',
        ]);

        $user = User::create([
            'name'           => $validated['name'],
            'username'       => $validated['username'],
            'email'          => $validated['email'],
            'role'           => $validated['requested_role'],
            'requested_role' => $validated['requested_role'],
            'status'         => User::STATUS_PENDING,
            'password'       => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        return redirect()->route('login')
            ->with('status', 'Pendaftaran berhasil. Akun Anda sedang menunggu verifikasi dan persetujuan (ACC) dari PPIC sebelum dapat digunakan untuk masuk.');
    }
}