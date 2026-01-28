# Plan de Migración: UUID → INTEGER (UNSIGNED)

## ⚠️ ADVERTENCIA CRÍTICA

**NO EJECUTAR ESTA MIGRACIÓN EN PRODUCCIÓN O DESARROLLO ACTIVO**

Esta migración es **destructiva** y requiere:
- Backup completo de la base de datos
- Tiempo de inactividad del sistema
- Recreación de todas las relaciones
- Pérdida de datos UUID existentes
- Actualización de todas las referencias en el código

**Recomendación:** Mantener el sistema con UUIDs. Los beneficios de cambiar a INTEGER son mínimos comparados con el riesgo y esfuerzo requerido.

---

## 📊 Análisis del Sistema Actual

El sistema AgriFlor utiliza **UUIDs (Universally Unique Identifiers)** como claves primarias en todas las tablas principales.

### Ventajas de UUIDs (Sistema Actual)
✅ Generación distribuida sin colisiones
✅ Seguridad por obscuridad (IDs no predecibles)
✅ Facilita merge de datos entre bases de datos
✅ Ideal para sistemas distribuidos
✅ No requiere coordinación centralizada

### Desventajas de UUIDs
❌ Mayor espacio de almacenamiento (16 bytes vs 4 bytes)
❌ Índices más grandes y potencialmente más lentos
❌ Menos legibles para humanos
❌ URLs más largas

---

## 📋 Inventario de Tablas (38 tablas)

### Tablas del Sistema

1. **alerts** - Alertas del sistema
2. **application_products** - Productos aplicados (FK: application_id, product_id, brand_id, reception_id)
3. **applications** - Aplicaciones de productos (FK: origin_location_id, farm_lot_id, applied_by, approved_by, cancelled_by)
4. **base_units** - Unidades base de medida
5. **brands** - Marcas de productos
6. **cache** - Sistema de caché (tabla técnica)
7. **cache_locks** - Locks de caché (tabla técnica)
8. **farm_lots** - Lotes de finca (FK: location_id, created_by)
9. **inventory** - Inventario (FK: product_id, brand_id, location_id, reception_id, purchase_id)
10. **inventory_movements** - Movimientos de inventario (FK: product_id, brand_id, location_id, responsible_user)
11. **locations** - Ubicaciones/Bodegas (FK: created_by)
12. **output_farm_lots** - Relación salidas-lotes (FK: product_output_id, farm_lot_id)
13. **output_products** - Productos en salidas (FK: product_output_id, product_id, brand_id)
14. **output_types** - Tipos de salida
15. **packaging_units** - Unidades de empaque (FK: created_by)
16. **permissions** - Permisos del sistema
17. **personal_access_tokens** - Tokens de acceso (tabla técnica de Sanctum)
18. **product_outputs** - Salidas de productos (FK: origin_location_id, destination_location_id, output_type_id, created_by, approved_by)
19. **product_packaging_units** - Relación productos-empaques (FK: product_id, packaging_unit_id)
20. **products** - Productos (FK: brand_id, created_by)
21. **purchase_attachments** - Adjuntos de compras (FK: purchase_id)
22. **purchase_items** - Items de compra (FK: purchase_id, product_id, brand_id)
23. **purchases** - Órdenes de compra (FK: supplier_id, origin_location_id, created_by, approved_by)
24. **reception_batch_attachments** - Adjuntos de lotes (FK: reception_batch_id)
25. **reception_batches** - Lotes de recepción (FK: reception_id, location_id, created_by, approved_by)
26. **reception_batch_items** - Items de lotes (FK: reception_batch_id, product_id, brand_id, reception_item_id)
27. **reception_items** - Items de recepción (FK: reception_id, product_id, brand_id, purchase_item_id, output_product_id)
28. **receptions** - Recepciones (FK: purchase_id, product_output_id, location_id, created_by)
29. **recipe_products** - Productos en recetas (FK: recipe_id, product_id, brand_id)
30. **role_permission** - Relación roles-permisos (FK: role_id, permission_id)
31. **roles** - Roles del sistema
32. **supplier_contacts** - Contactos de proveedores (FK: supplier_id)
33. **suppliers** - Proveedores (FK: created_by)
34. **technical_order_farms** - Relación órdenes-fincas (FK: technical_order_id, location_id)
35. **technical_order_products** - Productos en órdenes (FK: technical_order_id, product_id, brand_id)
36. **technical_orders** - Órdenes técnicas (FK: recipe_id, created_by, approved_by)
37. **technical_recipes** - Recetas técnicas (FK: created_by)
38. **users** - Usuarios del sistema (FK: role_id)

