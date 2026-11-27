<section>
    <header>
        <h2 class="panel-title">Perbarui Password</h2>
        <p class="page-sub">Pastikan akun Anda menggunakan password yang kuat untuk menjaga keamanan.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-5 space-y-4">
        @csrf
        @method('put')

        <div x-data="{ show: false }">
            <x-input-label for="update_password_current_password" value="Password Saat Ini" />
            <div class="relative">
                <input id="update_password_current_password" name="current_password" :type="show ? 'text' : 'password'" class="block w-full pr-11" autocomplete="current-password" />
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 w-10 grid place-items-center" style="color:var(--mut)" aria-label="Tampilkan atau sembunyikan password saat ini">
                    <x-icon x-show="!show" name="eye" />
                    <x-icon x-show="show" x-cloak name="eye-off" />
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1.5" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="update_password_password" value="Password Baru" />
            <div class="relative">
                <input id="update_password_password" name="password" :type="show ? 'text' : 'password'" class="block w-full pr-11" autocomplete="new-password" />
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 w-10 grid place-items-center" style="color:var(--mut)" aria-label="Tampilkan atau sembunyikan password baru">
                    <x-icon x-show="!show" name="eye" />
                    <x-icon x-show="show" x-cloak name="eye-off" />
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1.5" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="update_password_password_confirmation" value="Konfirmasi Password Baru" />
            <div class="relative">
                <input id="update_password_password_confirmation" name="password_confirmation" :type="show ? 'text' : 'password'" class="block w-full pr-11" autocomplete="new-password" />
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 w-10 grid place-items-center" style="color:var(--mut)" aria-label="Tampilkan atau sembunyikan konfirmasi password baru">
                    <x-icon x-show="!show" name="eye" />
                    <x-icon x-show="show" x-cloak name="eye-off" />
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1.5" />
        </div>

        <div class="flex items-center gap-3 pt-2">
            <x-primary-button>Simpan Password</x-primary-button>

            @if (session('status') === 'password-updated')
                <span x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="pill pill-good">
                    Password Diperbarui
                </span>
            @endif
        </div>
    </form>
</section>