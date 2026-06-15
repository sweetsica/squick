<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileManagerController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/upload', function () {
    return view('upload');
})->name('upload');

Route::post('/upload', [App\Http\Controllers\Api\ReportUpload::class, 'store']);

Route::post('/result', function (Illuminate\Http\Request $request) {
    return view('result', ['files' => $request->get('files', [])]);
})->name('result');

// File Manager Dashboard
Route::get('/dashboard', [FileManagerController::class, 'index'])->name('dashboard');

Route::prefix('fm')->group(function () {
    Route::get('/files', [FileManagerController::class, 'listFiles']);
    Route::get('/upload-dates', [FileManagerController::class, 'uploadDates']);
    Route::get('/folder-tree', [FileManagerController::class, 'folderTree']);
    Route::post('/folder', [FileManagerController::class, 'createFolder']);
    Route::post('/upload', [FileManagerController::class, 'upload']);
    Route::put('/rename/{id}', [FileManagerController::class, 'rename']);
    Route::put('/move/{id}', [FileManagerController::class, 'move']);
    Route::put('/toggle-visibility/{id}', [FileManagerController::class, 'toggleVisibility']);
    Route::delete('/delete/{id}', [FileManagerController::class, 'destroy']);
    Route::get('/info/{id}', [FileManagerController::class, 'info']);
    Route::get('/download/{id}', [FileManagerController::class, 'download']);
    Route::get('/stats', [FileManagerController::class, 'stats']);
});
