<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MetodoPagoContoller;

//Controladores
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteCreditoController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Rutas de autenticación - públicas (no requieren token)
Route::post('login',  [AuthController::class, 'login']);

//Rutas de autenticación - protegidas (requieren token JWT válido)
Route::middleware('auth:api')->group(function () {
    Route::post('logout',  [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('me',       [AuthController::class, 'me']);
});

//Rutas
Route::apiResource('categorias', CategoriaController::class);
Route::apiResource('metodos-pagos', MetodoPagoContoller::class);
Route::apiResource('clientes-creditos', ClienteCreditoController::class);
