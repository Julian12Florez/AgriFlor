# PLAN DE CORRECCI\u00d3N - SISTEMA AGRIFLOR
**Fecha:** 2026-01-19
**Basado en:** DIAGNOSTICO_COMPLETO.md
**Objetivo:** Roadmap paso a paso para corregir bugs cr\u00edticos y mejorar el sistema

---

## \ud83c\udfaf OBJETIVOS DEL PLAN

1. \u2705 Corregir bug de p\u00e9rdida de stock en transferencias
2. \u2705 Unificar flujo de inventario (eliminar OLD/NEW)
3. \u2705 Completar sistema de roles en frontend
4. \u2705 Simplificar c\u00f3digo complejo
5. \u2705 Implementar exportaci\u00f3n de reportes
6. \u26a0\ufe0f Evaluar migraci\u00f3n UUID \u2192 Integer (opcional)

---

## \ud83d\uddd3\ufe0f CRONOGRAMA ESTIMADO

| **Fase** | **Tarea** | **Duraci\u00f3n Estimada** | **Prioridad** |
|----------|-----------|------------------------|---------------|
| **Fase 1** | Investigaci\u00f3n y reproducci\u00f3n del bug de transferencias | 4-8 horas | \ud83d\udd34 URGENTE |
| **Fase 2** | Correcci\u00f3n del bug de transferencias | 4-6 horas | \ud83d\udd34 URGENTE |
| **Fase 3** | Unificaci\u00f3n del flujo de inventario | 8-12 horas | \ud83d\udfe1 ALTA |
| **Fase 4** | Sistema de roles en frontend | 12-16 horas | \ud83d\udfe1 ALTA |
| **Fase 5** | Simplificaci\u00f3n de c\u00f3digo | 16-24 horas | \ud83d\udfe0 MEDIA |
| **Fase 6** | Exportaci\u00f3n de reportes | 8-12 horas | \ud83d\udfe0 MEDIA |
| **Fase 7** | Migraci\u00f3n UUID (si aplica) | 40-80 horas | \ud83d\udfe2 BAJA |

**TOTAL (sin Fase 7):** 52-78 horas (6.5 - 10 d\u00edas laborales)

---

## \ud83d\udd34 FASE 1: INVESTIGACI\u00d3N DEL BUG DE TRANSFERENCIAS

### **Objetivo**
Reproducir y confirmar el bug exacto de p\u00e9rdida de stock en transferencias parciales.

### **Tareas**

#### **1.1. Preparar Ambiente de Testing**
```bash
# 1. Crear backup de base de datos
php artisan db:backup

# 2. Crear branch de investigaci\u00f3n
git checkout -b bugfix/transferencias-stock-perdido

# 3. Limpiar base de datos de prueba
php artisan migrate:fresh --seed
```

#### **1.2. Crear Datos de Prueba**

**Script de Seeder:**
```php
// database/seeders/TransferenciaBugSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Location, Product, Brand, Inventory, User};

class TransferenciaBugSeeder extends Seeder
{
    public function run()
    {
        // 1. Crear ubicaciones
        $bodegaA = Location::create([
            'name' => 'Bodega A - Test',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $fincaB = Location::create([
            'name' => 'Finca B - Test',
            'type' => 'farm',
            'status' => 'active',
        ]);

        // 2. Crear producto y marca
        $marca = Brand::create(['name' => 'Marca Test']);
        $producto = Product::create([
            'name' => 'Fertilizante Test',
            'category' => 'fertilizante',
            'base_unit' => 'kg',
        ]);

        // 3. Crear inventario inicial en Bodega A
        Inventory::create([
            'product_id' => $producto->id,
            'brand_id' => $marca->id,
            'location_id' => $bodegaA->id,
            'batch_number' => 'LOTE-TEST-001',
            'quantity' => 100, // 100 kg iniciales
            'unit' => 'kg',
            'expiration_date' => now()->addMonths(6),
            'unit_price' => 50,
            'total_value' => 5000,
            'status' => 'good',
        ]);

        echo "\n\u2705 Datos de prueba creados:\n";
        echo "   - Bodega A ID: {$bodegaA->id}\n";
        echo "   - Finca B ID: {$fincaB->id}\n";
        echo "   - Producto ID: {$producto->id}\n";
        echo "   - Inventario inicial: 100 kg\n\n";
    }
}
```

#### **1.3. Ejecutar Escenario de Prueba**

**Test Manual (Postman/cURL):**

