<section>
    <header>
        <h2 class="panel-title">Informasi Profil</h2>
        <p class="page-sub">Perbarui nama dan alamat email akun Anda.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-5 space-y-4">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="lbl">Username</label>
                <input type="text" value="{{ $user->username ?? '-' }}" disabled class="w-full num">
            </div>
            <div>
                <label class="lbl">Role</label>
                <input type="text" value="{{ $user->role ?? 'Staff / PPIC' }}" disabled class="w-full">
            </div>
        </div>

        <div>
            <x-input-label for="name" value="Nama" />
            <x-text-input id="name" name="name" type="text" class="block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-1.5" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" name="email" type="email" class="block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-1.5" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2">
                    <p class="text-xs text-slate-600">
                        Alamat email Anda belum diverifikasi.
                        <button form="send-verification" class="link-act">Klik di sini untuk mengirim ulang email verifikasi.</button>
                    </p>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-3 pt-2">
            <x-primary-button>Simpan Perubahan</x-primary-button>

            @if (session('status') === 'profile-updated')
                <span x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="pill pill-good">
                    Tersimpan
                </span>
            @endif
        </div>
    </form>
</section>