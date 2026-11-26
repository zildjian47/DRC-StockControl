<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = (string) $request->input('email');
        $user  = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user && !$user->isActive()) {
            $message = $user->isPending()
                ? 'Akun Anda masih menunggu persetujuan (ACC) dari PPIC sehingga belum dapat mengatur ulang password.'
                : 'Akun Anda telah dinonaktifkan oleh PPIC. Silakan hubungi PPIC untuk bantuan.';

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => $message]);
        }

        $credentials = [
            'email'                 => $user ? $user->email : $email,
            'password'              => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation'),
            'token'                 => $request->input('token'),
        ];

        $status = Password::reset(
            $credentials,
            function (User $user) use ($request) {
                $user->forceFill([
                    'password'       => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status == Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
    }
}