---

## 🔄 Orden de Migración Propuesto

**IMPORTANTE:** El orden debe respetar las dependencias de claves foráneas.

### Fase 1: Tablas Base (Sin dependencias)
1. `base_units`
2. `permissions`
3. `roles`
4. `output_types`

### Fase 2: Tablas con 1 Nivel de Dependencia
5. `role_permission` (depende: roles, permissions)
6. `brands`
7. `users` (depende: roles)

### Fase 3: Tablas Maestras
8. `suppliers` (depende: users)
9. `supplier_contacts` (depende: suppliers)
10. `locations` (depende: users)
11. `farm_lots` (depende: locations, users)
12. `packaging_units` (depende: users)
13. `products` (depende: brands, users)
14. `product_packaging_units` (depende: products, packaging_units)
15. `technical_recipes` (depende: users)

### Fase 4: Transacciones Principales
16. `purchases` (depende: suppliers, locations, users)
17. `purchase_items` (depende: purchases, products, brands)
18. `purchase_attachments` (depende: purchases)
19. `product_outputs` (depende: locations, output_types, users)
20. `output_products` (depende: product_outputs, products, brands)
21. `output_farm_lots` (depende: product_outputs, farm_lots)

### Fase 5: Recepciones
22. `receptions` (depende: purchases, product_outputs, locations, users)
23. `reception_items` (depende: receptions, products, brands, purchase_items, output_products)
24. `reception_batches` (depende: receptions, locations, users)
25. `reception_batch_items` (depende: reception_batches, products, brands, reception_items)
26. `reception_batch_attachments` (depende: reception_batches)

### Fase 6: Inventario
27. `inventory` (depende: products, brands, locations, receptions, purchases)
28. `inventory_movements` (depende: products, brands, locations, users)
29. `alerts` (depende: múltiples)

### Fase 7: Procesos Técnicos
30. `recipe_products` (depende: technical_recipes, products, brands)
31. `technical_orders` (depende: technical_recipes, users)
32. `technical_order_products` (depende: technical_orders, products, brands)
33. `technical_order_farms` (depende: technical_orders, locations)

### Fase 8: Aplicaciones
34. `applications` (depende: locations, farm_lots, users)
35. `application_products` (depende: applications, products, brands, receptions)

### Fase 9: Tablas Técnicas
36. `cache`
37. `cache_locks`
38. `personal_access_tokens`

---

## 🛠️ Proceso de Migración (POR TABLA)

Para cada tabla, el proceso sería:

### Paso 1: Backup
```bash
mysqldump -u usuario -p agriflor > backup_agriflor_$(date +%Y%m%d_%H%M%S).sql
```

### Paso 2: Crear Nueva Tabla con INTEGER
```php
Schema::create('tabla_nueva', function (Blueprint $table) {
    $table->id(); // unsignedBigInteger auto-increment
    // ... resto de campos
    // FKs como unsignedBigInteger
});
```

### Paso 3: Migrar Datos
```php
// Crear mapeo UUID → INTEGER
$mapping = [];
DB::table('tabla_vieja')->orderBy('created_at')->chunk(1000, function($records) use (&$mapping) {
    foreach ($records as $record) {
        $newId = DB::table('tabla_nueva')->insertGetId([
            // campos sin UUID
        ]);
        $mapping[$record->id] = $newId;
    }
});
```

### Paso 4: Actualizar Referencias
```php
// Actualizar FKs en tablas dependientes usando el mapeo
```

### Paso 5: Eliminar Tabla Vieja y Renombrar
```sql
DROP TABLE tabla_vieja;
RENAME TABLE tabla_nueva TO tabla_vieja;
```

---

## 💻 Cambios Requeridos en el Código

### Backend (Laravel)