```bash
# Paso 1: Login y obtener token
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@agriflor.com",
    "password": "password"
  }'

# Guardar TOKEN de respuesta

# Paso 2: Verificar inventario inicial
curl -X GET "http://localhost:8000/api/inventory?location_id=BODEGA_A_ID" \
  -H "Authorization: Bearer TOKEN"

# Resultado esperado: 100 kg en Bodega A

# Paso 3: Crear salida de transferencia (60 kg de 100 kg)
curl -X POST http://localhost:8000/api/product-outputs \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "output_date": "2026-01-19",
    "output_type_id": "OUTPUT_TYPE_TRANSFER_ID",
    "origin_location_id": "BODEGA_A_ID",
    "destination_location_id": "FINCA_B_ID",
    "products": [
      {
        "product_id": "PRODUCTO_ID",
        "brand_id": "MARCA_ID",
        "quantity_delivered": 60,
        "unit": "kg"
      }
    ]
  }'

# Guardar OUTPUT_ID de respuesta

# Paso 4: Aprobar la salida (si es necesario)
curl -X POST "http://localhost:8000/api/product-outputs/OUTPUT_ID/approve" \
  -H "Authorization: Bearer TOKEN"

# Paso 5: Crear recepci\u00f3n parcial (solo 60 kg)
curl -X POST http://localhost:8000/api/receptions/create-with-batch \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "source_id": "OUTPUT_ID",
    "source_type": "output",
    "reception_date": "2026-01-19",
    "received_by": "USER_ID",
    "items": [
      {
        "product_id": "PRODUCTO_ID",
        "quantity_received": 60,
        "condition": "good",
        "expiration_date": "2026-07-19"
      }
    ]
  }'

# Paso 6: Verificar inventario FINAL
curl -X GET "http://localhost:8000/api/inventory?location_id=BODEGA_A_ID" \
  -H "Authorization: Bearer TOKEN"

# \u2753 PREGUNTA CR\u00cdTICA:
# \u00bfCu\u00e1nto stock queda en Bodega A?
# ESPERADO: 40 kg (100 - 60)
# BUG: 0 kg (se perdi\u00f3 el remanente)
```

#### **1.4. Revisar Logs y Base de Datos**

```bash
# Ver logs de Laravel
tail -f storage/logs/laravel.log

# Verificar movimientos de inventario
php artisan tinker
>>> \App\Models\InventoryMovement::where('product_id', 'PRODUCTO_ID')->get();

# Verificar inventario actual
>>> \App\Models\Inventory::where('product_id', 'PRODUCTO_ID')->get();
```

#### **1.5. Analizar Resultados**

**Crear documento de investigaci\u00f3n:**
```markdown
# INVESTIGACION_BUG_TRANSFERENCIAS.md

## Escenario Ejecutado
- Inventario inicial: 100 kg
- Transferencia: 60 kg
- Recepci\u00f3n: 60 kg

## Resultados Obtenidos
- Inventario Bodega A: X kg (esperado: 40 kg)
- Inventario Finca B: Y kg (esperado: 60 kg)

## Movimientos Registrados
- EXIT: X kg
- ENTRY: Y kg

## Conclusi\u00f3n
[ ] Bug confirmado - Se pierde stock
[ ] Bug NO confirmado - Funciona correctamente
[ ] Comportamiento inesperado: [descripci\u00f3n]

## Causa Ra\u00edz
[Descripci\u00f3n de la causa exacta]
```

---

## \ud83d\udd34 FASE 2: CORRECCI\u00d3N DEL BUG DE TRANSFERENCIAS

### **Escenario A: Bug NO Confirmado (C\u00f3digo Est\u00e1 Correcto)**

Si el an\u00e1lisis demuestra que el c\u00f3digo funciona correctamente:

**Posibles causas alternativas:**
1. Error en frontend (cantidad enviada incorrecta)
2. Problema de UI (muestra datos desactualizados)
3. Error de usuario (no entiende el flujo de recepciones parciales)

**Acci\u00f3n:**
- Mejorar documentaci\u00f3n de usuario
- Agregar validaciones en frontend
- Mejorar mensajes de confirmaci\u00f3n
- Agregar tooltips explicativos

### **Escenario B: Bug Confirmado en reduceInventoryFIFO**

Si el bug est\u00e1 en la l\u00f3gica FIFO:

**Archivo:** `ReceptionController.php` - M\u00e9todo `reduceInventoryFIFO`

