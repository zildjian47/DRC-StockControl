<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ItemController;
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

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 1. Manufacture Process
    Route::resource('processes', ProcessController::class)->only(['index', 'store', 'update', 'destroy']);

    // 2. Master Companies
    Route::resource('companies', CompanyController::class)->only(['index', 'store', 'update', 'destroy']);

    // 3. Master Items & Archive (Soft Deletes)
    Route::get('/items/trashed', [ItemController::class, 'trashed'])->name('items.trashed');
    Route::post('/items/{id}/restore', [ItemController::class, 'restore'])->name('items.restore');
    Route::resource('items', ItemController::class)->only(['index', 'store', 'update', 'destroy']);

    // 4. Bill of Materials (BOM)
    Route::resource('boms', BomController::class)->only(['index', 'store', 'destroy']);

    // 5. Inventory Stocks
    Route::get('/inventory-stocks', [InventoryStockController::class, 'index'])->name('inventory_stocks.index');

    // 6. Lot Tracers (Meja Kontrol WIP)
    Route::get('/lot-tracers', [LotTracerController::class, 'index'])->name('lot_tracers.index');
    Route::post('/lot-tracers/{lotTracer}/finish', [LotTracerController::class, 'finish'])->name('lot_tracers.finish');
    Route::patch('/lot-tracers/{lotTracer}/set-finish', [LotTracerController::class, 'finish'])->name('lot_tracers.set_finish');

    // 7. Inbounds (QR Scanner & Verifikasi PPIC)
    Route::post('/inbounds/verify-batch', [InboundController::class, 'verifyBatch'])->name('inbounds.verify_batch');
    Route::resource('inbounds', InboundController::class)->only(['index', 'store']);

    // 8. Material Outs (QR Scanner & Verifikasi PPIC)
    Route::post('/material-outs/verify-batch', [MaterialOutController::class, 'verifyBatch'])->name('material_outs.verify_batch');
    Route::resource('material-outs', MaterialOutController::class)
        ->only(['index', 'store'])
        ->names([
            'index' => 'material_outs.index',
            'store' => 'material_outs.store',
        ]);

    // 9. Material Returns (QR Scanner & Verifikasi PPIC)
    Route::post('/material-returns/verify-batch', [MaterialReturnController::class, 'verifyBatch'])->name('material_returns.verify_batch');
    Route::resource('material-returns', MaterialReturnController::class)
        ->only(['index', 'store'])
        ->names([
            'index' => 'material_returns.index',
            'store' => 'material_returns.store',
        ]);

    // 10. Productions (QR Scanner & Verifikasi PPIC)
    Route::post('/productions/verify-batch', [ProductionController::class, 'verifyBatch'])->name('productions.verify_batch');
    Route::resource('productions', ProductionController::class)->only(['index', 'store']);

    // 11. Deliveries (QR Scanner & Verifikasi PPIC)
    Route::post('/deliveries/verify-batch', [DeliveryController::class, 'verifyBatch'])->name('deliveries.verify_batch');
    Route::resource('deliveries', DeliveryController::class)->only(['index', 'store']);

    // 12. Return to Suppliers (QR Scanner & Verifikasi PPIC)
    Route::post('/return-to-suppliers/verify-batch', [ReturnToSupplierController::class, 'verifyBatch'])->name('return_to_suppliers.verify_batch');
    Route::resource('return-to-suppliers', ReturnToSupplierController::class)
        ->only(['index', 'store'])
        ->names([
            'index' => 'return_to_suppliers.index',
            'store' => 'return_to_suppliers.store',
        ]);

    // 13. Stock Adjustments (2 Jalur: Desktop & Mobile Scanner)
    Route::resource('stock-adjustments', StockAdjustmentController::class)
        ->only(['index', 'store'])
        ->names([
            'index' => 'stock_adjustments.index',
            'store' => 'stock_adjustments.store',
        ]);

    // 14. Form Production NG (Desktop QC)
    Route::resource('production-ngs', ProductionNgController::class)
        ->only(['index', 'store'])
        ->names([
            'index' => 'production_ngs.index',
            'store' => 'production_ngs.store',
        ]);

    // 15. Problem Delays / Delay Delivery (Desktop)
    Route::resource('problem-delays', ProblemDelayController::class)
        ->only(['index', 'store'])
        ->names([
            'index' => 'problem_delays.index',
            'store' => 'problem_delays.store',
        ]);
});

require __DIR__.'/auth.php';