# 📊 Informes de Movimientos de Inventario

## ✅ SÍ, es 100% POSIBLE generar informes entre rangos de fechas

Con el registro completo de movimientos implementado, ahora puedes generar informes detallados de TODOS los movimientos de productos entre cualquier rango de fechas.

---

## 🎯 Endpoints Disponibles

### 1️⃣ **Endpoint Simple: Lista de Movimientos**

**URL:** `GET /api/inventory/movements`

**Descripción:** Lista movimientos con paginación

**Parámetros:**
```
?start_date=2024-01-01          (opcional)
&end_date=2024-12-31            (opcional)
&product_id=uuid                (opcional)
&location_id=uuid               (opcional)
&type=entry|exit|transfer       (opcional)
&search=texto                   (opcional)
&per_page=50                    (opcional, default: 15)
```

**Respuesta:**
```json
{
  "data": [
    {
      "id": "uuid",
      "type": "entry",
      "quantity": 300.00,
      "unit": "kg",
      "unitPrice": 2500.00,
      "totalPrice": 750000.00,
      "product": {
        "id": "uuid",
        "name": "Fertilizante NPK",
        "productCode": "FER-001"
      },
      "brand": {
        "id": "uuid",
        "name": "Yara"
      },
      "location": {
        "id": "uuid",
        "name": "Bodega Central"
      },
      "user": {
        "id": "uuid",
        "name": "Juan Pérez"
      },
      "relatedDocumentId": "reception-uuid",
      "relatedDocumentType": "App\\Models\\Reception",
      "observations": "Recepción lote #1 - good - Compra",
      "createdAt": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

**Uso:**
- ✅ Para listas paginadas
- ✅ Para mostrar en tablas
- ✅ Para búsquedas específicas

---

### 2️⃣ **Endpoint Consolidado: Reporte Completo (NUEVO)**

**URL:** `GET /api/inventory/movements/report`

**Descripción:** Reporte consolidado con estadísticas, agrupaciones y resúmenes ejecutivos

**Parámetros:**
```
?start_date=2024-01-01          (opcional)
&end_date=2024-12-31            (opcional)
&product_id=uuid                (opcional)
&location_id=uuid               (opcional)
&type=entry|exit|transfer       (opcional)
```

**Respuesta Completa:**
```json
{
  "success": true,
  "filters": {
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "location_id": null,
    "product_id": null,
    "type": null
  },
  "movements": [
    {
      "id": "uuid-movement-001",
      "date": "2024-01-15 10:30:00",
      "type": "entry",
      "product_id": "uuid-product",
      "product_name": "Fertilizante NPK 15-15-15",
      "product_code": "FER-001",
      "category": "fertilizante",
      "brand_name": "Yara",
      "location_id": "uuid-location",
      "location_name": "Bodega Central",
      "location_type": "warehouse",
      "quantity": 300.00,
      "unit": "kg",
      "unit_price": 2500.00,
      "total_price": 750000.00,
      "responsible_user": "Juan Pérez",
      "related_document_id": "uuid-reception",
      "related_document_type": "App\\Models\\Reception",
      "observations": "Recepción lote #1 - good - Compra"
    },
    {
      "id": "uuid-movement-002",
      "date": "2024-01-25 09:00:00",
      "type": "exit",
      "product_id": "uuid-product",
      "product_name": "Fertilizante NPK 15-15-15",
      "product_code": "FER-001",
      "category": "fertilizante",
      "brand_name": "Yara",
      "location_id": "uuid-location-bodega",
      "location_name": "Bodega Central",
      "location_type": "warehouse",
      "quantity": 200.00,
      "unit": "kg",
      "unit_price": 2500.00,
      "total_price": 500000.00,
      "responsible_user": "María García",
      "related_document_id": "uuid-reception",
      "related_document_type": "App\\Models\\Reception",
      "observations": "Salida confirmada en recepción lote #1 - Transferencia a Finca La Esperanza"
    }
  ],
  "summary": {
    "total_movements": 45,
    "total_entries": 1500.00,
    "total_exits": 300.00,
    "total_value_entries": 3750000.00,
    "total_value_exits": 750000.00,
    "net_change": 1200.00,
    "net_value_change": 3000000.00
  },
  "by_product": [
    {
      "product_id": "uuid-product",
      "product_name": "Fertilizante NPK 15-15-15",
      "product_code": "FER-001",
      "category": "fertilizante",
      "total_entries": 500.00,
      "total_exits": 200.00,
      "total_value_entries": 1250000.00,
      "total_value_exits": 500000.00,
      "movements_count": 3
    },
    {
      "product_id": "uuid-product-2",
      "product_name": "Glifosato 48%",
      "product_code": "HER-001",
      "category": "herbicida",
      "total_entries": 100.00,
      "total_exits": 50.00,
      "total_value_entries": 4500000.00,
      "total_value_exits": 2250000.00,
      "movements_count": 5
    }
  ],
  "by_location": [
    {
      "location_id": "uuid-location-bodega",
      "location_name": "Bodega Central",
      "location_type": "warehouse",
      "total_entries": 1000.00,
      "total_exits": 200.00,
      "total_value_entries": 2500000.00,
      "total_value_exits": 500000.00,
      "movements_count": 15
    },
    {
      "location_id": "uuid-location-finca",
      "location_name": "Finca La Esperanza",
      "location_type": "farm",
      "total_entries": 500.00,
      "total_exits": 100.00,
      "total_value_entries": 1250000.00,
      "total_value_exits": 250000.00,
      "movements_count": 8
    }
  ],
  "by_type": {
    "entry": {
      "count": 25,
      "total_quantity": 1500.00,
      "total_value": 3750000.00
    },
    "exit": {
      "count": 15,
      "total_quantity": 300.00,
      "total_value": 750000.00
    },
    "transfer": {
      "count": 5,
      "total_quantity": 100.00,
      "total_value": 250000.00
    },
    "application": {
      "count": 0,
      "total_quantity": 0,
      "total_value": 0
    }
  },
  "by_day": [
    {
      "date": "2024-01-15",
      "total_entries": 300.00,
      "total_exits": 0,
      "total_value_entries": 750000.00,
      "total_value_exits": 0,
      "movements_count": 2
    },
    {
      "date": "2024-01-20",
      "total_entries": 200.00,
      "total_exits": 0,
      "total_value_entries": 500000.00,
      "total_value_exits": 0,
      "movements_count": 1
    },
    {
      "date": "2024-01-25",
      "total_entries": 200.00,
      "total_exits": 200.00,
      "total_value_entries": 500000.00,
      "total_value_exits": 500000.00,
      "movements_count": 3
    }
  ]
}
```

**Uso:**
- ✅ Para reportes ejecutivos
- ✅ Para análisis de tendencias
- ✅ Para auditorías
- ✅ Para gráficos y dashboards
- ✅ Para exportar a Excel/PDF

---

## 📋 Casos de Uso

### 1. **Informe Mensual de Movimientos**

```bash
GET /api/inventory/movements/report?start_date=2024-01-01&end_date=2024-01-31
```

**Retorna:**
- Todos los movimientos del mes de enero
- Resumen de entradas vs salidas
- Agrupación por producto
- Agrupación por ubicación
- Movimientos día a día

---

### 2. **Informe de un Producto Específico**

```bash
GET /api/inventory/movements/report?product_id=uuid-fertilizante&start_date=2024-01-01&end_date=2024-12-31
```

**Retorna:**
- Todos los movimientos del fertilizante en el año
- Total de entradas y salidas
- Valor total movido
- Ubicaciones donde hubo movimientos
- Distribución por día

---

### 3. **Informe de una Ubicación Específica**

```bash
GET /api/inventory/movements/report?location_id=uuid-bodega&start_date=2024-01-01&end_date=2024-03-31
```

**Retorna:**
- Todos los movimientos en Bodega Central (Q1)
- Productos que entraron/salieron
- Valores totales
- Tipos de movimientos
- Timeline diario

---

### 4. **Solo Entradas en Rango de Fechas**

```bash
GET /api/inventory/movements/report?type=entry&start_date=2024-01-01&end_date=2024-06-30
```

**Retorna:**
- Solo movimientos de entrada (recepciones)
- Productos recibidos
- Valor total de entradas
- Ubicaciones de destino
- Distribución temporal

---

### 5. **Solo Salidas en Rango de Fechas**

```bash
GET /api/inventory/movements/report?type=exit&start_date=2024-01-01&end_date=2024-06-30
```

**Retorna:**
- Solo movimientos de salida
- Productos despachados
- Valor total de salidas
- Ubicaciones de origen
- Distribución temporal

---

## 📊 Interpretación de Datos

### **Summary (Resumen Global)**

```json
"summary": {
  "total_movements": 45,           // Total de movimientos en el periodo
  "total_entries": 1500.00,        // Suma de todas las entradas (kg/L/unidades)
  "total_exits": 300.00,           // Suma de todas las salidas
  "total_value_entries": 3750000.00, // Valor total de entradas ($)
  "total_value_exits": 750000.00,  // Valor total de salidas ($)
  "net_change": 1200.00,           // Cambio neto en cantidad (1500 - 300)
  "net_value_change": 3000000.00   // Cambio neto en valor ($)
}
```

**Interpretación:**
- **net_change > 0**: Aumentó el inventario
- **net_change < 0**: Disminuyó el inventario
- **net_value_change**: Valor agregado/perdido en el periodo

---

### **By Product (Por Producto)**

```json
{
  "product_name": "Fertilizante NPK",
  "total_entries": 500.00,         // Total recibido
  "total_exits": 200.00,           // Total despachado
  "total_value_entries": 1250000.00, // Valor recibido
  "total_value_exits": 500000.00,  // Valor despachado
  "movements_count": 3             // Número de movimientos
}
```

**Uso:**
- Identificar productos con más movimiento
- Calcular rotación de inventario
- Detectar productos con alta demanda

---

### **By Location (Por Ubicación)**

```json
{
  "location_name": "Bodega Central",
  "location_type": "warehouse",
  "total_entries": 1000.00,        // Total que entró a bodega
  "total_exits": 200.00,           // Total que salió de bodega
  "total_value_entries": 2500000.00,
  "total_value_exits": 500000.00,
  "movements_count": 15
}
```

**Uso:**
- Identificar ubicaciones con más actividad
- Balance entre entradas y salidas por ubicación
- Planificación de capacidad

---

### **By Type (Por Tipo de Movimiento)**

```json
"by_type": {
  "entry": {
    "count": 25,                   // 25 recepciones
    "total_quantity": 1500.00,     // 1,500 kg recibidos
    "total_value": 3750000.00      // $3.75M en recepciones
  },
  "exit": {
    "count": 15,                   // 15 salidas
    "total_quantity": 300.00,      // 300 kg despachados
    "total_value": 750000.00       // $750K en salidas
  }
}
```

**Uso:**
- Balance global de operaciones
- Proporción entre entradas y salidas
- Identificar tipo de operación dominante

---

### **By Day (Por Día)**

```json
{
  "date": "2024-01-15",
  "total_entries": 300.00,         // Entradas del día
  "total_exits": 0,                // Salidas del día
  "total_value_entries": 750000.00,
  "total_value_exits": 0,
  "movements_count": 2             // 2 movimientos ese día
}
```

**Uso:**
- Identificar días con más actividad
- Detectar patrones temporales
- Gráficos de tendencia
- Planificación de operaciones

---

## 💡 Ejemplos de Análisis

### **Ejemplo 1: Informe Trimestral**

**Request:**
```
GET /api/inventory/movements/report?start_date=2024-01-01&end_date=2024-03-31
```

**Análisis:**
```
Resumen Q1 2024:
- Total movimientos: 120
- Entradas: 5,000 kg por $12.5M
- Salidas: 2,000 kg por $5M
- Balance: +3,000 kg (+$7.5M)