```php
// ANTES (con bug hipot\u00e9tico):
private function reduceInventoryFIFO(...): void
{
    // ... l\u00f3gica actual ...

    // \u274c BUG: Si hay un error en el c\u00e1lculo, se pierde stock
}

// DESPU\u00c9S (corregido):
private function reduceInventoryFIFO(
    string $productId,
    string $brandId,
    string $locationId,
    float $quantity
): void {
    \Log::info('Starting FIFO reduction', [
        'product_id' => $productId,
        'brand_id' => $brandId,
        'location_id' => $locationId,
        'quantity_to_reduce' => $quantity,
    ]);

    // Obtener lotes FIFO
    $inventoryBatches = Inventory::where('product_id', $productId)
        ->where('brand_id', $brandId)
        ->where('location_id', $locationId)
        ->where('status', 'good')
        ->where('quantity', '>', 0)
        ->orderBy('expiration_date', 'asc')
        ->orderBy('created_at', 'asc')
        ->get();

    // Validaci\u00f3n: verificar que hay suficiente stock ANTES de reducir
    $totalAvailable = $inventoryBatches->sum('quantity');
    if ($totalAvailable < $quantity) {
        throw new \Exception(
            "Inventario insuficiente. Disponible: {$totalAvailable}, Solicitado: {$quantity}"
        );
    }

    $remainingQuantity = $quantity;
    $processedBatches = [];

    foreach ($inventoryBatches as $batch) {
        if ($remainingQuantity <= 0) break;

        $quantityToTake = min($batch->quantity, $remainingQuantity);

        \Log::info('Processing batch', [
            'batch_id' => $batch->id,
            'batch_quantity' => $batch->quantity,
            'quantity_to_take' => $quantityToTake,
            'remaining_quantity' => $remainingQuantity,
        ]);

        // Reducir cantidad del lote
        $batch->quantity -= $quantityToTake;
        $batch->total_value = $batch->quantity * $batch->unit_price;

        if ($batch->quantity > 0) {
            $batch->save();
            \Log::info('Batch reduced and saved', [
                'batch_id' => $batch->id,
                'new_quantity' => $batch->quantity,
            ]);
        } else {
            $batch->delete();
            \Log::info('Batch fully consumed and deleted', [
                'batch_id' => $batch->id,
            ]);
        }

        $processedBatches[] = [
            'batch_id' => $batch->id,
            'quantity_taken' => $quantityToTake,
        ];

        $remainingQuantity -= $quantityToTake;
    }

    // Validaci\u00f3n final: asegurar que se proces\u00f3 toda la cantidad
    if ($remainingQuantity > 0) {
        \Log::error('FIFO reduction failed - insufficient inventory', [
            'remaining_quantity' => $remainingQuantity,
            'processed_batches' => $processedBatches,
        ]);

        throw new \Exception(
            "Error en reducci\u00f3n FIFO. Faltan {$remainingQuantity} unidades."
        );
    }

    \Log::info('FIFO reduction completed successfully', [
        'total_quantity_reduced' => $quantity,
        'processed_batches' => count($processedBatches),
    ]);
}
```

### **Escenario C: Bug en Validaci\u00f3n de Recepciones Parciales**

Si el problema es que se permite recibir m\u00e1s de lo enviado:

**Archivo:** `ReceptionController.php` - M\u00e9todo `createReceptionWithBatch`

```php
public function createReceptionWithBatch(Request $request): JsonResponse
{
    // ... validaciones existentes ...

    // \u2705 NUEVA VALIDACI\u00d3N: Verificar que no se reciba m\u00e1s de lo enviado
    foreach ($data['items'] as $itemData) {
        $receptionItem = $reception->receptionItems()
            ->where('product_id', $itemData['product_id'])
            ->first();

        if (!$receptionItem) {
            throw new \Exception("Producto no encontrado en la recepci\u00f3n");
        }

        // Validar que la cantidad recibida no exceda la cantidad pendiente
        $newTotalReceived = $receptionItem->quantity_received + $itemData['quantity_received'];

        if ($newTotalReceived > $receptionItem->quantity_expected) {
            throw new \Exception(
                "No se puede recibir m\u00e1s de lo esperado. " .
                "Esperado: {$receptionItem->quantity_expected}, " .
                "Ya recibido: {$receptionItem->quantity_received}, " .
                "Intentando recibir: {$itemData['quantity_received']}"
            );
        }
    }

    // ... resto del c\u00f3digo ...
}
```

### **Tests Automatizados**

```php
// tests/Feature/TransferenciaStockTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Inventory, Location, Product, Brand, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransferenciaStockTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function transferencia_parcial_mantiene_stock_en_origen()
    {
        // Arrange
        $user = User::factory()->create();
        $bodegaA = Location::factory()->warehouse()->create();
        $fincaB = Location::factory()->farm()->create();
        $producto = Product::factory()->create();
        $marca = Brand::factory()->create();

        Inventory::create([
            'product_id' => $producto->id,
            'brand_id' => $marca->id,
            'location_id' => $bodegaA->id,
            'batch_number' => 'TEST-001',
            'quantity' => 100,
            'unit' => 'kg',
            'unit_price' => 50,
            'total_value' => 5000,
            'status' => 'good',
        ]);

        // Act: Crear salida de 60 kg
        $output = $this->actingAs($user)
            ->postJson('/api/product-outputs', [
                'output_date' => now(),
                'output_type_id' => OutputType::where('code', 'transfer')->first()->id,
                'origin_location_id' => $bodegaA->id,
                'destination_location_id' => $fincaB->id,
                'products' => [
                    [
                        'product_id' => $producto->id,
                        'brand_id' => $marca->id,
                        'quantity_delivered' => 60,
                        'unit' => 'kg',
                    ],
                ],
            ])
            ->assertStatus(201)
            ->json('data');

        // Aprobar salida
        $this->postJson("/api/product-outputs/{$output['id']}/approve")
            ->assertStatus(200);

        // Recibir 60 kg
        $this->postJson('/api/receptions/create-with-batch', [
            'source_id' => $output['id'],
            'source_type' => 'output',
            'reception_date' => now(),
            'received_by' => $user->id,
            'items' => [
                [
                    'product_id' => $producto->id,
                    'quantity_received' => 60,
                    'condition' => 'good',
                ],
            ],
        ])->assertStatus(201);

        // Assert: Verificar inventarios
        $stockBodegaA = Inventory::where('location_id', $bodegaA->id)
            ->where('product_id', $producto->id)
            ->sum('quantity');

        $stockFincaB = Inventory::where('location_id', $fincaB->id)
            ->where('product_id', $producto->id)
            ->sum('quantity');

        $this->assertEquals(40, $stockBodegaA, 'Stock en Bodega A debe ser 40 kg');
        $this->assertEquals(60, $stockFincaB, 'Stock en Finca B debe ser 60 kg');
    }

    /** @test */
    public function no_se_puede_recibir_mas_de_lo_enviado()
    {
        // ... setup similar ...

        // Intentar recibir 120 kg cuando solo se enviaron 100 kg
        $this->postJson('/api/receptions/create-with-batch', [
            // ...
            'items' => [
                [
                    'product_id' => $producto->id,
                    'quantity_received' => 120,
                    'condition' => 'good',
                ],
            ],
        ])->assertStatus(422);
    }
}
```

