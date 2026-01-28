Prompt para Sistema de Permisos y Exportación de Informes
Como desarrollador experto en Laravel y React, necesito implementar las siguientes funcionalidades en mi aplicación:
1. Sistema de Validación de Permisos Basado en Roles
Contexto:
Tengo una aplicación Laravel + React que requiere control de acceso al menú basado en roles de usuario.
Requisitos:
Roles y sus permisos:

Supervisor, Bodeguero, Operario de Finca: Solo acceso a módulos de "Recepción" y "Salida"
Compras: Acceso a todos los módulos del sistema EXCEPTO el módulo de "Administración"
Administrador: Acceso completo a todos los módulos del sistema (incluyendo Administración)

Necesito:
Backend (Laravel):

Middleware para validar permisos por rol
Implementación de políticas (Policies) o Gates según mejor práctica
Rutas protegidas con los permisos correspondientes, con lógica específica para excluir Administración del rol Compras
Estructura de base de datos si es necesario (tablas roles, permissions, role_user)
Seeders para roles iniciales (Administrador, Supervisor, Bodeguero, Operario de Finca, Compras)
Sistema flexible que permita definir permisos por módulo/recurso

Frontend (React):

HOC o Custom Hook para verificar permisos en componentes
Sistema de menú dinámico que muestre/oculte opciones según rol del usuario autenticado
Protección de rutas en React Router con validación específica para cada rol
Manejo de estado para permisos del usuario (Context API o Redux)
Componente de menú que implemente la lógica:

Supervisor, Bodeguero, Operario: Solo ver Recepción y Salida
Compras: Ver todo excepto Administración
Administrador: Ver todo



Código completo funcional para:

Tabla/modelo de roles con campo para identificar permisos especiales
Tabla/modelo de permisos por módulo
Middleware de autorización con lógica de exclusión
Sistema de verificación de permisos reutilizable
Componente de menú dinámico en React con renderizado condicional
Sistema de rutas protegidas en ambos lados (backend y frontend)
Helper functions para verificar acceso por rol y módulo


2. Exportación de Informes a Excel y PDF
Contexto:
Actualmente tengo tres informes que solo se visualizan en pantalla:

Stock actual
Consumo de productos
Movimientos

Estos informes YA están implementados visualmente en el sistema con sus respectivas estructuras de datos, columnas, filtros y lógica de negocio.
Necesito:
Tu tarea es:

Analizar los informes visuales existentes en el código actual
Identificar automáticamente:

Qué columnas se muestran en cada informe
Qué filtros están disponibles
Qué datos se consultan desde el backend
Formato y presentación actual


Crear exportaciones a Excel y PDF que repliquen EXACTAMENTE la estructura visual de los informes existentes

Backend (Laravel):

Controladores para generar exportaciones
Uso de Laravel Excel (maatwebsite/excel) para archivos Excel
Uso de DomPDF o Snappy para archivos PDF
Endpoints API para cada tipo de exportación
Reutilizar las mismas queries y lógica de los informes visuales existentes
Aplicar los mismos filtros que se usan en la interfaz visual
Validación de permisos: verificar que el usuario tenga acceso al módulo de informes según su rol

Frontend (React):

Botones de exportación en cada vista de informe (Excel y PDF)
Manejo de descarga de archivos
Indicadores de carga durante la generación
Manejo de errores en caso de fallo
Deshabilitación de botones según permisos del usuario
Pasar los filtros activos actuales a los endpoints de exportación

Requisitos técnicos:

Excel:

Formato XLSX con las mismas columnas de la tabla visual
Estilos: encabezados en negrita, autosize de columnas, filtros activados
Conservar el mismo orden de columnas


PDF:

Diseño profesional, logo de empresa
Encabezados y pie de página
Orientación landscape (horizontal) o portrait según el número de columnas
Misma estructura de columnas que la vista visual


Ambos formatos deben incluir:

Título del informe
Fecha y hora de generación
Usuario que generó el informe
Filtros aplicados (los mismos que están activos en la interfaz)
Totales y sumatorias donde aplique (si existen en la vista visual)
Mismo formato de datos (fechas, números, monedas)


Optimización:

Para grandes volúmenes de datos usar paginación o chunks
Nombres de archivos descriptivos: nombre_informe_YYYY_MM_DD_HHMMSS.xlsx
Timeout adecuado para informes grandes



Importante:

NO inventes estructuras nuevas
Analiza el código existente de los informes visuales
Replica EXACTAMENTE lo que el usuario ya ve en pantalla
Mantén la consistencia entre la vista web y las exportaciones
Si hay cálculos o agregaciones en la vista, inclúyelos en las exportaciones


Entregables esperados:

Código Laravel completo para:

Sistema de permisos con roles y módulos
Middleware y políticas de autorización
Controladores de exportación
Clases Export para Laravel Excel que repliquen los informes visuales
Views para PDFs que repliquen los informes visuales


Componentes React para:

Menú dinámico con lógica de permisos
HOC/Hook de autorización
Botones de exportación integrados en las vistas de informes existentes
Protección de rutas


Archivos de migración y seeders:

Tabla roles
Tabla permissions
Tabla role_permission (si aplica)
Datos iniciales de los 5 roles


Instrucciones de instalación de paquetes requeridos:

maatwebsite/excel
barryvdh/laravel-dompdf (o alternativa)


Ejemplos de uso y mejores prácticas
Código listo para producción con:

Manejo de errores y validaciones
Logs de acciones importantes
Tests unitarios básicos (opcional pero deseable)




Información adicional de mi stack:

Laravel: [versión]
React: [versión]
Autenticación: [Laravel Sanctum/Passport/JWT]
Gestión de estado: [Context API/Redux/Zustand]
Base de datos: [MySQL/PostgreSQL]

Por favor, proporciona una solución completa, escalable, mantenible y siguiendo las mejores prácticas de Laravel y React. El código debe ser fácil de extender para agregar nuevos roles o módulos en el futuro.
CRÍTICO: Antes de crear las exportaciones, analiza primero el código de los informes visuales existentes para replicar su estructura exacta.