1. **Modelos (37 archivos)**
   - Eliminar `use HasUuids;`
   - Cambiar `protected $keyType = 'string';` → `protected $keyType = 'int';`
   - Actualizar `$casts` de UUIDs a integers

2. **Migraciones (48 archivos)**
   - Cambiar `$table->uuid('id')->primary()` → `$table->id()`
   - Cambiar `$table->uuid('campo_id')` → `$table->unsignedBigInteger('campo_id')`

3. **Controllers (20+ archivos)**
   - Las validaciones de UUIDs → validaciones de integers
   - `'exists:tabla,id'` funciona igual

4. **Request Validators**
   - Cambiar validaciones `uuid` → `integer`

### Frontend (TypeScript/React)

1. **Types (20+ archivos)**
   - Cambiar `id: string` → `id: number`
   - Todas las referencias de IDs

2. **API Calls**
   - Los endpoints siguen funcionando igual
   - Las respuestas cambian de string → number

3. **Components (50+ archivos)**
   - Actualizar comparaciones de IDs
   - Actualizar keys en loops

---

## 📊 Estimación de Esfuerzo

| Tarea | Archivos Afectados | Tiempo Estimado |
|-------|-------------------|-----------------|
| Análisis detallado de dependencias | - | 8 horas |
| Crear migraciones nuevas | 48 archivos | 40 horas |
| Actualizar modelos | 37 archivos | 15 horas |
| Actualizar controllers | 20 archivos | 20 horas |
| Actualizar validators | 15 archivos | 10 horas |
| Actualizar frontend types | 20 archivos | 10 horas |
| Actualizar componentes | 50+ archivos | 30 horas |
| Testing completo | - | 40 horas |
| Contingencias | - | 20 horas |
| **TOTAL** | **200+ archivos** | **~193 horas (~24 días)** |

---

## ⚠️ Riesgos Identificados

### Alto Riesgo
1. **Pérdida de datos** durante la migración
2. **Inconsistencias** en las relaciones
3. **Tiempo de inactividad** prolongado
4. **Bugs** en producción post-migración

### Medio Riesgo
1. **Performance** durante la migración (tablas grandes)
2. **Rollback** complicado si algo falla
3. **Integraciones externas** que usen UUIDs

### Bajo Riesgo
1. Problemas de **validación** frontend
2. **URLs** con IDs expuestos

---

## 🎯 Recomendación Final

### ❌ NO SE RECOMIENDA REALIZAR ESTA MIGRACIÓN

**Razones:**

1. **Costo/Beneficio Negativo:**
   - Esfuerzo: ~24 días-persona
   - Beneficio: Mínimo (ahorro marginal de almacenamiento)
   - Riesgo: Alto (pérdida de datos, bugs)

2. **El Sistema Actual Funciona Correctamente:**
   - UUIDs son apropiados para este tipo de sistema
   - No hay problemas de performance reportados
   - La escalabilidad está garantizada

3. **Alternativas Mejores:**
   - Optimizar índices existentes
   - Implementar caché
   - Optimizar queries

4. **Cuándo SÍ Considerarlo:**
   - Si hay problemas de performance comprobados
   - Si se necesita integración con sistema legacy que requiere integers
   - Si el tamaño de BD es crítico (actualmente no es el caso)

---

## 📝 Notas Adicionales

### Si Aun Así se Decide Migrar

1. **Ejecutar en ambiente de desarrollo primero**
2. **Backup triple** (base de datos, archivos, código)
3. **Plan de rollback documentado**
4. **Testing exhaustivo** (mínimo 2 semanas)
5. **Migración en ventana de mantenimiento** (fines de semana)
6. **Equipo completo disponible** durante la migración
7. **Monitoreo 24/7** post-migración (primera semana)

### Documentación de Referencia

- Laravel Migrations: https://laravel.com/docs/migrations
- UUID vs Integer: https://www.percona.com/blog/2019/11/22/uuids-are-popular-but-bad-for-performance-lets-discuss/
- Database Migration Best Practices: https://www.postgresql.org/docs/current/sql-altertable.html

---

**Documento creado:** 2026-01-19
**Autor:** Sistema de Gestión AgriFlor
**Versión:** 1.0
**Estado:** NO EJECUTAR - SOLO REFERENCIA