---

## \ud83d\udfe1 FASE 3: UNIFICACI\u00d3N DEL FLUJO DE INVENTARIO

### **Objetivo**
Eliminar el flujo dual (OLD/NEW) y usar \u00fanicamente el NEW FLOW.

### **Paso 3.1: Eliminar C\u00f3digo de Compatibilidad**

**Archivo:** `ReceptionController.php`

```php
// ELIMINAR ESTAS L\u00cdNEAS (1076-1161):
// COMPATIBILITY CHECK: Detect if inventory was already reduced...
$existingMovements = InventoryMovement::where(...)->get();
$inventoryAlreadyReduced = $existingMovements->count() > 0;

if (!$inventoryAlreadyReduced) {
    // NEW FLOW
} else {
    // OLD FLOW
}

// REEMPLAZAR CON:
// Siempre usar NEW FLOW (reducci\u00f3n gradual durante recepci\u00f3n)
$this->createExitMovement(...);
```

**C\u00f3digo Simplificado:**

```php
private function processInventoryMovements(...): void
{
    if ($itemData['condition'] !== 'good' || !$receptionItem) {
        return;
    }

    $sourceType = $reception->source_type;

    if ($sourceType === 'purchase') {
        // PURCHASE: Solo ENTRY en destino
        $this->createEntryMovement(...);

    } elseif ($sourceType === 'output') {
        // OUTPUT: EXIT en origen + ENTRY en destino (si no es consumo)

        // 1. Reducir inventario en origen
        $this->createExitMovement(...);

        // 2. Aumentar inventario en destino (solo si no es consumo)
        $output = ProductOutput::find($reception->source_id);
        if ($output->outputType->code !== 'consumption') {
            $this->createEntryMovement(...);
        }
    }
}
```

### **Paso 3.2: Migrar Salidas Antiguas (Si Existen)**

**Script de Migraci\u00f3n:**

```php
// database/migrations/2026_01_20_000000_migrate_old_flow_outputs.php
<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\{ProductOutput, InventoryMovement, Inventory};
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Buscar todas las salidas con movimientos tipo ProductOutput
        $oldFlowMovements = InventoryMovement::where('related_document_type', 'LIKE', '%ProductOutput%')
            ->where('type', 'exit')
            ->get();

        echo "Encontrados {$oldFlowMovements->count()} movimientos de OLD FLOW\n";

        DB::beginTransaction();

        foreach ($oldFlowMovements as $movement) {
            // Recuperar el inventario que se redujo
            $inventory = Inventory::where('product_id', $movement->product_id)
                ->where('brand_id', $movement->brand_id)
                ->where('location_id', $movement->location_id)
                ->first();

            if (!$inventory) {
                // Crear nuevo lote con la cantidad que se hab\u00eda reducido
                Inventory::create([
                    'product_id' => $movement->product_id,
                    'brand_id' => $movement->brand_id,
                    'location_id' => $movement->location_id,
                    'batch_number' => 'RECOVERED-' . $movement->id,
                    'quantity' => $movement->quantity,
                    'unit' => $movement->unit,
                    'unit_price' => $movement->unit_price,
                    'total_value' => $movement->total_price,
                    'status' => 'good',
                ]);
            } else {
                // Aumentar cantidad del lote existente
                $inventory->quantity += $movement->quantity;
                $inventory->total_value = $inventory->quantity * $inventory->unit_price;
                $inventory->save();
            }

            // Eliminar el movimiento antiguo
            $movement->delete();

            echo "Recuperado stock de movimiento {$movement->id}\n";
        }

        DB::commit();

        echo "Migraci\u00f3n completada: {$oldFlowMovements->count()} movimientos migrados\n";
    }

    public function down()
    {
        // No revertible
        throw new \Exception('Esta migraci\u00f3n no es revertible');
    }
};
```

### **Paso 3.3: Actualizar Tests**

