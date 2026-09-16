# 🛠️ Backend - Tienda y Librería Israel (API REST)

Sistema backend para la gestión integral de inventario, punto de venta (POS), compras con Costo Promedio Ponderado (CPP), control de lotes con fechas de vencimiento, créditos y generación de reportes contables.

---

## 🚀 Tecnologías Principales

* **Framework:** Laravel 12 (PHP 8.2+)
* **Base de Datos:** PostgreSQL 15+
* **Autenticación & Seguridad:** JWT (	ymon/jwt-auth) y Laravel Sanctum
* **Roles y Permisos:** Spatie Laravel Permission (spatie/laravel-permission)
* **Generación de Reportes:** DomPDF (arryvdh/laravel-dompdf)
* **ORM:** Eloquent con transacciones atómicas (ACID)

---

## 📂 Estructura Limpia del Proyecto

`	ext
Tienda_Libreria_Isarael-app/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # Controladores de la API REST
│   │   │   ├── Auth/             # Autenticación (Login, Recuperación)
│   │   │   ├── CompraController.php            # Compras y Costo Promedio Ponderado
│   │   │   ├── VentaController.php             # Facturación y ventas POS
│   │   │   ├── ProductoController.php          # Catálogo e inventario
│   │   │   ├── CreditoController.php           # Cuentas por cobrar y abonos
│   │   │   ├── CambioProductoController.php    # Garantías y cambios
│   │   │   ├── DevolucionVentaController.php   # Devoluciones de clientes
│   │   │   ├── ProductoDaniadoController.php   # Registro de mermas/daños
│   │   │   ├── ProveedorController.php         # Gestión de proveedores
│   │   │   ├── CronogramaProveedorController.php # Visitas de proveedores
│   │   │   ├── ReporteController.php           # Generación de reportes PDF
│   │   │   └── UserController.php              # Administración de usuarios
│   │   └── Requests/             # Form Requests (Validaciones desacopladas)
│   ├── Models/                   # Modelos Eloquent y relaciones relacionales
│   │   ├── Producto.php          # Producto con costo promedio y stock
│   │   ├── Lote.php              # Control de vencimientos y lotes activos
│   │   ├── Compra.php / DetalleCompra.php
│   │   ├── Venta.php / DetalleVenta.php
│   │   ├── Credito.php / AbonoCredito.php
│   │   └── ...
├── database/
│   ├── migrations/               # Esquema de tablas y relaciones PostgreSQL
│   ├── seeders/                  # Datos iniciales para pruebas
│   └── factories/                # Generadores de datos simulados
├── resources/
│   └── views/
│       └── reportes/             # Plantillas Blade optimizadas para DomPDF
├── routes/
│   ├── api.php                   # Endpoints protegidos de la API REST
│   └── web.php                   # Rutas web base
├── .env.example                  # Plantilla de configuración de entorno
└── composer.json                 # Dependencias y scripts de PHP
`

---

## ⚙️ Requisitos Previos

* PHP >= 8.2 con extensiones: pdo, pdo_pgsql, mbstring, openssl, cmath, curl.
* Composer 2.x
* PostgreSQL 14+ con base de datos creada (ej: 	ienda_libreria_israel)

---

## 🔧 Instalación y Configuración

1. **Clonar o descargar el proyecto:**
   `ash
   cd Tienda_Libreria_Isarael-app
   `

2. **Instalar dependencias:**
   `ash
   composer install
   `

3. **Configurar el entorno:**
   `ash
   cp .env.example .env
   php artisan key:generate
   php artisan jwt:secret
   `

4. **Configurar la conexión a PostgreSQL en el archivo .env:**
   `env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=tienda_libreria_israel
   DB_USERNAME=postgres
   DB_PASSWORD=tu_password
   `

5. **Ejecutar migraciones y seeders:**
   `ash
   php artisan migrate --seed
   `

6. **Iniciar el servidor de desarrollo:**
   `ash
   php artisan serve --port=8000
   `
   *La API estará disponible en:* http://localhost:8000/api

---

## 📦 Lógica Central: Costo Promedio Ponderado (CPP)

El sistema recalcula el costo unitario base del producto en cada compra para proteger el margen de ganancia:

\text{Nuevo CPP} = \frac{(\text{Stock Previo} \times \text{CPP Anterior}) + (\text{Cantidad Comprada} \times \text{Costo Unitario Factura})}{\text{Stock Previo} + \text{Cantidad Comprada}}

* **Venta al Detalle:** $\text{Nuevo CPP} \times (1 + \text{Margen Detalle}\%)$
* **Venta al Mayor:** $\text{Nuevo CPP} \times (1 + \text{Margen Mayor}\%)$

---

## 📑 Principales Módulos y Endpoints

| Método | Endpoint | Descripción |
| :--- | :--- | :--- |
| POST | /api/login | Autenticación y emisión de JWT Token |
| GET/POST | /api/productos | Consulta con filtros y registro de productos |
| GET/POST | /api/compras | Listado y registro de compras (Wizard) |
| PUT | /api/compras/{id}/anular | Anulación de compra y reversión de stock/CPP |
| GET/POST | /api/ventas | Facturación de punto de venta |
| GET/POST | /api/creditos | Control de cuentas por cobrar y abonos |
| GET | /api/reportes/{tipo}/pdf | Descarga de reportes PDF |
