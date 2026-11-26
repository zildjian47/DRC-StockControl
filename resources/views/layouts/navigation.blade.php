@php
    $navUser = Auth::user();
    $navRole = $navUser?->role ?? '';
    $pendingUserBadge = ($navUser && $navUser->isPpic())
        ? \App\Models\User::where('status', \App\Models\User::STATUS_PENDING)->count()
        : 0;
@endphp

@foreach (config('plantline_menu') as $group)
    @php
        $visibleItems = collect($group['items'])->filter(function ($item) use ($navRole) {
            if (empty($item['roles'])) {
                return true;
            }
            return in_array($navRole, $item['roles'], true);
        })->values();
    @endphp

    @if ($visibleItems->isNotEmpty())
        <div class="sb-group">
            <div class="sb-title">{{ $group['group'] }}</div>
            @foreach ($visibleItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="sb-item"
                   @if(request()->routeIs($item['match'])) aria-current="page" @endif
                   title="{{ $item['label'] }}">
                    <x-icon :name="$item['icon']" :flip="!empty($item['flip'])" />
                    <span class="sb-label flex-1 truncate">{{ $item['label'] }}</span>
                    @if ($item['route'] === 'users.index' && $pendingUserBadge > 0)
                        <span class="sb-label px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-400 text-slate-950 num"
                              title="{{ $pendingUserBadge }} akun menunggu ACC">
                            {{ $pendingUserBadge }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
@endforeach