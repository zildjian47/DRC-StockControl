# Aturan UI DRC Stock Control (Navy Console)

Trigger: "ikuti Navy Console", atau setiap pembuatan/pengubahan view Blade.

## Struktur halaman
- Halaman desktop: `<x-app-layout>` + `<x-slot name="header"><x-page-header ...>`. Jangan membungkus isi dengan `py-6`/`max-w-7xl`.
- Halaman HP scanner: `<x-scanner-layout>`. Auth: `<x-guest-layout>`.
- Menu hanya diubah di `config/plantline_menu.php`.
- Pesan sukses/error: `<x-flash />`.

## Komponen (class di resources/css/app.css, jangan ditulis ulang dengan utility)
panel, panel-head, panel-title, panel-body, panel-foot, btn, btn-primary, btn-danger, btn-crit, btn-sm, btn-lg, btn-group,
link-act, act-btn, lbl, hint, seg, tbl-wrap, tbl, num, empty, pill (+ pill-good/warn/crit/info/neu/empty),
ty (+ ty-RM/FG/KOMPONEN/TRANSIT/FGK), dlg, dlg-split, dlg-split-wide, dlg-side, dlg-main, dlg-k, dlg-list,
opt-chips, sbar, rel, flow, fbar, tl-*, alert, kpis/kpi, track, sumc, cons, plist, mono-tile,
flow3, flow3-links, fcol, fh3, fmid, nodec, pnode, rr, trg, hasil, inp-calc, inp-calc-ic, bom-grp-box.

## Aturan
- Dua aksi bersebelahan di header halaman dibungkus `<span class="btn-group">`: aksi biasa di kiri, satu `btn-primary` di kanan.
- Tombol portal scanner menggunakan teks `Scan` dengan ikon `scan`, dan tombol tambah form menggunakan awalan `Input ...` (`Input Item`, `Input BOM`, `Input Perusahaan`, `Input Proses`, `Input Adjustment`, `Input NG`, `Input Delay`).
- Form tambah/edit/input utama berbentuk modal dua panel: `<div class="panel dlg w-full max-w-3xl">` berisi `dlg-split` dengan `dlg-side` (abu, pratinjau hidup) dan `dlg-main` (field), bebas scrollbar kanan-kiri (`overflow-x: hidden`).
- Modal "Input BOM" memakai **Alur node** (`flow3`): Material Input (kiri, `nodec inp` abu) → Proses (tengah, `pnode`) → Target Output (kanan, `nodec out`), dihubungkan garis SVG yang digambar ulang lewat `drawLinks()`.
- Hapus/nonaktifkan Master Item memakai dialog dua panel abu: item punya riwayat → tombol `btn-primary` "Nonaktifkan"; belum punya riwayat → `btn-crit` "Hapus Permanen". Kiri `sumc` + `dlg-list`, kanan pesan + `cons` (kotak hijau `k` untuk akibat aman, merah `x` untuk akibat berat).
- Form Proses dan Perusahaan (Input dan Edit) memakai dua panel abu. Proses: kiri `plist` (proses terdaftar + chip `new` biru untuk nama yang sedang diketik). Perusahaan: kiri pratinjau (`mono-tile`, `dlg-code`, `dlg-name`, pill tipe), kanan field dengan tipe berupa `opt-chips`.
- Pill tipe perusahaan: SUPPLIER = `pill-info`, INTERNAL = `pill-warn`, CUSTOMER = `pill-good`.
- Kolom input yang mendukung ekspresi kalkulator (`+`, `-`, `*`, `/`) dibungkus `.inp-calc` dengan ikon `<span class="inp-calc-ic"><x-icon name="calc" /></span>` di dalamnya.
- Indikator `.count-chip`, `.pill`, dan `.ty` selalu tampil dalam huruf kapital (`uppercase`).
- Font utama dan angka menggunakan `Inter` (`tabular-nums`).
- Jangan mengubah nama input, nama event, controller, route, atau logika Alpine saat merapikan tampilan.