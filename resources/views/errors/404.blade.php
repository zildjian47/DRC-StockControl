@extends('errors._layout')

@section('code', '404')
@section('tone', 'tone-info')
@section('title', 'Halaman Tidak Ditemukan')
@section('message', 'Alamat yang Anda buka tidak tersedia, sudah dipindahkan, atau data item terkait telah dinonaktifkan.')
@section('tip')
    Jika Anda baru saja memindai stiker QR rak, pastikan item masih berstatus aktif di Master Item atau gunakan menu <strong>Cek Info Stok</strong> di Portal Scanner.
@endsection