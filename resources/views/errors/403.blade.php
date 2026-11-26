@extends('errors._layout')

@section('code', '403')
@section('tone', 'tone-crit')
@section('title', 'Akses Ditolak')
@section('message', 'Akun Anda tidak memiliki izin untuk membuka halaman atau menjalankan aksi ini.')
@section('tip')
    Menu Master Data, Dashboard, Koreksi PPIC, Cetak QR, dan Export/Import hanya dapat diakses oleh akun ber-role <strong>PPIC</strong>. Operator gudang dapat menjalankan seluruh transaksi harian melalui <strong>Portal Scanner</strong>.
@endsection