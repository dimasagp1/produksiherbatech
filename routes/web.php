<?php

use App\Http\Controllers\Admin\BrandingSettingController;
use App\Http\Controllers\Admin\HistoryController;
use App\Http\Controllers\Admin\HrisSettingController;
use App\Http\Controllers\Admin\OdooSyncController;
use App\Http\Controllers\AlasanDowntimeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaporanHarianController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\MesinController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RejectController;
use App\Http\Controllers\SCM\BomController;
use App\Http\Controllers\SCM\DeliveryPlanController;
use App\Http\Controllers\SCM\FleetController;
use App\Http\Controllers\SCM\InventoryController;
use App\Http\Controllers\SCM\MaterialScrapController;
use App\Http\Controllers\SCM\MaterialUsageController;
use App\Http\Controllers\SCM\OdooMoSyncController;
use App\Http\Controllers\SCM\StockOpnameController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeeklyPlanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Profile routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin routes - Master Data (Produk: superadmin|admin|ppic|manager(read), lainnya: superadmin|admin|manager(read))
Route::middleware(['auth', 'role:superadmin|admin|ppic|manager'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('produk', ProdukController::class);
    Route::get('settings/branding', [BrandingSettingController::class, 'index'])->name('settings.branding.index');
    Route::post('settings/branding', [BrandingSettingController::class, 'update'])->name('settings.branding.update');
    Route::post('settings/branding/reset', [BrandingSettingController::class, 'reset'])->name('settings.branding.reset');
    Route::get('settings/odoo', [OdooSyncController::class, 'settings'])->name('odoo.settings');
    Route::post('settings/odoo', [OdooSyncController::class, 'updateSettings'])->name('odoo.settings.update');
    Route::get('odoo/preview-products', [OdooSyncController::class, 'previewProducts'])->name('odoo.preview-products');
    Route::post('odoo/sync-products', [OdooSyncController::class, 'syncProducts'])->name('odoo.sync-products');
    Route::post('odoo/reset-products', [OdooSyncController::class, 'resetProducts'])->name('odoo.reset-products');
    Route::post('odoo/test-connection', [OdooSyncController::class, 'testConnection'])->name('odoo.test-connection');
    Route::get('odoo/test-connection', [OdooSyncController::class, 'testConnection']);

    // HRIS Sasaran Mutu Integration Settings
    Route::get('settings/hris', [HrisSettingController::class, 'index'])->name('settings.hris.index');
    Route::post('settings/hris', [HrisSettingController::class, 'update'])->name('settings.hris.update');
    Route::post('settings/hris/test', [HrisSettingController::class, 'testConnection'])->name('settings.hris.test');
    Route::post('settings/hris/sync', [HrisSettingController::class, 'syncNow'])->name('settings.hris.sync');
});

Route::middleware(['auth', 'role:superadmin|admin|ppic|manager|leader|spv'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('odoo/scraps', [OdooSyncController::class, 'getScraps'])->name('odoo.scraps');
});
Route::middleware(['auth', 'role:superadmin|admin|manager'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('mesin', MesinController::class);
    Route::resource('line', LineController::class);
    Route::resource('alasan-downtime', AlasanDowntimeController::class);
    Route::resource('users', UserController::class);
    Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
});

// PPIC routes - Weekly Plan / WPS
Route::middleware(['auth', 'role:ppic|superadmin'])->prefix('ppic')->name('ppic.')->group(function () {
    Route::get('/weekly-plan', [WeeklyPlanController::class, 'index'])->name('weekly-plan.index');
    Route::post('/weekly-plan', [WeeklyPlanController::class, 'store'])->name('weekly-plan.store');
    Route::put('/weekly-plan/{weeklyPlan}', [WeeklyPlanController::class, 'update'])->name('weekly-plan.update');
    Route::delete('/weekly-plan/{weeklyPlan}', [WeeklyPlanController::class, 'destroy'])->name('weekly-plan.destroy');
    Route::post('/weekly-plan/{weeklyPlan}/activate', [WeeklyPlanController::class, 'activate'])->name('weekly-plan.activate');
    // Odoo MO sync (manual button)
    Route::post('/odoo/mo-sync', [OdooMoSyncController::class, 'sync'])->name('odoo.mo-sync');
    Route::get('/odoo/mo-list', [OdooMoSyncController::class, 'moList'])->name('odoo.mo-list');
});

