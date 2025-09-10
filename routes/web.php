<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShortLinkController;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/shortlink', [ShortLinkController::class,'index']);