```php
/** @test */
public function salida_aprobada_no_reduce_inventario_inmediatamente()
{
    // ... setup ...

    // Aprobar salida
    $this->postJson("/api/product-outputs/{$output->id}/approve")
        ->assertStatus(200);

    // Verificar que el inventario NO se redujo
    $stock = Inventory::where('location_id', $bodegaA->id)->sum('quantity');
    $this->assertEquals(100, $stock, 'Stock NO debe reducirse al aprobar');

    // Verificar que NO hay movimientos EXIT de tipo ProductOutput
    $movements = InventoryMovement::where('related_document_type', 'LIKE', '%ProductOutput%')
        ->where('type', 'exit')
        ->count();

    $this->assertEquals(0, $movements, 'No debe haber movimientos EXIT de ProductOutput');
}
```

---

## \ud83d\udfe1 FASE 4: SISTEMA DE ROLES EN FRONTEND

### **Paso 4.1: Crear AuthContext**

**Archivo:** `frontend/src/context/AuthContext.tsx`

```typescript
import React, { createContext, useContext, ReactNode } from 'react';
import { usePermissions, UserWithPermissions } from '../hooks/usePermissions';

interface AuthContextType {
  user: UserWithPermissions | null;
  isLoading: boolean;
  hasPermission: (permission: string) => boolean;
  hasAnyPermission: (...permissions: string[]) => boolean;
  hasAllPermissions: (...permissions: string[]) => boolean;
  hasModuleAccess: (module: string) => boolean;
  isAdmin: () => boolean;
  getRoleName: () => string;
  getRoleDisplayName: () => string;
  getPermissions: () => string[];
  getAccessibleModules: () => string[];
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  const permissionsData = usePermissions();

  return (
    <AuthContext.Provider value={permissionsData}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = (): AuthContextType => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth debe usarse dentro de AuthProvider');
  }
  return context;
};
```

### **Paso 4.2: Envolver App con AuthProvider**

**Archivo:** `frontend/src/main.tsx`

```typescript
import { AuthProvider } from './context/AuthContext';

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <App />
      </AuthProvider>
    </QueryClientProvider>
  </React.StrictMode>
);
```

### **Paso 4.3: Crear ProtectedRoute**

**Archivo:** `frontend/src/components/auth/ProtectedRoute.tsx`

```typescript
import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { Spin } from 'antd';

interface ProtectedRouteProps {
  children: React.ReactNode;
  requiredPermission?: string;
  requiredModule?: string;
  fallbackPath?: string;
}

export const ProtectedRoute: React.FC<ProtectedRouteProps> = ({
  children,
  requiredPermission,
  requiredModule,
  fallbackPath = '/unauthorized',
}) => {
  const { user, isLoading, hasPermission, hasModuleAccess } = useAuth();

  if (isLoading) {
    return (
      <div style={{ textAlign: 'center', padding: '50px' }}>
        <Spin size="large" />
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (requiredPermission && !hasPermission(requiredPermission)) {
    return <Navigate to={fallbackPath} replace />;
  }

  if (requiredModule && !hasModuleAccess(requiredModule)) {
    return <Navigate to={fallbackPath} replace />;
  }

  return <>{children}</>;
};
```

### **Paso 4.4: Proteger Rutas**

**Archivo:** `frontend/src/App.tsx`

```typescript
import { ProtectedRoute } from './components/auth/ProtectedRoute';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<LoginPage />} />

        {/* Rutas p\u00fablicas */}
        <Route path="/" element={<Layout />}>
          <Route index element={<DashboardPage />} />

          {/* Rutas protegidas por m\u00f3dulo */}
          <Route
            path="/products"
            element={
              <ProtectedRoute requiredModule="products">
                <ProductsPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="/purchases"
            element={
              <ProtectedRoute requiredModule="purchases">
                <PurchasesPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="/admin"
            element={
              <ProtectedRoute requiredModule="admin">
                <AdminPage />
              </ProtectedRoute>
            }
          />
        </Route>

        <Route path="/unauthorized" element={<UnauthorizedPage />} />
      </Routes>
    </BrowserRouter>
  );
}
```

### **Paso 4.5: Men\u00fa Din\u00e1mico en Sidebar**

**Archivo:** `frontend/src/components/layout/Sidebar.tsx`

