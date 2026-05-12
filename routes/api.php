<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Conroladores
use App\Http\Controllers\CategoriaController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Rutas
Route::apiResource('categorias', CategoriaController::class);