Top 3 productos más movidos:
1. Fertilizante NPK: 45 movimientos
2. Glifosato 48%: 30 movimientos
3. Urea: 25 movimientos

Ubicación más activa: Bodega Central (75 movimientos)

Día con más movimientos: 2024-02-15 (12 movimientos)
```

---

### **Ejemplo 2: Auditoría de un Producto**

**Request:**
```
GET /api/inventory/movements/report?product_id=uuid-glifosato&start_date=2024-01-01&end_date=2024-12-31
```

**Análisis:**
```
Glifosato 48% - Año 2024:
- Movimientos totales: 35
- Entradas: 500 L por $22.5M
- Salidas: 250 L por $11.25M
- Stock neto: +250 L (+$11.25M)

Ubicaciones:
- Bodega Central: 20 movimientos
- Finca La Esperanza: 10 movimientos
- Finca El Porvenir: 5 movimientos

Meses con más entradas:
- Enero: 100 L
- Abril: 150 L
- Julio: 100 L

Meses con más salidas:
- Marzo: 80 L
- Junio: 90 L
- Septiembre: 80 L
```

---

### **Ejemplo 3: Balance de Ubicación**

**Request:**
```
GET /api/inventory/movements/report?location_id=uuid-bodega&start_date=2024-06-01&end_date=2024-06-30
```

**Análisis:**
```
Bodega Central - Junio 2024:
- Movimientos: 42
- Entradas: 1,200 kg/L por $3M
- Salidas: 800 kg/L por $2M
- Balance: +400 kg/L (+$1M)

