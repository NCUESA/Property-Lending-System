<?php

use App\Http\Controllers\BorrowController;
use App\Http\Controllers\BorrowRequest;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HelloWorldController;
use App\Http\Controllers\IPController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ResponsibleController;
use App\Http\Controllers\AuthController;

// SSO routes
Route::prefix('auth')->group(function () {
    Route::get('/redirect', [AuthController::class, 'redirect'])->name('login');
    Route::get('/synology/callback', [AuthController::class, 'callback']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

/* * Level 0: Muggle (Basic access) 
 */

Route::get('/', function () {
    return view('home', ['js_name' => 'index']);
});

Route::get('/form', function () {
    return view('form');
});

Route::get('/status', function () {
    return view('status', ['js_name' => 'status']);
});

// Public Borrow API
Route::prefix('borrow')->name('borrow')->group(function () {
    Route::prefix('getData')->name('getData')->group(function () {
        Route::get('/', [BorrowController::class, 'getLendingStatusData']);
        Route::get('/{id}', [BorrowController::class, 'getLendingStatusDataSingle']);
        Route::post('/condition', [BorrowController::class, 'getLendingStatusDataInCondition']);
        Route::post('/single', [BorrowController::class, 'getLendingStatusSingleWithID']);
    });

    Route::prefix('item')->group(function () {
        Route::post('/', [BorrowController::class, 'sendBorrowRequest']);
        Route::post('/final', [BorrowController::class, 'sendFinalRequest']);
    });
});


/* * Level 5: Normal (Intermediate access) 
 */
Route::middleware('roleAuth:5')->group(function () {
    Route::name('status_table')->prefix('status_table')->group(function () {
        Route::get('/', function () {
            return view('status_table.full_status', ['js_name' => 'full_status']);
        });
        Route::get('/control', function () {
            return view('status_table.control', ['js_name' => 'full_status_control']);
        });
    });

    // Moved property API inside Level 5 protection
    Route::name('property')->prefix('property')->group(function () {
        Route::prefix('info')->group(function () {
            Route::post('/get', [PropertyController::class, 'getPropertyData']);
            Route::post('/getWithBorrowID', [PropertyController::class, 'getPropertyDataWithBorrowID']);
            Route::post('/update', [PropertyController::class, 'updatePropertyData']);
        });
        Route::prefix('borrowable')->group(function () {
            Route::post('/show', [PropertyController::class, 'getBorrowableData']);
        });
        Route::prefix('status')->group(function () {
            Route::post('/show', [PropertyController::class, 'getPropertyStatusData']);
        });
    });
});

/* * Level 10: Admin (Highest access) 
 */
Route::middleware('roleAuth:10')->group(function () {
    Route::get('/maintain', function () {
        return view('maintain', ['js_name' => 'maintain']);
    });

    Route::get('/user', function () {
        return view('user', ['js_name' => 'responsible']);
    });

    Route::prefix('ip')->group(function () {
        Route::get('/', function () {
            return view('ip', ['js_name' => 'ip']);
        });
        Route::post('/add', [IPController::class, 'addIP']);
        Route::post('/show', [IPController::class, 'showIP']);
        Route::post('/delete', [IPController::class, 'deleteIP']);
    });

    // Moved responsible API inside Level 10 protection
    Route::name('user')->group(function () {
        Route::post('/show-user', [ResponsibleController::class, 'showUserFull']);
        Route::post('/show-user-name', [ResponsibleController::class, 'showUserNameOnly']);
        Route::post('/add-user', [ResponsibleController::class, 'addUser']);
        Route::post('/delete-user', [ResponsibleController::class, 'deleteUser']);
    });
});
