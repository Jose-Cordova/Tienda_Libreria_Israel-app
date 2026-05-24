<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


//Controladores
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteCreditoController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\UnidadMedidaController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\ProductoController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function(){
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function(){
        Route::get('me',[AuthController::class, 'me']);
        Route::post('logout',[AuthController::class, 'logout']);
        Route::post('refresh',[AuthController::class, 'refresh']);
    });
});

//Rutas
Route::apiResource('categorias', CategoriaController::class);
Route::apiResource('metodos-pagos', MetodoPagoController::class);
Route::apiResource('clientes-creditos', ClienteCreditoController::class);
Route::apiResource('marcas', MarcaController::class);
Route::apiResource('unidades-medidas', UnidadMedidaController::class);
Route::apiResource('ventas', VentaController::class);

Route::middleware(['auth:api', 'role:ADMIN'])->group(function(){
    Route::apiResource('proveedores', ProveedorController::class);
    Route::apiResource('compras', CompraController::class);
    Route::post('compras/{id}/anular', [CompraController::class, 'anular']);
});
Route::get('productos/alerta-stock-minimo', [ProductoController::class, 'alertaStockMinimo']);
Route::patch('productos/{id}/cambiar-estado', [ProductoController::class, 'cambiarEstado']);
Route::apiResource('productos', ProductoController::class);

