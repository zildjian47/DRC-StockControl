<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('email') && $this->filled('login')) {
            $this->merge(['email' => $this->input('login')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $rawLogin = trim((string) $this->input('email'));
        $login    = Str::lower($rawLogin);
        $field    = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user     = User::whereRaw("LOWER({$field}) = ?", [$login])->first();
        $password = (string) $this->input('password');

        if (!$user || !Hash::check($password, (string) $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if ($user->isPending()) {
            RateLimiter::clear($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Akun Anda masih menunggu verifikasi dan persetujuan (ACC) dari PPIC.',
            ]);
        }

        if ($user->isInactive()) {
            RateLimiter::clear($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Akun Anda telah dinonaktifkan oleh PPIC. Silakan hubungi PPIC.',
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}