Productos con más entradas:
1. Fertilizante NPK: 500 kg
2. Urea: 400 kg
3. Glifosato: 300 L

Productos con más salidas:
1. Fertilizante NPK: 350 kg
2. Glifosato: 250 L
3. Urea: 200 kg

Días más activos:
- 2024-06-10: 8 movimientos
- 2024-06-15: 7 movimientos
- 2024-06-25: 6 movimientos
```

---

## 📈 Uso para Gráficos

### Gráfico de Línea: Tendencia Diaria
```javascript
// Usar by_day array
const dates = data.by_day.map(d => d.date);
const entries = data.by_day.map(d => d.total_entries);
const exits = data.by_day.map(d => d.total_exits);

// Mostrar en gráfico de línea dual
```

### Gráfico de Barras: Por Producto
```javascript
// Usar by_product array
const products = data.by_product.map(p => p.product_name);
const values = data.by_product.map(p => p.total_value_entries - p.total_value_exits);

// Mostrar balance neto por producto
```

### Gráfico de Pastel: Por Tipo
```javascript
// Usar by_type object
const types = Object.keys(data.by_type);
const counts = types.map(t => data.by_type[t].count);

// Mostrar distribución de tipos de movimiento
```

### Gráfico de Barras Apiladas: Por Ubicación
```javascript
// Usar by_location array
const locations = data.by_location.map(l => l.location_name);
const entriesPerLocation = data.by_location.map(l => l.total_entries);
const exitsPerLocation = data.by_location.map(l => l.total_exits);

