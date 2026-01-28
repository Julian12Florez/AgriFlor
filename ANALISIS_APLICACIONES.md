# Analisis: Registro de Aplicaciones en Campo
**Fecha**: 2026-01-21
**Solicitud**: "Analizar movimientos por aplicacion, cuando se haga recepcion de salida descontar stock y almacenar registro de lo aplicado y donde se aplico"

---

## Entendimiento del Sistema Actual

### Modulos y sus Propositos

| Modulo | Proposito | Estado |
|--------|-----------|--------|
| **ProductOutput** | Movimientos entre ubicaciones (transferencias) | ✅ Funciona |
| **Reception** | Recepcionar compras Y salidas/transferencias | ✅ Funciona |
| **Application** | Registrar aplicaciones directas en campo | ✅ Funciona (independiente) |
| **output_farm_lots** | Asociar lotes destino a salidas tipo consumo | ⚠️ Solo relacion basica |

### Flujos Actuales Documentados (FLUJO_INVENTARIO_POR_TIPO.md)

```
COMPRA:
Proveedor → Reception → ENTRY en destino ✅

TRANSFERENCIA:
Ubicacion A → Salida → Reception → EXIT origen + ENTRY destino ✅

CONSUMO:
Ubicacion → Salida(consumo) → Reception → Solo EXIT origen ✅
                                         → Asocia farm_lots ✅
                                         → NO registra detalles de aplicacion ⚠️
```

---

## Analisis del Requerimiento

### Lo que FUNCIONA actualmente para consumo:
1. ✅ Crear salida tipo "consumo" con lotes destino
2. ✅ Aprobar salida (valida stock)
3. ✅ Recepcionar (descuenta inventario FIFO)
4. ✅ Asociar farm_lots donde se consumira
5. ✅ Crear InventoryMovement tipo EXIT

### Lo que FALTA para registro completo de aplicacion:

| Campo | Existe en output_farm_lots? | Existe en Application? |
|-------|----------------------------|------------------------|
| Cantidad por lote | ❌ No | ✅ Si (via ApplicationProduct) |
| Area aplicada | ❌ No | ✅ Si |
| Fecha aplicacion real | ❌ No | ✅ Si |
| Usuario que aplico | ❌ No | ✅ Si |
| Dosis utilizada | ❌ No | ✅ Si |
| Observaciones | ❌ No | ✅ Si |
| Trazabilidad recepcion | ❌ No | ✅ Si (reception_id) |

---

## Opciones de Solucion

### Opcion A: Extender output_farm_lots
Agregar campos de aplicacion a la tabla pivote existente.

**Pros:**
- Cambio minimo en estructura
- Mantiene relacion existente

**Contras:**
- Mezcla conceptos (salida vs aplicacion)
- Duplica funcionalidad de Application
- Menos flexible para multiples aplicaciones por lote

### Opcion B: Integrar con modulo Application (RECOMENDADA)
Crear Application automaticamente al recepcionar consumo, o permitir registro manual post-recepcion.

**Pros:**
- Reutiliza modulo existente bien diseñado
- Historial completo de aplicaciones por lote
- Reportes unificados de aplicaciones
- Ya tiene todos los campos necesarios

**Contras:**
- Requiere integracion entre modulos
- Decidir si es automatico o manual

### Opcion C: Nueva tabla de registro de aplicaciones
Crear tabla separada para aplicaciones desde salidas.

**Pros:**
- Separacion clara de conceptos

**Contras:**
- Duplicacion de funcionalidad
- Dos sistemas paralelos de aplicaciones

---

## Solucion Propuesta (Opcion B)

### Flujo Propuesto para Consumo

```
FLUJO ACTUAL:
Salida(consumo) → Reception → EXIT → output_farm_lots (solo IDs)
                                     ↓
                              ¿Cuanto? ¿Donde exactamente? ¿Cuando?
                                     ❌ NO REGISTRADO

FLUJO PROPUESTO:
Salida(consumo) → Reception → EXIT → Application + ApplicationProducts
                                     ↓
                              ✅ Cantidad por lote
                              ✅ Area aplicada
                              ✅ Fecha real
                              ✅ Dosis
                              ✅ Trazabilidad completa
```

### Cambios Requeridos

#### 1. Agregar relacion Application ↔ ProductOutput

**Migracion:**
```php
// add_product_output_id_to_applications_table.php
Schema::table('applications', function (Blueprint $table) {
    $table->uuid('product_output_id')->nullable()->after('farm_lot_id');
    $table->foreign('product_output_id')
          ->references('id')->on('product_outputs')
          ->onDelete('set null');
    $table->index('product_output_id');
});
```

