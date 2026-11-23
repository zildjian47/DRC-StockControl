@if (session('success'))
    <div x-data="{ show: true }" x-show="show" class="alert alert-good" role="status">
        <x-icon name="check" />
        <div class="flex-1 min-w-0">{{ session('success') }}</div>
        <button type="button" @click="show = false"
                class="ml-auto -mr-1 -mt-0.5 p-1 rounded opacity-70 hover:opacity-100 flex-none"
                aria-label="Tutup">
            <x-icon name="x" class="w-3.5 h-3.5" />
        </button>
    </div>
@endif

@if (session('error'))
    <div x-data="{ show: true }" x-show="show" class="alert alert-crit" role="alert">
        <x-icon name="alert" />
        <div class="flex-1 min-w-0">{{ session('error') }}</div>
        <button type="button" @click="show = false"
                class="ml-auto -mr-1 -mt-0.5 p-1 rounded opacity-70 hover:opacity-100 flex-none"
                aria-label="Tutup">
            <x-icon name="x" class="w-3.5 h-3.5" />
        </button>
    </div>
@endif

@if ($errors->any())
    <div x-data="{ show: true }" x-show="show" class="alert alert-crit" role="alert">
        <x-icon name="alert" />
        <div class="flex-1 min-w-0">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
        <button type="button" @click="show = false"
                class="ml-auto -mr-1 -mt-0.5 p-1 rounded opacity-70 hover:opacity-100 flex-none"
                aria-label="Tutup">
            <x-icon name="x" class="w-3.5 h-3.5" />
        </button>
    </div>
@endif