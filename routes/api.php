<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Conroladores
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\UnidadMedidaController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Rutas
Route::apiResource('categorias', CategoriaController::class);
Route::apiResource('marcas', MarcaController::class);
Route::apiResource('unidadesmedidas', UnidadMedidaController::class);
