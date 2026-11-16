<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Profil Pengguna"
                       subtitle="Kelola informasi nama petugas, alamat email login, dan keamanan password akun Anda" />
    </x-slot>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-[18px] items-start">
        <section class="panel">
            <div class="panel-body">
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
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </section>
    </div>
</x-app-layout>