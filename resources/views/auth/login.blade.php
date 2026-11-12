<x-guest-layout variant="split">
    <div class="mb-6">
        <h2 class="page-title" style="font-size:26px">Masuk</h2>
        <p class="page-sub">Gunakan email dan password akun DRC Stock Control Anda.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="password" value="Password" />
            <div class="relative">
                <input id="password" class="block w-full pr-11" :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password" />
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 w-10 grid place-items-center" style="color:var(--mut)" aria-label="Tampilkan atau sembunyikan password">
                    <x-icon x-show="!show" name="eye" />
                    <x-icon x-show="show" x-cloak name="eye-off" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div class="flex items-center justify-between text-[13px]">
            <label for="remember_me" class="inline-flex items-center gap-2 cursor-pointer">
                <input id="remember_me" type="checkbox" name="remember"> Ingat perangkat ini
            </label>
            @if (Route::has('password.request'))
                <a class="link-act" href="{{ route('password.request') }}">Lupa password?</a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-full">Masuk</button>
    </form>
</x-guest-layout>