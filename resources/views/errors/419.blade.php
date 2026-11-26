@extends('errors._layout')

@section('code', '419')
@section('tone', 'tone-warn')
@section('title', 'Sesi Halaman Kedaluwarsa')
@section('message', 'Halaman ini sudah terlalu lama dibiarkan terbuka sehingga token keamanan otomatis diperbarui.')
@section('tip')
    Data stok aman dan tidak berubah. Tekan tombol <strong>Muat Ulang Halaman</strong> di bawah lalu ulangi input atau scan Anda.
@endsection
@section('home_btn_class', '')
@section('actions')
    <button type="button" onclick="window.location.reload()" class="btn btn-primary">
        Muat Ulang Halaman
    </button>
@endsection