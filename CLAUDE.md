# Aturan UI DRC Stock Control (Navy Console)

Trigger: "ikuti Navy Console", atau setiap pembuatan/pengubahan view Blade.

## Struktur halaman
- Halaman desktop: `<x-app-layout>` + `<x-slot name="header"><x-page-header ...>`. Jangan membungkus isi dengan `py-6`/`max-w-7xl`.
- Halaman HP scanner: `<x-scanner-layout>`. Auth: `<x-guest-layout>`.
- Menu hanya diubah di `config/plantline_menu.php`.
- Pesan sukses/error: `<x-flash />`.

## Komponen (class di resources/css/app.css, jangan ditulis ulang dengan utility)
panel, panel-head, panel-title, panel-body, panel-foot, btn, btn-primary, btn-danger, btn-sm, btn-lg, btn-group,
link-act, act-btn, lbl, hint, seg, tbl-wrap, tbl, num, empty, pill (+ pill-good/warn/crit/info/neu/empty),
ty (+ ty-RM/FG/KOMPONEN/TRANSIT/FGK), dlg, dlg-split, dlg-split-wide, dlg-side, dlg-main, dlg-k, dlg-list,
opt-chips, sbar, rel, flow, fbar, tl-*, alert, kpis/kpi, track.

## Aturan
- Dua aksi bersebelahan di header halaman dibungkus `<span class="btn-group">`: aksi biasa di kiri, satu `btn-primary` di kanan.
- Form tambah/edit/input utama berbentuk modal dua panel: `<div class="panel dlg w-full max-w-3xl">` berisi `dlg-split` dengan `dlg-side` (abu, pratinjau hidup) dan `dlg-main` (field), bebas scrollbar kanan-kiri (`overflow-x: hidden`).
- Pilihan tipe/kategori dengan sedikit opsi memakai `opt-chips` (radio).
- Tipe item memakai `ty` berwarna (`ty-RM`, `ty-FG`, `ty-KOMPONEN`, `ty-TRANSIT`, `ty-FGK`), tanpa simbol. `pill` digunakan untuk status dengan warna indikator sistem yang sudah ditetapkan.
- Riwayat mutasi stok pada Informasi Material memakai linimasa (`tl-*`), dikelompokkan per tanggal.
- Font utama dan angka menggunakan `Inter` (`tabular-nums`).
- Jangan mengubah nama input, nama event, controller, route, atau logika Alpine saat merapikan tampilan.