// Mostrar entradas vs salidas por ubicación
```

---

## 🔍 Campos Disponibles en Cada Movimiento

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | UUID | ID único del movimiento |
| `date` | DateTime | Fecha/hora exacta |
| `type` | String | entry/exit/transfer/application |
| `product_id` | UUID | ID del producto |
| `product_name` | String | Nombre del producto |
| `product_code` | String | Código del producto |
| `category` | String | Categoría del producto |
| `brand_name` | String | Nombre de la marca |
| `location_id` | UUID | ID de la ubicación |
| `location_name` | String | Nombre de la ubicación |
| `location_type` | String | warehouse/farm |
| `quantity` | Decimal | Cantidad movida |
| `unit` | String | kg/litros/unidades |
| `unit_price` | Decimal | Precio unitario |
| `total_price` | Decimal | Precio total |
| `responsible_user` | String | Usuario responsable |
| `related_document_id` | UUID | ID del documento |
| `related_document_type` | String | Tipo de documento |
| `observations` | String | Observaciones detalladas |

---

## ✅ Ventajas del Nuevo Endpoint

### vs Endpoint Simple `/movements`:

| Característica | Simple | Consolidado |
|----------------|--------|-------------|
| Lista movimientos | ✅ | ✅ |
| Paginación | ✅ | ❌ (todos los datos) |
| Resumen estadístico | ❌ | ✅ |
| Agrupación por producto | ❌ | ✅ |
| Agrupación por ubicación | ❌ | ✅ |
| Agrupación por tipo | ❌ | ✅ |
| Timeline diario | ❌ | ✅ |
| Cálculos de balance | ❌ | ✅ |
| Para tablas | ✅ | ❌ |
| Para reportes ejecutivos | ❌ | ✅ |
| Para gráficos | ❌ | ✅ |
| Para exportar | ⚠️ | ✅ |

**Recomendación:**
- **Simple**: Para mostrar en UI con paginación
- **Consolidado**: Para reportes, gráficos, análisis, auditorías

---

## 🎯 Conclusión

**SÍ**, con el registro completo de movimientos puedes:

✅ Generar informes de TODOS los productos entre rangos de fechas
✅ Filtrar por producto, ubicación, tipo de movimiento
✅ Obtener estadísticas consolidadas
✅ Ver agrupaciones por producto, ubicación, tipo, día
✅ Calcular balances y cambios netos
✅ Exportar datos para análisis externo
✅ Generar gráficos y dashboards
✅ Realizar auditorías completas

**El sistema está COMPLETO para informes y auditorías.**
