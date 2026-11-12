@props(['messages'])
@if ($messages)
    <ul {{ $attributes->merge(['class' => 'space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li class="text-xs font-medium" style="color:var(--crit)">{{ $message }}</li>
        @endforeach
    </ul>
@endif