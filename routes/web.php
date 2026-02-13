<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SwaggerController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/swagger', [SwaggerController::class, 'index'])->name('swagger.index');
Route::get('/swagger.json', [SwaggerController::class, 'json'])->name('api.swagger.json');