// ===== SCM (Phase B + C) =====
// BOM — ppic view, warehouse_admin view, superadmin/admin full
Route::middleware(['auth', 'role:ppic|superadmin|admin|warehouse_admin|manager'])->prefix('scm')->name('scm.')->group(function () {
    Route::get('/bom', [BomController::class, 'index'])->name('bom.index');
    Route::post('/bom', [BomController::class, 'store'])->name('bom.store');
    Route::post('/bom/sync-odoo', [BomController::class, 'syncFromOdoo'])->name('bom.sync-odoo');
    Route::put('/bom/{bom}', [BomController::class, 'update'])->name('bom.update');
    Route::delete('/bom/{bom}', [BomController::class, 'destroy'])->name('bom.destroy');
});

// Material Usage — operator input, warehouse_admin full, manager/leader/spv view, superadmin/admin
Route::middleware(['auth', 'role:operator|warehouse_admin|manager|superadmin|admin|leader|spv|ppic'])->prefix('scm')->name('scm.')->group(function () {
    Route::get('/material-usage', [MaterialUsageController::class, 'index'])->name('material-usage.index');
    Route::get('/material-usage/create', [MaterialUsageController::class, 'create'])->name('material-usage.create');
    Route::get('/material-usage/export/{format}', [MaterialUsageController::class, 'export'])->name('material-usage.export');
    Route::post('/material-usage', [MaterialUsageController::class, 'store'])->name('material-usage.store');
    Route::post('/material-usage/sync-odoo', [MaterialUsageController::class, 'syncFromOdoo'])->name('material-usage.sync-odoo');
    Route::get('/material-usage/{materialUsage}', [MaterialUsageController::class, 'show'])->name('material-usage.show');

    Route::get('/scrap-material', [MaterialScrapController::class, 'index'])->name('scrap-material.index');
    Route::get('/scrap-material/create', [MaterialScrapController::class, 'create'])->name('scrap-material.create');
    Route::post('/scrap-material', [MaterialScrapController::class, 'store'])->name('scrap-material.store');
    Route::post('/scrap-material/pull-odoo', [MaterialScrapController::class, 'pullFromOdoo'])->name('scrap-material.pull-odoo');
    Route::delete('/scrap-material/{materialScrap}', [MaterialScrapController::class, 'destroy'])->name('scrap-material.destroy');
});

// Saldo Stok (read + adjust warehouse)
Route::middleware(['auth', 'role:warehouse_admin|manager|superadmin|admin|ppic'])->prefix('scm')->name('scm.')->group(function () {
    Route::get('/saldo-stok', [InventoryController::class, 'index'])->name('saldo-stok.index');
    Route::post('/saldo-stok/adjust', [InventoryController::class, 'adjust'])->name('saldo-stok.adjust');
    Route::post('/saldo-stok/sync-odoo', [InventoryController::class, 'syncFromOdoo'])->name('saldo-stok.sync-odoo');
    Route::get('/materials', [InventoryController::class, 'materials'])->name('materials');
});

// Stock Opname (Menu 2) — warehouse initiate/input, manager approve
Route::middleware(['auth', 'role:warehouse_admin|manager|superadmin|admin|spv'])->prefix('scm')->name('scm.')->group(function () {
    Route::get('/stock-opname', [StockOpnameController::class, 'index'])->name('stock-opname.index');
    Route::post('/stock-opname', [StockOpnameController::class, 'store'])->name('stock-opname.store');
    Route::get('/stock-opname/{stockOpname}', [StockOpnameController::class, 'show'])->name('stock-opname.show');
    Route::put('/stock-opname/{stockOpname}/items', [StockOpnameController::class, 'updateItems'])->name('stock-opname.items');
    Route::post('/stock-opname/{stockOpname}/recalculate', [StockOpnameController::class, 'recalculate'])->name('stock-opname.recalculate');
    Route::post('/stock-opname/{stockOpname}/post-adjustment', [StockOpnameController::class, 'postAdjustment'])->name('stock-opname.post-adjustment');
    Route::post('/stock-opname/{stockOpname}/approve', [StockOpnameController::class, 'approve'])->name('stock-opname.approve');
    Route::get('/stock-opname/{stockOpname}/print-kartu-stelling', [StockOpnameController::class, 'printKartuStelling'])->name('stock-opname.print-kartu-stelling');
    Route::delete('/stock-opname/{stockOpname}', [StockOpnameController::class, 'destroy'])->name('stock-opname.destroy');
});

