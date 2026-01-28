🟢 Prompt Frontend Completo Desacoplado con Mock Data (Versión Expandida con Diseño Agro)

Quiero que generes un frontend desacoplado y presentable para un sistema agrícola de trazabilidad e inventario, con los siguientes lineamientos:

⚙️ Stack Tecnológico del Frontend

React + Vite (para rapidez y modularidad).

React Router (navegación entre pantallas).

Ant Design (AntD) como librería principal de componentes.

Tailwind CSS para estilos utilitarios, control fino de layout y responsividad.

Mock data local (JSON o servicios fakeApi.js) para simular llamadas a API.

PWA: instalable en móviles con splash screen, ícono y soporte offline básico.

Notificaciones Push: simuladas en esta fase, con placeholders para integración futura con OneSignal.

🎨 Diseño y Estilo Visual

Paleta de colores inspirada en el agro y el aguacate:

Verde aguacate (#4CAF50, #2E7D32).

Verde claro (#A5D6A7).

Café tierra (#6D4C41).

Amarillo semilla (#FFD54F).

Gris neutro (#F5F5F5, #212121 para textos).

Tipografía: moderna, clara y legible (ej: Inter o Poppins).

Diseño elegante y profesional:

Menús laterales y headers con colores base de la paleta agro.

Tablas y formularios estilizados con AntD + overrides de theme para resaltar la identidad del agro.

Iconografía clara y moderna, con íconos relacionados al campo (semillas, hojas, bodega, tractor).

Dashboard con cards visuales y gráficos limpios (ejemplo: Recharts o Ant Design Charts).

Responsive design total:

Optimización para desktop, tablet y móvil.

Uso de Grid y Flexbox (AntD + Tailwind).

Collapse automático de menús en pantallas pequeñas.

Tema personalizado de AntD:

Primary color: verde aguacate (#4CAF50).

Secondary: amarillo semilla (#FFD54F).

Background: gris claro (#F5F5F5).

Buttons y modales estilizados con esquinas redondeadas (border-radius 12–16px).

📱 Módulos / Pantallas con Mock Data

Cada pantalla debe consumir datos simulados (mock JSON) y reflejar la interacción esperada en la versión final con backend:

Login y Registro de Usuarios

Pantalla elegante con branding agro.

Roles precargados en mock (Administrador, Agrónomo, Bodega, Finca).

Dashboard Inicial

Cards con métricas clave (stock en bodega, insumos en tránsito, aplicaciones en finca, alertas).

Gráficos simples (ej: productos más usados, vencimientos próximos).

CRUD de Productos

Tabla responsiva con buscador y filtros.

Formulario modal para crear/editar.

Campos: nombre, categoría, unidad de medida, estado.

CRUD de Marcas

Tabla simple con mock data.

CRUD de Proveedores

Listado con contacto y NIT (mock).

CRUD de Fincas y Bodegas

Tabla y mapa simulado (mock).

Campos: nombre, ubicación, responsable.

Compras

Tabla con compras realizadas.

Formulario con detalle de productos (precio unitario, cantidad, subtotal).

Entradas a Bodega

Mock de lotes, vencimientos y cantidades.

Validaciones básicas simuladas.

Recetas Técnicas Estándar

Formulario para crear combinaciones de productos.

Listado de recetas.

Órdenes Técnicas

Listado con estado (Aprobada/Aplicada).

Asociación con recetas.

Mock de asignación a varias fincas.

Salidas de Bodega

Listado con estado (Pendiente, Aprobada, En tránsito).

Validación de +5% máximo (mock).

Simulación de aprobación externa con un link de prueba.

Recepción en Finca

Mock validando contra salida.

Actualización del stock simulado.

Transferencias entre Fincas

Tabla con movimientos (En tránsito, Recibido).

Inventario y Movimientos

Kardex con filtros (producto, bodega, fecha).

Mock de entradas/salidas.

Alertas y Reportes Inteligentes

Cards con notificaciones (productos próximos a vencer, sin rotación, certificaciones a vencer).

Mock de reportería exportable (CSV/PDF simulado).

🎯 Objetivo

Este frontend debe ser 100% funcional en modo demo con mock data.

El diseño debe transmitir confianza, profesionalismo y conexión con el mundo agrícola/aguacatero.

Posteriormente será integrado al backend real en Laravel con Inertia y Sanctum.