```typescript
import { useAuth } from '../../context/AuthContext';
import { Menu } from 'antd';
import {
  DashboardOutlined,
  ShoppingCartOutlined,
  InboxOutlined,
  UserOutlined,
} from '@ant-design/icons';

interface MenuItem {
  key: string;
  label: string;
  icon: React.ReactNode;
  path: string;
  module?: string; // null = acceso p\u00fablico
}

const ALL_MENU_ITEMS: MenuItem[] = [
  {
    key: 'dashboard',
    label: 'Dashboard',
    icon: <DashboardOutlined />,
    path: '/',
    module: null,
  },
  {
    key: 'products',
    label: 'Productos',
    icon: <InboxOutlined />,
    path: '/products',
    module: 'products',
  },
  {
    key: 'purchases',
    label: 'Compras',
    icon: <ShoppingCartOutlined />,
    path: '/purchases',
    module: 'purchases',
  },
  {
    key: 'admin',
    label: 'Administraci\u00f3n',
    icon: <UserOutlined />,
    path: '/admin',
    module: 'admin',
  },
];

export const Sidebar: React.FC = () => {
  const { hasModuleAccess, isLoading } = useAuth();
  const navigate = useNavigate();

  // Filtrar men\u00fa seg\u00fan permisos
  const visibleItems = ALL_MENU_ITEMS.filter(item =>
    !item.module || hasModuleAccess(item.module)
  );

  if (isLoading) {
    return <Spin />;
  }

  return (
    <Menu
      mode="inline"
      items={visibleItems.map(item => ({
        key: item.key,
        label: item.label,
        icon: item.icon,
        onClick: () => navigate(item.path),
      }))}
    />
  );
};
```

### **Paso 4.6: Ocultar Botones seg\u00fan Permisos**

**Ejemplo en ProductsPage:**

```typescript
import { useAuth } from '../../context/AuthContext';

export const ProductsPage: React.FC = () => {
  const { hasPermission } = useAuth();

  return (
    <div>
      <h1>Productos</h1>

      {hasPermission('products.create') && (
        <Button type="primary" onClick={handleCreate}>
          Crear Producto
        </Button>
      )}

      <Table
        dataSource={products}
        columns={[
          // ... columnas ...
          {
            title: 'Acciones',
            render: (_, record) => (
              <>
                {hasPermission('products.edit') && (
                  <Button onClick={() => handleEdit(record)}>Editar</Button>
                )}
                {hasPermission('products.delete') && (
                  <Button danger onClick={() => handleDelete(record)}>Eliminar</Button>
                )}
              </>
            ),
          },
        ]}
      />
    </div>
  );
};
```

### **Paso 4.7: Crear Seeders de Roles y Permisos**

**Archivo:** `backend/database/seeders/RolesPermissionsSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Role, Permission};

class RolesPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Crear permisos por m\u00f3dulo
        $modules = [
            'products' => ['view', 'create', 'edit', 'delete'],
            'purchases' => ['view', 'create', 'edit', 'delete'],
            'receptions' => ['view', 'create', 'edit'],
            'outputs' => ['view', 'create', 'edit', 'approve'],
            'admin' => ['view', 'users.manage', 'roles.manage'],
        ];

        $permissions = [];
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = Permission::create([
                    'name' => "{$module}.{$action}",
                    'display_name' => ucfirst($action) . ' ' . ucfirst($module),
                    'module' => $module,
                ]);
            }
        }

        // Crear roles

        // 1. Administrador (acceso total)
        $admin = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrador',
            'has_full_access' => true,
            'excluded_modules' => [],
        ]);

        // 2. Compras (todo excepto admin)
        $compras = Role::create([
            'name' => 'compras',
            'display_name' => 'Compras',
            'has_full_access' => false,
            'excluded_modules' => ['admin'],
        ]);

        $compras->permissions()->attach(
            Permission::whereNotIn('module', ['admin'])->pluck('id')
        );

        // 3. Supervisor/Bodeguero/Operario (solo recepci\u00f3n y salida)
        $supervisor = Role::create([
            'name' => 'supervisor',
            'display_name' => 'Supervisor',
            'has_full_access' => false,
            'excluded_modules' => [],
        ]);

        $supervisor->permissions()->attach(
            Permission::whereIn('module', ['receptions', 'outputs'])->pluck('id')
        );

        echo "Roles y permisos creados correctamente\n";
    }
}
```

---

## \ud83d\udfe0 FASE 5: SIMPLIFICACI\u00d3N DE C\u00d3DIGO

### **Paso 5.1: Crear InventoryMovementService**

**Archivo:** `backend/app/Services/InventoryMovementService.php`

```php
<?php

namespace App\Services;

use App\Models\{Reception, Inventory, InventoryMovement, ProductOutput};
use Illuminate\Support\Facades\Log;

class InventoryMovementService
{
    /**
     * Procesa movimientos de inventario para una recepci\u00f3n
     */
    public function processReceptionMovements(
        Reception $reception,
        array $itemData,
        $receptionItem,
        int $batchNumber,
        string $userId
    ): void {
        if ($itemData['condition'] !== 'good' || !$receptionItem) {
            return;
        }

        if ($reception->source_type === 'purchase') {
            $this->processPurchaseMovement($reception, $itemData, $receptionItem, $batchNumber, $userId);
        } else {
            $this->processOutputMovement($reception, $itemData, $receptionItem, $batchNumber, $userId);
        }
    }

    /**
     * Procesa movimiento de compra (solo ENTRY)
     */
    private function processPurchaseMovement(...): void
    {
        $this->createEntryMovement(...);
    }

    /**
     * Procesa movimiento de salida (EXIT + ENTRY)
     */
    private function processOutputMovement(...): void
    {
        // 1. Crear EXIT en origen
        $this->createExitMovement(...);

        // 2. Crear ENTRY en destino (si no es consumo)
        $output = ProductOutput::find($reception->source_id);
        if ($output->outputType->code !== 'consumption') {
            $this->createEntryMovement(...);
        }
    }

    // ... m\u00e9todos createEntryMovement, createExitMovement, etc.
}
```

