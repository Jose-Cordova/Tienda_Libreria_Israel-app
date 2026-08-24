<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


//Controladores
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteCreditoController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReporteHistorialController;
use App\Http\Controllers\ReporteComprasController;
use App\Http\Controllers\ReporteCreditoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CreditoController;
use App\Http\Controllers\DevolucionVentaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ProductoDaniadoController;
use App\Http\Controllers\CambioProductoController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\CronogramaProveedorController;
use App\Http\Controllers\NotaController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rutas públicas de autenticación
Route::prefix('auth')->group(function(){
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Rutas de recuperación de contraseña
Route::post('set-password', [UserController::class, 'setPassword']);
Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('reset-password', [ForgotPasswordController::class, 'reset']);

// Grupo de rutas protegidas que requieren autenticación
Route::middleware(['auth:api'])->group(function(){

    // Subgrupo de autenticación de usuario activo
    Route::prefix('auth')->group(function(){
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    // Rutas accesibles tanto para ADMIN como para VENDEDOR
    Route::middleware(['role:ADMIN|VENDEDOR'])->group(function(){
        // Ventas y tickets
        Route::apiResource('ventas', VentaController::class);
        Route::get('/ventas/{id}/ticket', [VentaController::class, 'ticket'])->name('ventas.ticket');

        // Métodos de pago y clientes
        Route::apiResource('metodos-pagos', MetodoPagoController::class)->only(['index', 'show']);
        Route::apiResource('clientes-creditos', ClienteCreditoController::class);

        // Créditos y abonos
        Route::apiResource('creditos', CreditoController::class)->only(['index', 'show']);
        Route::post('creditos/{id}/abonos', [CreditoController::class, 'storeAbono']);
        Route::patch('abonos/{id}/anular', [CreditoController::class, 'anularAbono']);
        Route::get('abonos/{id}/ticket', [CreditoController::class, 'ticketAbono']);

        // Devoluciones y productos dañados
        Route::apiResource('devoluciones-ventas', DevolucionVentaController::class);
        Route::get('productos-daniados/lotes-vencidos', [ProductoDaniadoController::class, 'lotesVencidos']);
        Route::apiResource('productos-daniados', ProductoDaniadoController::class);
        Route::post('productos-daniados/{id}/anular', [ProductoDaniadoController::class, 'anular']);
        Route::apiResource('cambios-productos', CambioProductoController::class);
        Route::post('cambios-productos/{id}/anular', [CambioProductoController::class, 'anular']);
        Route::post('cambios-productos/{id}/aceptar', [CambioProductoController::class, 'aceptar']);
        Route::post('cambios-productos/{id}/rechazar', [CambioProductoController::class, 'rechazar']);

        // Consulta de categorías y marcas para filtros de productos
        Route::apiResource('categorias', CategoriaController::class)->only(['index', 'show']);
        Route::apiResource('marcas', MarcaController::class)->only(['index', 'show']);

        // Consulta de productos para ventas
        Route::get('productos/alerta-stock-minimo', [ProductoController::class, 'alertaStockMinimo']);
        Route::apiResource('productos', ProductoController::class)->only(['index', 'show']);

        // Notas personales
        Route::apiResource('notas', NotaController::class);
    });

    // Rutas exclusivas para el rol ADMIN
    Route::middleware(['role:ADMIN'])->group(function(){
        // Dashboard principal
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Proveedores y compras
        Route::apiResource('proveedores', ProveedorController::class);
        Route::apiResource('compras', CompraController::class);
        Route::post('compras/{id}/anular', [CompraController::class, 'anular']);
        Route::apiResource('cronograma-proveedores', CronogramaProveedorController::class);

        // Creación, edición y eliminación de categorías y marcas (solo Admin)
        Route::apiResource('categorias', CategoriaController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('marcas', MarcaController::class)->only(['store', 'update', 'destroy']);

        // Creación y modificación de productos
        Route::post('productos', [ProductoController::class, 'store']);
        Route::put('productos/{producto}', [ProductoController::class, 'update']);
        Route::delete('productos/{producto}', [ProductoController::class, 'destroy']);
        Route::patch('productos/{id}/cambiar-estado', [ProductoController::class, 'cambiarEstado']);

        // Gestión de usuarios
        Route::apiResource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('users/{id}/status', [UserController::class, 'changeStatus']);
        Route::post('users/{id}/resend', [UserController::class, 'resendInvitation']);

        // Configuración de la tienda
        Route::apiResource('configuracion', ConfiguracionController::class);

        // Reportes de la tienda
        Route::get('/reportes/resumen', [ReporteController::class, 'resumenJson']);
        Route::get('/reportes/general', [ReporteController::class, 'reporteGeneral']);
        Route::get('/reportes/historial-datos', [ReporteHistorialController::class, 'historialDatos']);
        Route::get('/reportes/historial', [ReporteHistorialController::class, 'reporteHistorial']);
        Route::get('/reportes/compras', [ReporteComprasController::class, 'reporteCompras']);
        Route::get('/reportes/compras-datos', [ReporteComprasController::class, 'comprasDatos']);
        Route::get('/reportes/creditos', [ReporteCreditoController::class, 'reporteCreditos']);
        Route::get('/reportes/creditos-datos', [ReporteCreditoController::class, 'creditosDatos']);
        Route::get('reportes/ventas', [ReporteController::class, 'ventas']);
        Route::get('reportes/productos-daniados', [ReporteController::class, 'productosDaniados']);
        Route::get('reportes/inventario', [ReporteController::class, 'inventario']);
        Route::get('reportes/cierre-diario', [ReporteController::class, 'cierreDiario']);
    });
});
