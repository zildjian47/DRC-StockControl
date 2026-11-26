<x-guest-layout variant="split">
    <div class="mb-6">
        <h2 class="page-title" style="font-size:26px">Masuk</h2>
        <p class="page-sub">Gunakan username atau email beserta password akun DRC Stock Control Anda.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <x-input-label for="email" value="Username atau Email" />
            <x-text-input id="email" class="block w-full" type="text" name="email" :value="old('email')"
                          placeholder="Contoh: ppic atau andi@gmail.com"
                          required autofocus autocomplete="username" />
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

        @if (Route::has('register'))
            <div class="pt-3 border-t border-slate-200 text-center text-[13px]" style="color:var(--mut)">
                Belum punya akun?
                <a class="link-act font-semibold ml-1" href="{{ route('register') }}">Daftar akun baru</a>
            </div>
        @endif
    </form>
</x-guest-layout>