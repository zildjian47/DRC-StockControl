@foreach (config('plantline_menu') as $group)
    <div class="sb-group">
        <div class="sb-title">{{ $group['group'] }}</div>
        @foreach ($group['items'] as $item)
            <a href="{{ route($item['route']) }}"
               class="sb-item"
               @if(request()->routeIs($item['match'])) aria-current="page" @endif
               title="{{ $item['label'] }}">
                <x-icon :name="$item['icon']" />
                <span class="sb-label">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
@endforeach