@extends('errors._layout')

@section('code', '500')
@section('tone', 'tone-crit')
@section('title', 'Terjadi Kesalahan Server')
@section('message', 'Sistem mengalami kendala tak terduga saat memproses permintaan Anda.')
@section('tip')
    Data stok tetap aman karena setiap transaksi dilindungi penguncian database. Silakan muat ulang halaman dan coba kembali, atau laporkan ke bagian <strong>PPIC</strong> jika kendala berulang.
@endsection
@section('home_btn_class', '')
@section('actions')
    <button type="button" onclick="window.location.reload()" class="btn btn-primary">
        Muat Ulang Halaman
    </button>
@endsection