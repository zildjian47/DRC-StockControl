# Aturan UI DRC Stock Control (Navy Console)

Trigger: "ikuti Navy Console", atau setiap pembuatan/pengubahan view Blade.

## Struktur halaman
- Halaman desktop: `<x-app-layout>` + `<x-slot name="header"><x-page-header ...>`. Jangan membungkus isi dengan `py-6`/`max-w-7xl`.
- Halaman HP scanner: `<x-scanner-layout>`. Auth: `<x-guest-layout>`.
- Menu hanya diubah di `config/plantline_menu.php`.
- Pesan sukses/error: `<x-flash />`.

## Komponen (class di resources/css/app.css, jangan ditulis ulang dengan utility)
panel, panel-head, panel-title, panel-body, panel-foot, btn, btn-primary, btn-danger, btn-sm, btn-lg,
link-act, lbl, hint, seg, tbl-wrap, tbl, num, empty, pill (+ pill-good/warn/crit/info/neu),
alert, kpis/kpi, track.

## Aturan
- Satu tombol `btn-primary` per area aksi. Tombol Edit/Hapus di tabel memakai `link-act`.
- Status SELALU `pill` + teks, tanpa glyph. good=selesai/sesuai/berjalan, warn=pending/menunggu, crit=NG/hold/tidak sesuai/delay, info=dikoreksi/informasi, neu=netral.
- Angka, qty, lot number, ID, tanggal-jam: class `num` (IBM Plex Mono). Angka di tabel rata kanan.
- Label form `lbl`, input tanpa class warna (gaya dari base). Tinggi sentuh di HP minimal 44px.
- Radius 4px, tanpa shadow, tanpa gradasi, tanpa emoji. Warna hanya dari token `:root` dan palet Tailwind yang sudah dipetakan.
- Jangan memakai: indigo, purple, gray-*, rounded-xl, rounded-full pada tombol/kartu, shadow-md ke atas, nilai hex langsung (`bg-[#...]`).
- Jangan mengubah controller, route, name input, atau logika Alpine saat merapikan tampilan.