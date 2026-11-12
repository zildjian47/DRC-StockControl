@if (session('success'))
    <div class="alert alert-good" role="status">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-crit" role="alert">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-crit" role="alert">
        <div>@foreach ($errors->all() as $err)<div>{{ $err }}</div>@endforeach</div>
    </div>
@endif