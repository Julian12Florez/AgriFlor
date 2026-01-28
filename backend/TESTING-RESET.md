# 🧪 Guía de Reset de Base de Datos para Pruebas

Este documento explica cómo limpiar la base de datos para realizar pruebas desde cero manteniendo los datos maestros actualizados.

## 📋 Scripts Disponibles

### 1. `reset-for-testing.sh` - Limpieza Rápida (Recomendado)

**Uso recomendado:** Cuando solo quieres limpiar transacciones pero mantener los datos maestros existentes.

```bash
./reset-for-testing.sh
```

**Lo que hace:**
- ✅ Limpia TODOS los datos transaccionales:
  - Compras y productos de compras
  - Inventario y movimientos de inventario
  - Salidas y productos de salida
  - Recepciones
  - Órdenes técnicas y recetas (si existen)
- ✅ Mantiene INTACTOS los datos maestros:
  - Usuarios y roles
  - Marcas
  - Unidades base y de empaque
  - Ubicaciones
  - Proveedores
  - Tipos de salida
  - Productos

**Ventaja:** Rápido y no afecta tus datos maestros configurados.

---

### 2. `reset-full.sh` - Reset Completo

**Uso recomendado:** Cuando los seeders maestros han sido actualizados y necesitas recargar TODO desde cero.

```bash
./reset-full.sh
```

**Lo que hace:**
- ⚠️ Ejecuta `migrate:fresh` (elimina y recrea TODAS las tablas)
- ✅ Recarga TODOS los seeders maestros actualizados
- ✅ Deja las transacciones limpias

**Advertencia:** Esto eliminará TODO incluyendo datos maestros. Solo úsalo cuando necesites recargar seeders actualizados.

---

## 🎯 Flujo de Trabajo Recomendado para Pruebas

### Opción A: Pruebas Diarias (Recomendada)

1. Ejecuta el limpiador rápido:
   ```bash
   ./reset-for-testing.sh
   ```

2. Realiza tus pruebas:
   - Crear compras
   - Recepcionar productos
   - Crear salidas (diferentes tipos)
   - Verificar inventario
   - Generar reportes

3. Cuando necesites empezar de nuevo, vuelve al paso 1

### Opción B: Actualización de Seeders

Si has actualizado los archivos de seeders maestros:

1. Ejecuta el reset completo:
   ```bash
   ./reset-full.sh
   ```

2. Continúa con tus pruebas como en Opción A

---

## 📊 Estado Después de Cada Script

### Después de `reset-for-testing.sh`:

| Categoría | Estado | Detalles |
|-----------|--------|----------|
| **Usuarios** | ✅ Preservados | Admin y usuarios de prueba |
| **Permisos/Roles** | ✅ Preservados | Configuración completa |
| **Marcas** | ✅ Preservadas | Todas las marcas |
| **Unidades** | ✅ Preservadas | Base y empaque |
| **Ubicaciones** | ✅ Preservadas | Bodegas y fincas |
| **Proveedores** | ✅ Preservados | Todos los proveedores |
| **Tipos de Salida** | ✅ Preservados | 4 tipos configurados |
| **Productos** | ✅ Preservados | Catálogo completo |
| **Compras** | ❌ Limpias | 0 registros |
| **Inventario** | ❌ Limpio | 0 registros |
| **Salidas** | ❌ Limpias | 0 registros |
| **Recepciones** | ❌ Limpias | 0 registros |

### Después de `reset-full.sh`:

| Categoría | Estado | Detalles |
|-----------|--------|----------|
| **TODO** | ✅ Recargado | Desde seeders actualizados |
| **Transacciones** | ❌ Limpias | 0 registros |

---

## 🛠️ Uso Manual (Sin Scripts)

Si prefieres ejecutar manualmente:

### Solo limpiar transacciones:
```bash
php artisan db:seed --class=CleanTransactionalDataSeeder
```

### Reset completo:
```bash
php artisan migrate:fresh --seed
```

---

## 📝 Datos Maestros Incluidos

### Usuarios
- **Admin**: Usuario administrador con todos los permisos

### Marcas
- Marcas de productos comunes en el sector agrícola

### Unidades Base
- kg, L, mL, unidades, etc.

### Unidades de Empaque
- Saco, bulto, caja, etc. con sus conversiones

### Ubicaciones
- Bodegas y fincas de ejemplo

### Proveedores
- Proveedores de insumos agrícolas

### Tipos de Salida
1. **Orden Técnica**: Salida asociada a orden técnica
2. **Solicitud Libre**: Salida libre sin orden
3. **Transferencia**: Entre ubicaciones
4. **Consumo**: Para lotes específicos (requiere lotes)

### Productos
- Catálogo de productos agrícolas con sus unidades de empaque

---

## ⚡ Tips para Pruebas Eficientes

1. **Usa `reset-for-testing.sh` regularmente** - Es rápido y seguro
2. **Solo usa `reset-full.sh` cuando sea necesario** - Es más lento
3. **Documenta tus casos de prueba** - Para reproducirlos fácilmente
4. **Prueba diferentes escenarios**:
   - Compras con diferentes precios
   - Múltiples recepciones
   - Diferentes tipos de salidas
   - Generación de reportes con datos variados

---

## 🆘 Solución de Problemas

### Error de permisos
```bash
chmod +x reset-for-testing.sh
chmod +x reset-full.sh
```

### Error de conexión a base de datos
Verifica tu `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=agriflor
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

### Seeders no se ejecutan
Verifica que existan:
```bash
ls -la database/seeders/
```

---

## 📞 Necesitas Ayuda?

Si encuentras algún problema o necesitas agregar más datos maestros a los seeders, actualiza los archivos en:
```
database/seeders/
├── BrandSeeder.php
├── LocationSeeder.php
├── PackagingUnitSeeder.php
├── ProductSeeder.php
├── SupplierSeeder.php
└── ...
```

Luego ejecuta `./reset-full.sh` para aplicar los cambios.
