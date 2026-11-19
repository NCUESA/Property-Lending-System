<?php

use App\Http\Controllers\Admin\BorrowPeriodController;
use App\Http\Controllers\Admin\BorrowRequestController;
use App\Http\Controllers\Admin\PropertyCategoryController;
use App\Http\Controllers\Admin\PropertyCodeController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\PropertyPoolController;
use App\Http\Controllers\Admin\PropertySubcategoryController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserGroupController;
use Illuminate\Support\Facades\Route;

/*
 * 後台：所有路由（含寫入）都在 auth 之下，並依角色套用 can: 權限。
 */
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {

    // ── 值班人員以上：借用單管理 ──────────────────────────
    Route::middleware('can:staff')->group(function () {
        Route::get('borrows', [BorrowRequestController::class, 'index'])->name('borrows.index');
        Route::get('borrows/{borrow}', [BorrowRequestController::class, 'show'])->name('borrows.show');
        Route::patch('borrows/{borrow}', [BorrowRequestController::class, 'update'])->name('borrows.update');
        Route::post('borrows/{borrow}/lend', [BorrowRequestController::class, 'lend'])->name('borrows.lend');
        Route::post('borrows/{borrow}/return', [BorrowRequestController::class, 'return'])->name('borrows.return');
        Route::post('borrows/{borrow}/cancel', [BorrowRequestController::class, 'cancel'])->name('borrows.cancel');
    });

    // ── 管理員：器材、編碼、人員、群組、設定 ───────────────
    Route::middleware('can:admin')->group(function () {
        Route::resource('properties', PropertyController::class)->except('show');
        Route::get('property-codes/next', [PropertyCodeController::class, 'next'])->name('property-codes.next');

        Route::resource('categories', PropertyCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('categories.subcategories', PropertySubcategoryController::class)
            ->only(['store', 'update', 'destroy'])
            ->shallow();

        Route::resource('pools', PropertyPoolController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
        Route::resource('groups', UserGroupController::class)->except(['create', 'show']);

        Route::resource('periods', BorrowPeriodController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
});