### **Paso 5.2: Usar Servicio en Controlador**

**Archivo:** `ReceptionController.php`

```php
use App\Services\InventoryMovementService;

class ReceptionController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryMovementService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function createReceptionWithBatch(Request $request): JsonResponse
    {
        // ...

        foreach ($data['items'] as $itemData) {
            // Usar servicio en lugar de m\u00e9todo privado
            $this->inventoryService->processReceptionMovements(
                $reception,
                $itemData,
                $receptionItem,
                $batchNumber,
                $request->user()->id
            );
        }

        // ...
    }
}
```

### **Paso 5.3: Crear ReportService**

**Archivo:** `backend/app/Services/ReportService.php`

```php
<?php

namespace App\Services;

use App\Models\{Inventory, InventoryMovement};
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function generateKardex(array $filters): array
    {
        // L\u00f3gica extra\u00edda del controlador
        // ...
    }

    public function generateConsumptionReport(array $filters): array
    {
        // L\u00f3gica extra\u00edda del controlador
        // ...
    }
}
```

---

## \ud83d\udfe0 FASE 6: EXPORTACI\u00d3N DE REPORTES

### **Paso 6.1: Instalar Paquetes**

```bash
cd backend
composer require maatwebsite/excel
composer require barryvdh/laravel-dompdf
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"
```

### **Paso 6.2: Crear Clase Export**

**Archivo:** `backend/app/Exports/KardexExport.php`

```php
<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KardexExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    protected $data;
    protected $filters;

    public function __construct(array $data, array $filters = [])
    {
        $this->data = $data;
        $this->filters = $filters;
    }

    public function array(): array
    {
        return array_map(function ($item) {
            return [
                $item['product_name'],
                $item['product_code'] ?? 'N/A',
                $item['category'],
                $item['total_quantity'],
                $item['base_unit'],
                $item['total_value'],
                $item['locations_count'],
                $item['status'],
            ];
        }, $this->data);
    }

    public function headings(): array
    {
        return [
            'Producto',
            'C\u00f3digo',
            'Categor\u00eda',
            'Cantidad Total',
            'Unidad',
            'Valor Total',
            'Ubicaciones',
            'Estado',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    public function title(): string
    {
        return 'Kardex General';
    }
}
```

### **Paso 6.3: Crear Endpoint de Exportaci\u00f3n**

**Archivo:** `InventoryController.php`

```php
use App\Exports\KardexExport;
use Maatwebsite\Excel\Facades\Excel;

public function exportKardex(Request $request)
{
    $kardexData = $this->kardex($request)->getData()->data;

    $fileName = 'kardex_' . date('Y-m-d_His') . '.xlsx';

    return Excel::download(
        new KardexExport($kardexData, $request->all()),
        $fileName
    );
}
```

### **Paso 6.4: Crear Vista PDF**

