<x-guest-layout variant="split">
    <div class="mb-5">
        <h2 class="page-title" style="font-size:24px">Daftar Akun Baru</h2>
        <p class="page-sub">Isi data diri Anda. Akun akan aktif setelah diverifikasi dan disetujui (ACC) oleh PPIC.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-3.5">
        @csrf

        <div>
            <x-input-label for="name" value="Nama Lengkap" />
            <x-text-input id="name" class="block w-full" type="text" name="name" :value="old('name')"
                          placeholder="Contoh: Andi Pratama" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="username" value="Username (untuk login cepat)" />
            <x-text-input id="username" class="block w-full lowercase" type="text" name="username" :value="old('username')"
                          placeholder="Contoh: andi.rm (huruf kecil/angka)" required autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="email" value="Email (Gmail Aktif)" />
            <x-text-input id="email" class="block w-full" type="email" name="email" :value="old('email')"
                          placeholder="Contoh: andi.pratama@gmail.com" required autocomplete="email" />
            <p class="text-[11px] mt-1" style="color:var(--mut)">Digunakan untuk verifikasi PPIC &amp; pemulihan lupa password mandiri.</p>
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="requested_role" value="Pengajuan Divisi / Role" />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-1">
                <label class="flex items-start gap-2.5 p-2.5 rounded border cursor-pointer bg-white hover:bg-slate-50 border-slate-200">
                    <input type="radio" name="requested_role" value="STAFF_MATERIAL" class="mt-0.5"
                           {{ old('requested_role', 'STAFF_MATERIAL') === 'STAFF_MATERIAL' ? 'checked' : '' }} required>
                    <span class="leading-tight">
                        <b class="block text-xs text-slate-900">Staff Material</b>
                        <small class="text-[11px] text-slate-500">Inbound, Mat. Out, Mat. Return, RTS</small>
                    </span>
                </label>
                <label class="flex items-start gap-2.5 p-2.5 rounded border cursor-pointer bg-white hover:bg-slate-50 border-slate-200">
                    <input type="radio" name="requested_role" value="STAFF_FG" class="mt-0.5"
                           {{ old('requested_role') === 'STAFF_FG' ? 'checked' : '' }} required>
                    <span class="leading-tight">
                        <b class="block text-xs text-slate-900">Staff FG</b>
                        <small class="text-[11px] text-slate-500">Production, Form NG, Delivery</small>
                    </span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('requested_role')" class="mt-1" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="password" value="Password" />
            <div class="relative">
                <input id="password" class="block w-full pr-11" :type="show ? 'text' : 'password'" name="password" required autocomplete="new-password" />
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 w-10 grid place-items-center" style="color:var(--mut)" aria-label="Tampilkan atau sembunyikan password">
                    <x-icon x-show="!show" name="eye" />
                    <x-icon x-show="show" x-cloak name="eye-off" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="password_confirmation" value="Konfirmasi Password" />
            <div class="relative">
                <input id="password_confirmation" class="block w-full pr-11" :type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password" />
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 w-10 grid place-items-center" style="color:var(--mut)" aria-label="Tampilkan atau sembunyikan konfirmasi password">
                    <x-icon x-show="!show" name="eye" />
                    <x-icon x-show="show" x-cloak name="eye-off" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
        </div>

        <div class="alert alert-info text-[11.5px] leading-relaxed">
            <x-icon name="info" />
            <span>Password Anda terenkripsi privat (PPIC tidak dapat melihat password). PPIC hanya memverifikasi nama, username, email, dan penetapan role.</span>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-full">Daftar &amp; Ajukan ACC</button>

        <div class="pt-3 border-t border-slate-200 text-center text-[13px]" style="color:var(--mut)">
            Sudah punya akun?
            <a class="link-act font-semibold ml-1" href="{{ route('login') }}">Masuk di sini</a>
        </div>
    </form>
</x-guest-layout>