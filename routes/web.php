<?php

use App\Http\Controllers\BorrowController;
use App\Http\Controllers\BorrowRequest;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HelloWorldController;
use App\Http\Controllers\IPController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ResponsibleController;

/*Route::get('/', function () {
    return view('welcome');
});*/

/**
 * The View of all frontend pages
 * only for the control system and present page.
 * 
 * 
 */
Route::middleware('ipAuth:0')->group(function () {
    Route::get('/', function () {
        return view('home', ['js_name' => 'index']);
    });

    Route::get('/status', function () {
        return view('status', ['js_name' => 'status']);
    });
});

Route::middleware('ipAuth:5')->group(function () {
    Route::get('/full_status', function () {
        return view('full_status', ['js_name' => 'full_status']);
    });
});

Route::middleware('ipAuth:10')->group(function () {
    Route::get('/maintain', function () {
        return view('maintain', ['js_name' => 'maintain']);
    });

    Route::get('/responsible', function () {
        return view('responsible', ['js_name' => 'responsible']);
    });
});

// JS Action, Seperate by the contorller name
Route::name('property')->group(function () {
    Route::post('/get-property-info', [PropertyController::class, 'getPropertyData']);
    Route::post('/get-property-with-borrow-id', [PropertyController::class, 'getPropertyDataWithBorrowID']);
    Route::post('/show-borrowable-item', [PropertyController::class, 'getBorrowableData']);
    Route::post('/show-item-status', [PropertyController::class, 'getPropertyStatusData']);
    Route::post('/update-property-info', [PropertyController::class, 'updatePropertyData']);
});

// ->prefix('borrow')
Route::prefix('borrow')->group(function () {

    Route::prefix('getData')->group(function () {
        Route::post('/', [BorrowController::class, 'getLendingStatusData']);
        Route::post('/condition', [BorrowController::class, 'getLendingStatusDataInCondition']);
    });

    Route::prefix('item')->group(function () {
        Route::post('/', [BorrowController::class, 'sendBorrowRequest']);
        Route::post('/final', [BorrowController::class, 'sendFinalRequest']);
    });
});

Route::name('responsible')->group(function () {
    Route::post('/show-user', [ResponsibleController::class, 'showUserFull']);
    Route::post('/show-user-name', [ResponsibleController::class, 'showUserNameOnly']);
    Route::post('/add-user', [ResponsibleController::class, 'addUser']);
});

Route::prefix('ip')->group(function () {
    Route::middleware('ipAuth:10')->get('/', function () {
        return view('ip', ['js_name' => 'ip']);
    });
    Route::post('/add', [IPController::class, 'addIP']);
    Route::post('/show', [IPController::class, 'showIP']);
    Route::post('/delete', [IPController::class, 'deleteIP']);
});
