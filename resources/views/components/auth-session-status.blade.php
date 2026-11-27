@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'alert alert-good']) }} role="status">
        <x-icon name="check" />
        <div class="flex-1 min-w-0">{{ $status }}</div>
    </div>
@endif