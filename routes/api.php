<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MetodoPagoContoller;

//Conroladores
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteCreditoController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Rutas
Route::apiResource('categorias', CategoriaController::class);
Route::apiResource('metodos-pagos', MetodoPagoContoller::class);
Route::apiResource('clientes-creditos', ClienteCreditoController::class);
