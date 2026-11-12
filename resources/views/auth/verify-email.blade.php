<x-guest-layout>
    <div class="mb-4">
        <h2 class="panel-title">Verifikasi Email</h2>
        <p class="page-sub">Silakan verifikasi alamat email Anda dengan menekan tautan yang baru saja kami kirimkan.</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-good mb-4">
            Tautan verifikasi baru telah dikirim ke alamat email Anda.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>Kirim Ulang Email Verifikasi</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link-act">Keluar</button>
        </form>
    </div>
</x-guest-layout>