**Modelo Application.php:**
```php
protected $fillable = [
    // ... campos existentes
    'product_output_id',
];

public function productOutput()
{
    return $this->belongsTo(ProductOutput::class, 'product_output_id');
}
```

**Modelo ProductOutput.php:**
```php
public function applications()
{
    return $this->hasMany(Application::class, 'product_output_id');
}
```

#### 2. Crear endpoint para registrar aplicacion desde salida

**Ruta (routes/api.php):**
```php
Route::post('/outputs/{id}/register-application', [ProductOutputController::class, 'registerApplication']);
```

**ProductOutputController.php - Nuevo metodo:**
```php
/**
 * Registrar aplicacion de productos desde una salida tipo consumo
 *
 * Este endpoint permite registrar los detalles de la aplicacion
 * despues de que los productos han sido recepcionados en el destino.
 *
 * POST /api/outputs/{id}/register-application
 */
public function registerApplication(Request $request, string $id): JsonResponse
{
    $request->validate([
        'farm_lot_id' => 'required|uuid|exists:farm_lots,id',
        'application_date' => 'required|date',
        'applied_by' => 'required|uuid|exists:users,id',
        'application_type' => 'nullable|string|max:100',
        'products' => 'required|array|min:1',
        'products.*.product_id' => 'required|uuid',
        'products.*.brand_id' => 'required|uuid',
        'products.*.quantity' => 'required|numeric|min:0.01',
        'products.*.unit' => 'required|string',
        'products.*.applied_area' => 'nullable|numeric|min:0',
        'products.*.area_unit' => 'nullable|string',
        'products.*.dosage' => 'nullable|numeric|min:0',
        'products.*.dosage_unit' => 'nullable|string',
        'products.*.observations' => 'nullable|string',
        'observations' => 'nullable|string',
    ]);

    try {
        DB::beginTransaction();

        $output = ProductOutput::with(['outputType', 'farmLots', 'reception'])
            ->findOrFail($id);

        // Validar que sea tipo consumo
        if ($output->outputType?->code !== 'consumption') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden registrar aplicaciones para salidas tipo consumo'
            ], 400);
        }

        // Validar que la salida este recepcionada (partial o completed)
        if (!in_array($output->status, ['partial', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => 'La salida debe tener recepcion iniciada para registrar aplicaciones'
            ], 400);
        }

        // Validar que el lote pertenece a esta salida
        if (!$output->farmLots->contains('id', $request->farm_lot_id)) {
            return response()->json([
                'success' => false,
                'message' => 'El lote seleccionado no esta asociado a esta salida'
            ], 400);
        }

        // Crear Application
        $application = Application::create([
            'application_number' => Application::generateApplicationNumber(),
            'origin_location_id' => $output->origin_location_id,
            'farm_lot_id' => $request->farm_lot_id,
            'product_output_id' => $output->id, // Relacion con la salida
            'application_date' => $request->application_date,
            'applied_by' => $request->applied_by,
            'status' => 'approved', // Ya se desconto el inventario en la recepcion
            'application_type' => $request->application_type ?? 'consumo_salida',
            'observations' => $request->observations,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Crear ApplicationProducts
        foreach ($request->products as $productData) {
            ApplicationProduct::create([
                'application_id' => $application->id,
                'product_id' => $productData['product_id'],
                'brand_id' => $productData['brand_id'],
                'quantity' => $productData['quantity'],
                'unit' => $productData['unit'],
                'applied_area' => $productData['applied_area'] ?? null,
                'area_unit' => $productData['area_unit'] ?? 'ha',
                'dosage' => $productData['dosage'] ?? null,
                'dosage_unit' => $productData['dosage_unit'] ?? null,
                'reception_id' => $output->reception?->id, // Trazabilidad
                'observations' => $productData['observations'] ?? null,
            ]);
        }

        // NOTA: NO se crea InventoryMovement porque el inventario
        // ya fue descontado en la recepcion de la salida

        DB::commit();

        $application->load(['applicationProducts.product', 'applicationProducts.brand', 'farmLot']);

        return response()->json([
            'success' => true,
            'message' => 'Aplicacion registrada exitosamente',
            'data' => [
                'application' => $application,
                'output' => $output->fresh(['applications']),
            ]
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error al registrar aplicacion desde salida', [
            'output_id' => $id,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error al registrar aplicacion: ' . $e->getMessage()
        ], 500);
    }
}
```

#### 3. Agregar endpoint para consultar aplicaciones de una salida

```php
/**
 * Obtener aplicaciones registradas para una salida
 *
 * GET /api/outputs/{id}/applications
 */
public function getApplications(string $id): JsonResponse
{
    $output = ProductOutput::with([
        'applications.applicationProducts.product',
        'applications.applicationProducts.brand',
        'applications.farmLot',
        'applications.appliedByUser'
    ])->findOrFail($id);

    return response()->json([
        'success' => true,
        'data' => $output->applications
    ]);
}
```

