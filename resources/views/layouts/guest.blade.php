@php $split = $attributes->get('variant') === 'split'; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DRC Stock Control · PT. Dian Raya Cipta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@if ($split)
    <div class="min-h-screen grid lg:grid-cols-[1.05fr_1fr]">
        <section class="login-brand hidden lg:flex flex-col justify-between p-12">
            <div class="flex items-center gap-2.5">
                <span class="sb-mark">D</span>
                <span class="sb-name">DRC Stock Control</span>
            </div>
            <div>
                <h1 class="text-[34px] font-semibold leading-[1.15] tracking-tight mb-3">Manufacturing<br>Execution &amp; Inventory</h1>
                <p class="max-w-[42ch] text-[15px]" style="color:var(--side-ink)">Satu sistem untuk inbound, stok, produksi, lot, dan pengiriman.</p>
            </div>
            <p class="text-xs font-medium" style="color:var(--side-mut)">PT. Dian Raya Cipta</p>
        </section>
        <section class="flex items-center justify-center p-8" style="background:var(--surface)">
            <div class="w-full max-w-[360px]">
                <div class="lg:hidden flex items-center gap-2.5 mb-8">
                    <span class="sb-mark">D</span>
                    <span class="sb-name" style="color:var(--ink)">DRC Stock Control</span>
                </div>
                {{ $slot }}
            </div>
        </section>
    </div>
@else
    <div class="min-h-screen flex flex-col items-center justify-center p-6">
        <div class="flex items-center gap-2.5 mb-6">
            <span class="sb-mark">D</span>
            <span class="sb-name" style="color:var(--ink)">DRC Stock Control</span>
        </div>
        <div class="panel panel-body w-full max-w-md">{{ $slot }}</div>
    </div>
@endif
</body>
</html>