**Archivo:** `backend/resources/views/exports/kardex.blade.php`

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Kardex General - AgriFlor</title>
    <style>
        body { font-family: Arial, sans-serif; }
        h1 { text-align: center; color: #2e7d32; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #4caf50; color: white; padding: 10px; }
        td { padding: 8px; border: 1px solid #ddd; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <h1>Kardex General de Inventario</h1>
    <p><strong>Fecha de Generaci\u00f3n:</strong> {{ date('d/m/Y H:i:s') }}</p>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>C\u00f3digo</th>
                <th>Cantidad</th>
                <th>Valor Total</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td>{{ $item['product_name'] }}</td>
                <td>{{ $item['product_code'] ?? 'N/A' }}</td>
                <td>{{ $item['total_quantity'] }} {{ $item['base_unit'] }}</td>
                <td>${{ number_format($item['total_value'], 2) }}</td>
                <td>{{ $item['status'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>AgriFlor - Sistema de Gesti\u00f3n Agr\u00edcola</p>
    </div>
</body>
</html>
```

### **Paso 6.5: Frontend - Bot\u00f3n de Exportaci\u00f3n**

**Archivo:** `frontend/src/pages/inventory/KardexPage.tsx`

```typescript
import { DownloadOutlined } from '@ant-design/icons';

export const KardexPage: React.FC = () => {
  const [filters, setFilters] = useState({});
  const [loading, setLoading] = useState(false);

  const handleExportExcel = async () => {
    setLoading(true);
    try {
      const response = await axios.get('/api/inventory/kardex/export', {
        params: filters,
        responseType: 'blob',
      });

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `kardex_${Date.now()}.xlsx`);
      document.body.appendChild(link);
      link.click();
      link.remove();

      message.success('Reporte exportado correctamente');
    } catch (error) {
      message.error('Error al exportar reporte');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <Space style={{ marginBottom: 16 }}>
        <Button
          type="primary"
          icon={<DownloadOutlined />}
          onClick={handleExportExcel}
          loading={loading}
        >
          Exportar Excel
        </Button>
        <Button
          icon={<DownloadOutlined />}
          onClick={handleExportPdf}
          loading={loading}
        >
          Exportar PDF
        </Button>
      </Space>

      {/* Tabla de kardex */}
    </div>
  );
};
```

---

## \ud83d\udfe2 FASE 7: MIGRACI\u00d3N UUID \u2192 INTEGER (OPCIONAL)

### **\u26a0\ufe0f RECOMENDACI\u00d3N: NO EJECUTAR**

**Razones:**
1. Alto costo de desarrollo (40-80 horas)
2. Alto riesgo de bugs
3. Beneficio marginal en performance
4. Sistema ya funcional con UUIDs

**Si a\u00fan as\u00ed se decide ejecutar:**
- Ver secci\u00f3n completa en `DIAGNOSTICO_COMPLETO.md`
- Ejecutar SOLO en ambiente de desarrollo
- Crear backups completos
- Testear exhaustivamente antes de producci\u00f3n

---

## \u2705 CHECKLIST DE VERIFICACI\u00d3N

### **Despu\u00e9s de Fase 1-2 (Bug Transferencias)**

- [ ] Bug reproducido exitosamente
- [ ] Causa ra\u00edz identificada
- [ ] Correcci\u00f3n implementada
- [ ] Tests automatizados creados
- [ ] Tests pasando (verde)
- [ ] Validaci\u00f3n manual exitosa
- [ ] Documentaci\u00f3n actualizada
- [ ] Code review aprobado
- [ ] Merged a main

### **Despu\u00e9s de Fase 3 (Unificaci\u00f3n Flujo)**

- [ ] C\u00f3digo de compatibilidad eliminado
- [ ] Flujo NEW unificado
- [ ] Salidas antiguas migradas (si aplica)
- [ ] Tests actualizados
- [ ] Tests pasando
- [ ] Documentaci\u00f3n de flujo actualizada
- [ ] Performance validado
- [ ] Merged a main

### **Despu\u00e9s de Fase 4 (Roles Frontend)**

- [ ] AuthContext creado
- [ ] ProtectedRoute implementado
- [ ] Rutas protegidas
- [ ] Men\u00fa din\u00e1mico funcionando
- [ ] Botones ocultos seg\u00fan permisos
- [ ] Seeders de roles ejecutados
- [ ] Tests de autorizaci\u00f3n pasando
- [ ] UI validada con diferentes roles
- [ ] Merged a main

### **Despu\u00e9s de Fase 5 (Simplificaci\u00f3n)**

- [ ] InventoryMovementService creado
- [ ] ReportService creado
- [ ] Controladores simplificados
- [ ] Tests refactorizados
- [ ] Code coverage mantenido
- [ ] Performance sin regresi\u00f3n
- [ ] Documentaci\u00f3n de arquitectura actualizada
- [ ] Merged a main

### **Despu\u00e9s de Fase 6 (Exportaci\u00f3n)**

- [ ] Paquetes instalados
- [ ] Clases Export creadas
- [ ] Vistas PDF creadas
- [ ] Endpoints de exportaci\u00f3n funcionando
- [ ] Botones en frontend implementados
- [ ] Descarga de archivos validada
- [ ] Formato Excel correcto
- [ ] Formato PDF correcto
- [ ] Tests de exportaci\u00f3n pasando
- [ ] Merged a main

---

## \ud83d\udcc6 CALENDARIO DE EJECUCI\u00f3N SUGERIDO

### **Semana 1**

**D\u00eda 1-2:** Fase 1 (Investigaci\u00f3n bug)
**D\u00eda 3:** Fase 2 (Correcci\u00f3n bug)
**D\u00eda 4-5:** Fase 3 (Unificaci\u00f3n flujo)

### **Semana 2**

**D\u00eda 1-3:** Fase 4 (Roles frontend)
**D\u00eda 4-5:** Fase 5 (Simplificaci\u00f3n) - Inicio

### **Semana 3**

**D\u00eda 1-2:** Fase 5 (Simplificaci\u00f3n) - Finalizaci\u00f3n
**D\u00eda 3-5:** Fase 6 (Exportaci\u00f3n reportes)

---

## \ud83d\udcdd NOTAS FINALES

1. **Comunicaci\u00f3n:** Informar al equipo antes de cada fase
2. **Backups:** Crear backup de BD antes de cambios cr\u00edticos
3. **Testing:** Ejecutar suite completa de tests antes de merge
4. **Documentaci\u00f3n:** Actualizar docs con cada cambio importante
5. **Code Review:** Revisar c\u00f3digo en pares antes de merge
6. **Despliegue:** Desplegar a staging primero, luego a producci\u00f3n

---

**Documento creado por:** Claude Code (Sonnet 4.5)
**Fecha:** 2026-01-19
**\u00daltima actualizaci\u00f3n:** 2026-01-19
**Proyecto:** AgriFlor - Sistema de Gesti\u00f3n Agr\u00edcola
