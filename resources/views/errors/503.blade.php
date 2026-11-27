@extends('errors._layout')

@section('code', '503')
@section('tone', 'tone-warn')
@section('title', 'Sistem Sedang Pemeliharaan')
@section('message', 'DRC Stock Control sedang dalam proses pemeliharaan atau pencadangan data sementara.')
@section('tip')
    Mohon tunggu beberapa saat lalu tekan tombol <strong>Coba Muat Ulang</strong> di bawah. Operasional gudang dapat dilanjutkan segera setelah pemeliharaan selesai.
@endsection
@section('home_btn_class', '')
@section('actions')
    <button type="button" onclick="window.location.reload()" class="btn btn-primary">
        Coba Muat Ulang
    </button>
@endsection