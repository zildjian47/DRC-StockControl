<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemCardController;
use App\Http\Controllers\ItemPoImportController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\InventoryStockController;
use App\Http\Controllers\LotTracerController;
use App\Http\Controllers\InboundController;
use App\Http\Controllers\MaterialOutController;
use App\Http\Controllers\MaterialReturnController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProductionNgController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ReturnToSupplierController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\ProblemDelayController;
use App\Http\Controllers\ScannerPortalController;
use App\Http\Controllers\UserManagementController;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route(Auth::user()->homeRouteName());
    }

    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'active', 'role:PPIC'])
    ->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    // Portal Scanner (Halaman Utama STAFF_MATERIAL & STAFF_FG, juga dapat diakses PPIC)
    Route::get('/scanner-portal', [ScannerPortalController::class, 'index'])->name('scanner.portal');

    // Kartu Info Item Mobile (Read-Only untuk semua role aktif + Scanner Cek Info Stok)
    Route::get('/item-card/scan', [ItemCardController::class, 'scanner'])->name('item_cards.scanner');
    Route::get('/item-card/lookup', [ItemCardController::class, 'lookup'])->name('item_cards.lookup');
    Route::get('/item-card/{item}', [ItemCardController::class, 'show'])
        ->whereNumber('item')
        ->name('item_cards.show');

    // Profile (Semua role aktif — hapus akun mandiri dinonaktifkan di controller)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // =========================================================================
    // GRUP 1: KHUSUS PPIC (Master Data, Kontrol Stok, Koreksi, Opname, User, Export, Import PO)
    // =========================================================================
    Route::middleware('role:PPIC')->group(function () {
        // 1. Manufacture Process
        Route::resource('processes', ProcessController::class)->only(['index', 'store', 'update', 'destroy']);

        // 2. Master Companies
        Route::resource('companies', CompanyController::class)->only(['index', 'store', 'update', 'destroy']);

        // 3. Master Items & Archive (Soft Deletes) + AJAX Info Modal + Cetak Label QR + Import Qty PO
        Route::get('/items/trashed', [ItemController::class, 'trashed'])->name('items.trashed');
        Route::get('/items/qr-print', [ItemController::class, 'qrPrint'])->name('items.qr_print');

        Route::get('/items/po-import', [ItemPoImportController::class, 'index'])->name('items.po_import.index');
        Route::get('/items/po-import/template', [ItemPoImportController::class, 'template'])->name('items.po_import.template');
        Route::post('/items/po-import/preview', [ItemPoImportController::class, 'preview'])->name('items.po_import.preview');
        Route::post('/items/po-import/commit', [ItemPoImportController::class, 'commit'])->name('items.po_import.commit');
        Route::post('/items/po-import/cancel', [ItemPoImportController::class, 'cancel'])->name('items.po_import.cancel');

        Route::post('/items/{id}/restore', [ItemController::class, 'restore'])->name('items.restore');
        Route::get('/items/{item}/info', [ItemController::class, 'info'])->name('items.info');
        Route::resource('items', ItemController::class)->only(['index', 'store', 'update', 'destroy']);

        // 4. Bill of Materials (BOM)
        Route::resource('boms', BomController::class)->only(['index', 'store', 'destroy']);

        // 5. Inventory Stocks
        Route::get('/inventory-stocks', [InventoryStockController::class, 'index'])->name('inventory_stocks.index');

        // 6. Lot Tracers (Meja Kontrol WIP)
        Route::get('/lot-tracers', [LotTracerController::class, 'index'])->name('lot_tracers.index');
        Route::patch('/lot-tracers/{lotTracer}/set-finish', [LotTracerController::class, 'finish'])->name('lot_tracers.set_finish');

        // 7. Seluruh Aksi Koreksi PPIC (verify-batch)
        Route::post('/inbounds/verify-batch', [InboundController::class, 'verifyBatch'])->name('inbounds.verify_batch');
        Route::post('/material-outs/verify-batch', [MaterialOutController::class, 'verifyBatch'])->name('material_outs.verify_batch');
        Route::post('/material-returns/verify-batch', [MaterialReturnController::class, 'verifyBatch'])->name('material_returns.verify_batch');
        Route::post('/productions/verify-batch', [ProductionController::class, 'verifyBatch'])->name('productions.verify_batch');
        Route::post('/deliveries/verify-batch', [DeliveryController::class, 'verifyBatch'])->name('deliveries.verify_batch');
        Route::post('/return-to-suppliers/verify-batch', [ReturnToSupplierController::class, 'verifyBatch'])->name('return_to_suppliers.verify_batch');
        Route::post('/production-ngs/verify-batch', [ProductionNgController::class, 'verifyBatch'])->name('production_ngs.verify_batch');

        // 8. Stock Adjustments (Desktop & Mobile Scanner khusus PPIC)
        Route::get('/stock-adjustments/lookup', [StockAdjustmentController::class, 'lookup'])->name('stock_adjustments.lookup');
        Route::resource('stock-adjustments', StockAdjustmentController::class)
            ->only(['index', 'store'])
            ->names([
                'index' => 'stock_adjustments.index',
                'store' => 'stock_adjustments.store',
            ]);

        // 9. Problem Delays / Delay Delivery (Desktop khusus PPIC)
        Route::resource('problem-delays', ProblemDelayController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names([
                'index'   => 'problem_delays.index',
                'store'   => 'problem_delays.store',
                'update'  => 'problem_delays.update',
                'destroy' => 'problem_delays.destroy',
            ]);

        // 10. Manajemen User (ACC Pendaftar, Edit Role, Nonaktifkan, Kirim Link Reset)
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}/approve', [UserManagementController::class, 'approve'])->name('users.approve');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/send-reset-link', [UserManagementController::class, 'sendResetLink'])->name('users.send_reset_link');

        // 11. Export Laporan Excel (.xlsx) & PDF (Terfilter Penuh, Khusus PPIC)
        Route::prefix('exports')->name('exports.')->group(function () {
            Route::get('/stock', [ExportController::class, 'stock'])->name('stock');
            Route::get('/lot-tracers', [ExportController::class, 'lotTracers'])->name('lot_tracers');
            Route::get('/inbounds', [ExportController::class, 'inbounds'])->name('inbounds');
            Route::get('/material-outs', [ExportController::class, 'materialOuts'])->name('material_outs');
            Route::get('/material-returns', [ExportController::class, 'materialReturns'])->name('material_returns');
            Route::get('/productions', [ExportController::class, 'productions'])->name('productions');
            Route::get('/deliveries', [ExportController::class, 'deliveries'])->name('deliveries');
            Route::get('/return-to-suppliers', [ExportController::class, 'returnToSuppliers'])->name('return_to_suppliers');
            Route::get('/stock-adjustments', [ExportController::class, 'stockAdjustments'])->name('stock_adjustments');
            Route::get('/production-ngs', [ExportController::class, 'productionNgs'])->name('production_ngs');
            Route::get('/problem-delays', [ExportController::class, 'problemDelays'])->name('problem_delays');
            Route::get('/items', [ExportController::class, 'items'])->name('items');
            Route::get('/boms', [ExportController::class, 'boms'])->name('boms');
            Route::get('/items/{item}/history', [ExportController::class, 'itemHistory'])
                ->whereNumber('item')
                ->name('item_history');
        });
    });

    // =========================================================================
    // GRUP 2: PPIC & STAFF_MATERIAL (Inbound, Material Out, Material Return, RTS)
    // =========================================================================
    Route::middleware('role:PPIC,STAFF_MATERIAL')->group(function () {
        // Inbounds
        Route::get('/inbounds/lookup', [InboundController::class, 'lookup'])->name('inbounds.lookup');
        Route::get('/inbounds', [InboundController::class, 'index'])->middleware('scanner.staff')->name('inbounds.index');
        Route::post('/inbounds', [InboundController::class, 'store'])->name('inbounds.store');

        // Material Outs
        Route::get('/material-outs/lookup', [MaterialOutController::class, 'lookup'])->name('material_outs.lookup');
        Route::get('/material-outs', [MaterialOutController::class, 'index'])->middleware('scanner.staff')->name('material_outs.index');
        Route::post('/material-outs', [MaterialOutController::class, 'store'])->name('material_outs.store');

        // Material Returns
        Route::get('/material-returns/lookup', [MaterialReturnController::class, 'lookup'])->name('material_returns.lookup');
        Route::get('/material-returns', [MaterialReturnController::class, 'index'])->middleware('scanner.staff')->name('material_returns.index');
        Route::post('/material-returns', [MaterialReturnController::class, 'store'])->name('material_returns.store');

        // Return to Suppliers
        Route::get('/return-to-suppliers/lookup', [ReturnToSupplierController::class, 'lookup'])->name('return_to_suppliers.lookup');
        Route::get('/return-to-suppliers', [ReturnToSupplierController::class, 'index'])->middleware('scanner.staff')->name('return_to_suppliers.index');
        Route::post('/return-to-suppliers', [ReturnToSupplierController::class, 'store'])->name('return_to_suppliers.store');
    });

    // =========================================================================
    // GRUP 3: PPIC & STAFF_FG (Production, Form NG, Delivery)
    // =========================================================================
    Route::middleware('role:PPIC,STAFF_FG')->group(function () {
        // Productions
        Route::get('/productions/lookup', [ProductionController::class, 'lookup'])->name('productions.lookup');
        Route::get('/productions', [ProductionController::class, 'index'])->middleware('scanner.staff')->name('productions.index');
        Route::post('/productions', [ProductionController::class, 'store'])->name('productions.store');

        // Form Production NG
        Route::get('/production-ngs/lookup', [ProductionNgController::class, 'lookup'])->name('production_ngs.lookup');
        Route::get('/production-ngs', [ProductionNgController::class, 'index'])->middleware('scanner.staff')->name('production_ngs.index');
        Route::post('/production-ngs', [ProductionNgController::class, 'store'])->name('production_ngs.store');

        // Deliveries
        Route::get('/deliveries/lookup', [DeliveryController::class, 'lookup'])->name('deliveries.lookup');
        Route::get('/deliveries', [DeliveryController::class, 'index'])->middleware('scanner.staff')->name('deliveries.index');
        Route::post('/deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
    });
});

require __DIR__.'/auth.php';