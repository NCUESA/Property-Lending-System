<?php

use App\Http\Controllers\BorrowController;
use App\Http\Controllers\BorrowRequest;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HelloWorldController;
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

Route::get('/', function () {
    return view('home', ['js_name' => 'index']);
});

Route::get('/status', function () {
    return view('status', ['js_name' => 'status']);
});

Route::get('/full_status', function () {
    return view('full_status', ['js_name' => 'full_status']);
});

Route::get('/maintain', function () {
    return view('maintain', ['js_name' => 'maintain']);
});

Route::get('/responsible', function () {
    return view('responsible', ['js_name' => 'responsible']);
});


/**
 * The route of database action.
 * 
 * Read
 * 
 */

Route::post('/get-property-info', [PropertyController::class, 'getPropertyData']);
Route::post('/get-property-with-borrow-id', [PropertyController::class, 'getPropertyDataWithBorrowID']);
Route::post('/show-borrowable-item', [PropertyController::class, 'getBorrowableData']);
Route::post('/show-item-status', [PropertyController::class, 'getPropertyStatusData']);
Route::post('/show-lending-status', [BorrowController::class, 'getLendingStatusData']);
Route::post('/show-user', [ResponsibleController::class, 'showUserFull']);
Route::post('/show-user-name', [ResponsibleController::class, 'showUserNameOnly']);

/**
 * The route of database action.
 * 
 * Write
 * 
 */
Route::post('/borrow-items', [BorrowController::class, 'sendBorrowRequest']);
Route::post('/add-user', [ResponsibleController::class, 'addUser']);
Route::post('/items-final', [BorrowController::class, 'sendFinalRequest']);
Route::post('/update-property-info', [PropertyController::class, 'updatePropertyData']);
