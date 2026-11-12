<x-guest-layout>
    <div class="mb-4">
        <h2 class="panel-title">Lupa Password</h2>
        <p class="page-sub">Masukkan alamat email akun Anda untuk menerima tautan reset password.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('login') }}" class="link-act">Kembali ke Login</a>
            <x-primary-button>Kirim Link Reset</x-primary-button>
        </div>
    </form>
</x-guest-layout>