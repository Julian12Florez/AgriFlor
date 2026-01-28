🟢 1. Prompt Full Stack Completo

Quiero que generes un sistema agrícola integral con el siguiente stack tecnológico:

⚙️ Backend

Framework: Laravel (última versión).

Autenticación:

Laravel Sanctum para autenticación basada en tokens de sesión (API + SPA).

Laravel Fortify para login, registro, restablecer contraseña.

Roles y permisos: Spatie Laravel Permission.

Base de datos: MySQL/MariaDB.

API RESTful: para exponer todos los módulos documentados.

🎨 Frontend

React + Vite.

Inertia.js (se integrará en la etapa de acoplamiento con backend).

Ant Design como librería de componentes (tablas, formularios, menús, modales, notificaciones).

Tailwind CSS para estilos utilitarios y responsive.

PWA: instalable en dispositivos móviles, con splash screen, ícono y soporte offline básico.

Notificaciones Push: con OneSignal integradas al front.

🔒 Seguridad

Middleware con Sanctum para proteger rutas.

Firewall recomendado a nivel servidor (ej. reglas Nginx o Cloudflare).

Validaciones estrictas en reglas de negocio (ejemplo: salida de bodega no puede exceder 5% de lo autorizado en orden técnica).

📊 Módulos a implementar (conexión entre ellos)

Incluye los siguientes módulos con sus reglas de negocio y datos solicitados:

Inicio de Sesión y Roles (login, registro, restablecer contraseña, control de accesos por rol).

Productos (CRUD con categorías y unidades).

Marcas (CRUD básico, asociadas a productos).

Proveedores (CRUD básico).

Fincas y Bodegas (CRUD de ubicaciones, responsables, estados).

Compras (asociadas a proveedor, con detalle de productos, costos y cantidades).

Entradas a Bodega (con o sin orden de compra, validación de inventario físico, lotes, vencimientos).

Recetas Técnicas Estándar (combinaciones de productos reutilizables en órdenes técnicas).

Órdenes Técnicas (documento aprobado que define aplicaciones en finca, asociable a recetas).

Salidas de Bodega (asociadas a órdenes técnicas o libres con justificación, requieren aprobación externa vía link con vencimiento).

Recepción en Finca (valida contra la salida, actualiza stock en finca al aprobar).

Transferencias entre Fincas (movimiento entre ubicaciones, estados en tránsito y recibido).

Inventario y Movimientos (kardex completo, filtros por fecha, producto, ubicación).

Alertas Inteligentes (productos por vencer, sin rotación, certificaciones próximas a caducar, órdenes pendientes, reportería de entradas y salidas).

Trazabilidad Total (flujo documentado: compra → entrada → salida → recepción → aplicación en finca → transferencias).

🎯 Objetivo

La primera etapa debe entregar un frontend desacoplado con mock data para presentaciones.

Posteriormente se integra con el backend Laravel mediante Inertia y Sanctum.