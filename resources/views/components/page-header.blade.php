@props(['title', 'subtitle' => null])
<div class="flex flex-wrap items-end justify-between gap-4 w-full">
    <div class="min-w-0">
        <h2 class="page-title">{{ $title }}</h2>
        @if ($subtitle)<p class="page-sub">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)<div class="page-actions">{{ $actions }}</div>@endisset
</div>