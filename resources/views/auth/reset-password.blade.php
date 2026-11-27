<x-guest-layout>
    <div class="mb-4">
        <h2 class="panel-title">Atur Ulang Password</h2>
        <p class="page-sub">Masukkan password baru untuk akun Anda.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="password" value="Password Baru" />
            <div class="relative">
                <input id="password" class="block w-full pr-11" :type="show ? 'text' : 'password'" name="password" required autocomplete="new-password" />
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 w-10 grid place-items-center" style="color:var(--mut)" aria-label="Tampilkan atau sembunyikan password baru">
                    <x-icon x-show="!show" name="eye" />
                    <x-icon x-show="show" x-cloak name="eye-off" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="password_confirmation" value="Konfirmasi Password Baru" />
            <div class="relative">
                <input id="password_confirmation" class="block w-full pr-11" :type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password" />
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 w-10 grid place-items-center" style="color:var(--mut)" aria-label="Tampilkan atau sembunyikan konfirmasi password baru">
                    <x-icon x-show="!show" name="eye" />
                    <x-icon x-show="show" x-cloak name="eye-off" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
        </div>

        <div class="flex items-center justify-end pt-2">
            <x-primary-button>Simpan Password</x-primary-button>
        </div>
    </form>
</x-guest-layout>