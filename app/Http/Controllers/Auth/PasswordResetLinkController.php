<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = (string) $request->input('email');
        $user  = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user && !$user->isActive()) {
            $message = $user->isPending()
                ? 'Akun Anda masih menunggu persetujuan (ACC) dari PPIC sehingga belum dapat melakukan reset password.'
                : 'Akun Anda telah dinonaktifkan oleh PPIC. Silakan hubungi PPIC untuk bantuan.';

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => $message]);
        }

        try {
            $status = Password::sendResetLink([
                'email' => $user ? $user->email : $email,
            ]);
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email lupa password.', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Gagal mengirim email reset password. Periksa koneksi internet server atau hubungi PPIC.']);
        }

        return $status == Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
    }
}