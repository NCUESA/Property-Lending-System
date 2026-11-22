<?php

use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\BorrowRequestController;
use App\Http\Controllers\MyBorrowController;
use App\Http\Controllers\PropertyStatusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

// 首頁：值班人員以上進後台，其他人（含訪客）到借用申請
Route::get('/', fn (Request $request) => $request->user() && Gate::allows('staff')
    ? to_route('admin.borrows.index')
    : to_route('borrow.create'))->name('home');

// ── 公開頁面（訪客可用）────────────────────────────────
Route::get('borrow', [BorrowRequestController::class, 'create'])->name('borrow.create');
Route::post('borrow', [BorrowRequestController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('borrow.store');

Route::get('properties/status', [PropertyStatusController::class, 'index'])->name('properties.status');

// ── 登入：統一使用 NCUESA SSO，不提供密碼登入 ───────────
Route::middleware('guest')->group(function () {
    Route::get('login', [SsoController::class, 'create'])->name('login');
    Route::get('auth/sso/redirect', [SsoController::class, 'redirect'])->name('auth.sso.redirect');
    Route::get('auth/sso/callback', [SsoController::class, 'callback'])->name('auth.sso.callback');
});

// 借用人自己的紀錄（訪客申請沒有帳號可以歸屬，所以一定要登入）
Route::middleware('auth')->group(function () {
    Route::get('my/borrows', [MyBorrowController::class, 'index'])->name('my.borrows.index');
    Route::post('my/borrows/{borrow}/cancel', [MyBorrowController::class, 'cancel'])->name('my.borrows.cancel');
});

Route::post('logout', [SsoController::class, 'destroy'])->middleware('auth')->name('logout');

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
