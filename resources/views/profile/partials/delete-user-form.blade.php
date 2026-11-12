<section class="space-y-4">
    <header>
        <h2 class="panel-title">Hapus Akun</h2>
        <p class="page-sub">Setelah akun dihapus, seluruh data yang terhubung dengan akun ini akan dihapus secara permanen.</p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Hapus Akun</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="panel-title">Apakah Anda yakin ingin menghapus akun ini?</h2>
            <p class="page-sub mt-1">Masukkan password Anda untuk mengonfirmasi penghapusan akun secara permanen.</p>

            <div class="mt-4">
                <x-input-label for="password" value="Password" class="sr-only" />
                <x-text-input id="password" name="password" type="password" class="block w-3/4" placeholder="Password" />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-1.5" />
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                <x-danger-button>Hapus Akun</x-danger-button>
            </div>
        </form>
    </x-modal>
</section>