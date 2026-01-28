3. Prompt Backend Completo

Quiero que generes un backend en Laravel para un sistema agrícola con:

Tecnologías

Laravel (última versión).

Sanctum para autenticación de SPA/API.

Fortify para login, registro, restablecer contraseña.

Spatie Permission para roles y permisos.

Base de datos MySQL/MariaDB.

Funcionalidades / Módulos

Usuarios y Roles:

CRUD de usuarios.

Roles y permisos con Spatie.

Protección de rutas con middleware.

Productos, Marcas, Proveedores, Fincas:

Migraciones y modelos.

Relación productos ↔ marcas.

CRUD de proveedores y ubicaciones.

Compras:

Registro de compras con proveedor, productos, precio unitario y subtotal.

Estado: comprada, en tránsito, recibida.

Entradas a Bodega:

Validación de inventario físico.

Ingresos con o sin orden de compra.

Lotes, fechas de vencimiento.

Actualización automática de stock.

Recetas Técnicas Estándar:

Modelo maestro de combinaciones de productos.

Asociación reutilizable en órdenes técnicas.

Órdenes Técnicas:

Creación en estado aprobada.

Asociación opcional a recetas.

Estado: aprobada / aplicada.

Salidas de Bodega:

Asociación a orden técnica o libres con justificación.

Validación: no permitir más del 5% adicional a lo aprobado.

Aprobación externa por link con vencimiento temporal.

Estado: pendiente de aprobación / aprobada / en tránsito.

Recepción en Finca:

Validación contra la salida.

Estado: recibido / rechazado (parcial o total).

Actualiza stock de finca solo si se aprueba.

Transferencias entre Fincas:

Movimiento con estado en tránsito y recibido.

Inventario y Movimientos:

Kardex completo.

Cierres mensuales.

Reportes por finca, producto, fechas.

Alertas Inteligentes:

Productos por vencer.

Sin rotación.

Órdenes no aplicadas en fecha.

Certificaciones próximas a vencer.

Notificaciones push vía OneSignal.

Objetivo

Backend 100% modular, seguro y preparado para integrarse con un frontend React mediante Inertia y Sanctum.