// Delivery Schedule (Menu 1) — warehouse_admin + ppic, manager approve-ish
Route::middleware(['auth', 'role:warehouse_admin|manager|superadmin|admin|ppic|spv'])->prefix('scm')->name('scm.')->group(function () {
    Route::get('/delivery', [DeliveryPlanController::class, 'index'])->name('delivery.index');
    Route::get('/delivery/create', [DeliveryPlanController::class, 'create'])->name('delivery.create');
    Route::post('/delivery', [DeliveryPlanController::class, 'store'])->name('delivery.store');
    Route::get('/delivery/{deliveryPlan}', [DeliveryPlanController::class, 'show'])->name('delivery.show');
    Route::put('/delivery/{deliveryPlan}/status', [DeliveryPlanController::class, 'updateStatus'])->name('delivery.status');
    Route::get('/delivery/{deliveryPlan}/validate-stock', [DeliveryPlanController::class, 'validateStock'])->name('delivery.validate-stock');
    Route::get('/delivery/{deliveryPlan}/print/surat-jalan', [DeliveryPlanController::class, 'printSuratJalan'])->name('delivery.print.surat-jalan');
    Route::get('/delivery/{deliveryPlan}/print/faktur', [DeliveryPlanController::class, 'printFaktur'])->name('delivery.print.faktur');
    Route::get('/delivery/{deliveryPlan}/print/coa', [DeliveryPlanController::class, 'printCoa'])->name('delivery.print.coa');
    Route::post('/delivery/sync-odoo', [DeliveryPlanController::class, 'syncFromOdoo'])->name('delivery.sync-odoo');
    Route::get('/delivery/export/performance', [DeliveryPlanController::class, 'exportPerformance'])->name('delivery.export.performance');

    Route::get('/fleet', [FleetController::class, 'index'])->name('fleet.index');
    Route::post('/fleet', [FleetController::class, 'store'])->name('fleet.store');
    Route::put('/fleet/{fleet}', [FleetController::class, 'update'])->name('fleet.update');
    Route::delete('/fleet/{fleet}', [FleetController::class, 'destroy'])->name('fleet.destroy');
});

// Reject Produk routes - Leader centric
Route::middleware(['auth', 'role:leader|operator|spv|superadmin|admin|manager'])->prefix('reject')->name('reject.')->group(function () {
    Route::get('/', [RejectController::class, 'index'])->name('index');
    Route::post('/', [RejectController::class, 'store'])->name('store');
    Route::post('/sync-odoo', [RejectController::class, 'syncFromOdoo'])->name('sync-odoo');
    Route::post('/{rejectDetail}/push-odoo', [RejectController::class, 'pushToOdoo'])->name('push-odoo');
    Route::delete('/{rejectDetail}', [RejectController::class, 'destroy'])->name('destroy');
});

// Leader/Operator routes - Laporan Harian (manager: read-only)
Route::middleware(['auth', 'role:leader|operator|spv|superadmin|admin|manager'])->prefix('leader')->name('leader.')->group(function () {
    Route::get('/laporan-harian', [LaporanHarianController::class, 'index'])->name('laporan-harian.index');
    Route::get('/laporan-harian/create', [LaporanHarianController::class, 'create'])->name('laporan-harian.create');
    Route::post('/laporan-harian', [LaporanHarianController::class, 'store'])->name('laporan-harian.store');
    Route::get('/laporan-harian/{laporanHarian}', [LaporanHarianController::class, 'show'])->name('laporan-harian.show');
    Route::get('/laporan-harian/{laporanHarian}/edit', [LaporanHarianController::class, 'edit'])->name('laporan-harian.edit');
    Route::put('/laporan-harian/{laporanHarian}', [LaporanHarianController::class, 'update'])->name('laporan-harian.update');
    Route::delete('/laporan-harian/{laporanHarian}', [LaporanHarianController::class, 'destroy'])->name('laporan-harian.destroy');
    Route::post('/laporan-harian/{laporanHarian}/lock', [LaporanHarianController::class, 'lock'])->name('laporan-harian.lock');
    // Timer actions
    Route::post('/laporan-harian/{laporanHarian}/timer/start', [LaporanHarianController::class, 'startTimer'])->name('laporan-harian.timer.start');
    Route::post('/laporan-harian/{laporanHarian}/timer/pause', [LaporanHarianController::class, 'pauseTimer'])->name('laporan-harian.timer.pause');
    Route::post('/laporan-harian/{laporanHarian}/timer/end', [LaporanHarianController::class, 'endTimer'])->name('laporan-harian.timer.end');
    Route::post('/laporan-harian/{laporanHarian}/submit', [LaporanHarianController::class, 'submitLaporan'])->name('laporan-harian.submit');
});

// API routes for lookup
Route::middleware(['auth'])->prefix('api')->group(function () {
    Route::get('/weekly-plan/by-date', [LaporanHarianController::class, 'weeklyPlanByDate'])->name('api.weekly-plan.by-date');
});

require __DIR__.'/auth.php';