#### 4. Opcional: Agregar campos a output_farm_lots para tracking

Si se quiere mantener un resumen rapido en la tabla pivote:

```php
// add_application_tracking_to_output_farm_lots.php
Schema::table('output_farm_lots', function (Blueprint $table) {
    $table->decimal('total_quantity_applied', 10, 2)->nullable()
          ->comment('Suma de cantidades aplicadas en este lote');
    $table->date('last_application_date')->nullable()
          ->comment('Fecha de la ultima aplicacion');
    $table->uuid('last_applied_by')->nullable();

    $table->foreign('last_applied_by')
          ->references('id')->on('users')
          ->onDelete('set null');
});
```

---

## Flujo de Usuario Propuesto

### Escenario: Aplicar fungicida en lotes de finca

```
1. Usuario crea salida tipo "Consumo"
   - Origen: Bodega Villa Antonella
   - Destino: Finca Julian
   - Lotes: Lote 1, Lote 2, Lote 3
   - Productos: 60L Fungicida Nativo

2. Supervisor aprueba salida
   - Sistema valida stock disponible ✅

3. Usuario registra recepcion
   - Confirma llegada de 60L
   - Sistema crea EXIT movement ✅
   - Stock en bodega: -60L ✅

4. Usuario registra aplicacion (NUEVO)
   POST /api/outputs/{id}/register-application
   {
     "farm_lot_id": "uuid-lote-1",
     "application_date": "2026-01-21",
     "applied_by": "uuid-usuario",
     "products": [{
       "product_id": "uuid-fungicida",
       "brand_id": "uuid-nativo",
       "quantity": 25,
       "unit": "L",
       "applied_area": 5,
       "area_unit": "ha",
       "dosage": 5,
       "dosage_unit": "L/ha"
     }],
     "observations": "Aplicacion preventiva por clima humedo"
   }

5. Usuario registra otra aplicacion para otro lote
   POST /api/outputs/{id}/register-application
   {
     "farm_lot_id": "uuid-lote-2",
     ...
   }

6. Consultar historial de aplicaciones
   GET /api/outputs/{id}/applications
   GET /api/applications?farm_lot_id=uuid-lote-1
   GET /api/farm-lots/{id}/applications
```

---

## Resumen de Cambios Necesarios

### Backend

| Archivo | Cambio | Prioridad |
|---------|--------|-----------|
| Nueva migracion | Agregar `product_output_id` a `applications` | ALTA |
| `Application.php` | Agregar relacion `productOutput()` | ALTA |
| `ProductOutput.php` | Agregar relacion `applications()` | ALTA |
| `ProductOutputController.php` | Nuevo metodo `registerApplication()` | ALTA |
| `ProductOutputController.php` | Nuevo metodo `getApplications()` | MEDIA |
| `routes/api.php` | Agregar rutas para nuevos endpoints | ALTA |
| Nueva migracion (opcional) | Campos tracking en `output_farm_lots` | BAJA |

### Frontend

| Archivo | Cambio | Prioridad |
|---------|--------|-----------|
| `outputsApi` | Agregar `registerApplication()`, `getApplications()` | ALTA |
| `Outputs.tsx` o nuevo componente | UI para registrar aplicaciones | ALTA |
| Nuevo componente | Modal/Drawer para detalles de aplicacion | MEDIA |

---

## Consideraciones Importantes

### 1. El inventario NO se descuenta dos veces
- El descuento ocurre en Reception (al recepcionar la salida)
- El registro de Application es solo para TRAZABILIDAD
- Application.status = 'approved' sin crear InventoryMovement adicional

### 2. Multiples aplicaciones por salida
- Una salida puede tener multiples lotes destino
- Cada lote puede tener multiples aplicaciones (en diferentes fechas)
- El total aplicado NO puede exceder lo recepcionado

### 3. Validaciones necesarias
- Suma de cantidades aplicadas <= cantidad recepcionada
- Lote debe pertenecer a la salida
- Solo salidas tipo "consumo" pueden registrar aplicaciones
- Salida debe tener recepcion (parcial o completa)

### 4. Reportes
- Historial de aplicaciones por lote
- Historial de aplicaciones por producto
- Consumo de productos por periodo
- Trazabilidad: Salida → Recepcion → Aplicacion

---

## Proximos Pasos

1. Ejecutar `/fix` para implementar los cambios de backend
2. Ejecutar `/fix frontend` para implementar UI de registro
3. Ejecutar `/test aplicaciones` para verificar flujo completo
