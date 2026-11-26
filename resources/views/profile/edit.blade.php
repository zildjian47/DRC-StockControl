@php
    $isPpic = Auth::user()->isPpic();
@endphp

@if ($isPpic)
    <x-app-layout>
        <x-slot name="header">
            <x-page-header title="Profil Pengguna"
                           subtitle="Kelola informasi nama petugas, alamat email login, dan keamanan password akun Anda" />
        </x-slot>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-[18px] items-start">
            <section class="panel">
                <div class="panel-body space-y-4">
                    <div class="p-3 rounded bg-slate-50 border border-slate-200 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div>
                            <span class="text-slate-500">Username:</span>
                            <b class="num text-slate-900 ml-1">{{ $user->username ?: '-' }}</b>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="pill pill-info">{{ $user->role_label }}</span>
                            <span class="pill pill-good">{{ $user->status ?? 'ACTIVE' }}</span>
                        </div>
                    </div>

                    <div class="max-w-xl">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-body">
                    <div class="max-w-xl">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>
            </section>

            <section class="panel xl:col-span-2">
                <div class="panel-body">
                    <div class="alert alert-info text-xs leading-relaxed">
                        <x-icon name="info" />
                        <div>Penghapusan akun mandiri dinonaktifkan demi menjaga keutuhan jejak audit petugas pada riwayat transaksi. Untuk menonaktifkan akun karyawan, gunakan menu <b>Manajemen User</b>.</div>
                    </div>
                </div>
            </section>
        </div>
    </x-app-layout>
@else
    <x-scanner-layout :backUrl="route('scanner.portal')">
        <x-slot name="header">Profil &amp; Password</x-slot>

        <section class="panel">
            <div class="panel-head">
                <div class="panel-title">Informasi Akun</div>
                <span class="pill {{ $user->isStaffMaterial() ? 'pill-warn' : 'pill-good' }}">{{ strtoupper($user->role_label) }}</span>
            </div>
            <div class="panel-body space-y-4">
                <div class="p-3 rounded bg-slate-50 border border-slate-200 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Username Login</span>
                    <b class="num text-slate-900">{{ $user->username ?: '-' }}</b>
                </div>

                @include('profile.partials.update-profile-information-form')
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div class="panel-title">Keamanan Password</div>
            </div>
            <div class="panel-body">
                @include('profile.partials.update-password-form')
            </div>
        </section>

        <div class="alert alert-info text-xs leading-relaxed">
            <x-icon name="info" />
            <div>Jika perlu mengubah role divisi atau menonaktifkan akun, silakan hubungi admin PPIC.</div>
        </div>
    </x-scanner-layout>
@endif