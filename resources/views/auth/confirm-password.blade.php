<x-guest-layout>
    <div class="mb-4">
        <h2 class="panel-title">Konfirmasi Password</h2>
        <p class="page-sub">Ini adalah area aman aplikasi. Harap konfirmasi password Anda sebelum melanjutkan.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf
        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" class="block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div class="flex justify-end pt-2">
            <x-primary-button>Konfirmasi</x-primary-button>
        </div>
    </form>
</x-guest-layout>