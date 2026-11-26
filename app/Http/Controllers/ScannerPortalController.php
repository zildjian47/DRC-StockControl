<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScannerPortalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $infoModule = [
            [
                'label' => 'Cek Info Stok',
                'desc'  => 'Scan QR rak atau label part untuk melihat stok, WIP, & riwayat',
                'badge' => 'INFO ONLY',
                'pill'  => 'pill-info',
                'icon'  => 'info',
                'flip'  => false,
                'url'   => route('item_cards.scanner'),
            ],
        ];

        $materialModules = [
            [
                'label' => 'Inbound',
                'desc'  => 'Penerimaan barang masuk dari Supplier',
                'badge' => 'RM · KOMPONEN · TRANSIT',
                'pill'  => 'pill-good',
                'icon'  => 'inb',
                'flip'  => false,
                'url'   => route('inbounds.index', ['mode' => 'scanner']),
            ],
            [
                'label' => 'Material Out',
                'desc'  => 'Pengeluaran material ke produksi & buka Lot',
                'badge' => 'BUKA LOT',
                'pill'  => 'pill-info',
                'icon'  => 'out',
                'flip'  => false,
                'url'   => route('material_outs.index', ['mode' => 'scanner']),
            ],
            [
                'label' => 'Material Return',
                'desc'  => 'Pengembalian sisa material dari mesin ke gudang',
                'badge' => 'LOT ON PROGRESS',
                'pill'  => 'pill-warn',
                'icon'  => 'undo',
                'flip'  => false,
                'url'   => route('material_returns.index', ['mode' => 'scanner']),
            ],
            [
                'label' => 'Return to Supplier',
                'desc'  => 'Pengembalian barang bermasalah ke Supplier',
                'badge' => 'RETUR SUPPLIER',
                'pill'  => 'pill-crit',
                'icon'  => 'truck',
                'flip'  => true,
                'url'   => route('return_to_suppliers.index', ['mode' => 'scanner']),
            ],
        ];

        $fgModules = [
            [
                'label' => 'Production',
                'desc'  => 'Pencatatan hasil produksi per Nomor Lot aktif',
                'badge' => 'OUTPUT LOT',
                'pill'  => 'pill-good',
                'icon'  => 'factory',
                'flip'  => false,
                'url'   => route('productions.index', ['mode' => 'scanner']),
            ],
            [
                'label' => 'Form NG',
                'desc'  => 'Pencatatan temuan barang cacat / reject QC',
                'badge' => 'REJECT QC',
                'pill'  => 'pill-crit',
                'icon'  => 'alert',
                'flip'  => false,
                'url'   => route('production_ngs.index', ['mode' => 'scanner']),
            ],
            [
                'label' => 'Delivery',
                'desc'  => 'Pengiriman barang jadi ke Customer',
                'badge' => 'FG · FGK · TRANSIT',
                'pill'  => 'pill-info',
                'icon'  => 'truck',
                'flip'  => false,
                'url'   => route('deliveries.index', ['mode' => 'scanner']),
            ],
        ];

        $ppicExtraModules = [
            [
                'label' => 'Stock Adjustment',
                'desc'  => 'Penyesuaian stok opname, loss, surplus, & repair',
                'badge' => 'KHUSUS PPIC',
                'pill'  => 'pill-neu',
                'icon'  => 'sliders',
                'flip'  => false,
                'url'   => route('stock_adjustments.index', ['mode' => 'scanner']),
            ],
        ];

        $modules = match ($user->role) {
            User::ROLE_STAFF_MATERIAL => array_merge($infoModule, $materialModules),
            User::ROLE_STAFF_FG       => array_merge($infoModule, $fgModules),
            User::ROLE_PPIC           => array_merge($infoModule, $materialModules, $fgModules, $ppicExtraModules),
            default                   => [],
        };

        return view('scanner.portal', compact('user', 'modules'));
    }
}