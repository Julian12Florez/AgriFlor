# Ejemplo de Aplicación: Módulo de Compras

## 📌 Ejemplo Real de Implementación

A continuación se muestra cómo aplicar el formateo en el módulo de compras (`Purchases.tsx`).

---

## PASO 1: Importar las funciones

**Al inicio del archivo, después de los imports existentes:**

```typescript
// Otros imports...
import { purchasesApi, suppliersApi, productsApi, locationsApi } from '../../services/api';
import { downloadPurchaseOrderPDF, openPurchaseOrderPDF } from '../../utils/pdfGenerator';

// ⭐ AGREGAR ESTA LÍNEA
import { formatCurrency, formatQuantity } from '../../utils/formatters';
```

---

## PASO 2: Aplicar en las columnas de la tabla

### Ejemplo 1: Columna de Total (Mobile)

**ANTES:**
```typescript
const mobileColumns: ColumnsType<Purchase> = [
  {
    title: 'Compra',
    key: 'purchase',
    render: (_, record) => (
      <div>
        <div style={{ fontWeight: 500, fontSize: '14px' }}>
          {record.orderNumber}
        </div>
        <div style={{ fontSize: '12px', color: '#666' }}>
          {record.supplierName}
        </div>
        <div style={{ fontSize: '12px', color: '#666' }}>
          <Tag color={getStatusColor(record.status)}>
            {getStatusText(record.status)}
          </Tag>
          <span style={{ marginLeft: 8 }}>${record.total.toLocaleString()}</span>
        </div>
      </div>
    ),
  },
];
```

**DESPUÉS:**
```typescript
const mobileColumns: ColumnsType<Purchase> = [
  {
    title: 'Compra',
    key: 'purchase',
    render: (_, record) => (
      <div>
        <div style={{ fontWeight: 500, fontSize: '14px' }}>
          {record.orderNumber}
        </div>
        <div style={{ fontSize: '12px', color: '#666' }}>
          {record.supplierName}
        </div>
        <div style={{ fontSize: '12px', color: '#666' }}>
          <Tag color={getStatusColor(record.status)}>
            {getStatusText(record.status)}
          </Tag>
          {/* ⭐ CAMBIO AQUÍ */}
          <span style={{ marginLeft: 8 }}>{formatCurrency(record.total)}</span>
        </div>
      </div>
    ),
  },
];
```

**Resultado:**
- Antes: `$123456.78`
- Después: `$123,456.78`

---

### Ejemplo 2: Columna de Total (Desktop)

**ANTES:**
```typescript
{
  title: 'Total',
  dataIndex: 'total',
  key: 'total',
  sorter: (a, b) => a.total - b.total,
  render: (total: number) => <span>${total.toFixed(2)}</span>,
},
```

**DESPUÉS:**
```typescript
{
  title: 'Total',
  dataIndex: 'total',
  key: 'total',
  sorter: (a, b) => a.total - b.total,
  render: (total: number) => <span>{formatCurrency(total)}</span>, // ⭐ CAMBIO AQUÍ
},
```

---

## PASO 3: Aplicar en los detalles del modal

**ANTES:**
```typescript
<Descriptions size="small" column={isMobile ? 1 : 2}>
  <Descriptions.Item label="Número">{selectedPurchase.orderNumber}</Descriptions.Item>
  <Descriptions.Item label="Estado">
    <Tag color={getStatusColor(selectedPurchase.status)}>
      {getStatusText(selectedPurchase.status)}
    </Tag>
  </Descriptions.Item>
  <Descriptions.Item label="Proveedor">{selectedPurchase.supplierName}</Descriptions.Item>
  <Descriptions.Item label="Fecha">{selectedPurchase.purchaseDate}</Descriptions.Item>
  <Descriptions.Item label="Subtotal">${selectedPurchase.subtotal.toFixed(2)}</Descriptions.Item>
  <Descriptions.Item label="IVA">${selectedPurchase.tax.toFixed(2)}</Descriptions.Item>
  <Descriptions.Item label="Total">${selectedPurchase.total.toFixed(2)}</Descriptions.Item>
</Descriptions>
```

**DESPUÉS:**
```typescript
<Descriptions size="small" column={isMobile ? 1 : 2}>
  <Descriptions.Item label="Número">{selectedPurchase.orderNumber}</Descriptions.Item>
  <Descriptions.Item label="Estado">
    <Tag color={getStatusColor(selectedPurchase.status)}>
      {getStatusText(selectedPurchase.status)}
    </Tag>
  </Descriptions.Item>
  <Descriptions.Item label="Proveedor">{selectedPurchase.supplierName}</Descriptions.Item>
  <Descriptions.Item label="Fecha">{selectedPurchase.purchaseDate}</Descriptions.Item>
  {/* ⭐ CAMBIOS AQUÍ */}
  <Descriptions.Item label="Subtotal">{formatCurrency(selectedPurchase.subtotal)}</Descriptions.Item>
  <Descriptions.Item label="IVA">{formatCurrency(selectedPurchase.tax)}</Descriptions.Item>
  <Descriptions.Item label="Total">{formatCurrency(selectedPurchase.total)}</Descriptions.Item>
</Descriptions>
```

---

## PASO 4: Aplicar en la tabla de productos

**ANTES:**
```typescript
const columns = [
  { title: 'Producto', dataIndex: 'productName', key: 'product' },
  { title: 'Cantidad', dataIndex: 'quantity', key: 'quantity' },
  { title: 'Precio Unit.', dataIndex: 'unitPrice', key: 'unitPrice',
    render: (price: number) => `$${price.toFixed(2)}` },
  { title: 'Subtotal', dataIndex: 'subtotal', key: 'subtotal',
    render: (subtotal: number) => `$${subtotal.toFixed(2)}` },
];
```

**DESPUÉS:**
```typescript
const columns = [
  { title: 'Producto', dataIndex: 'productName', key: 'product' },
  // ⭐ CAMBIO EN CANTIDAD
  { title: 'Cantidad', dataIndex: 'quantity', key: 'quantity',
    render: (qty: number) => formatQuantity(qty) },
  // ⭐ CAMBIO EN PRECIO UNITARIO
  { title: 'Precio Unit.', dataIndex: 'unitPrice', key: 'unitPrice',
    render: (price: number) => formatCurrency(price) },
  // ⭐ CAMBIO EN SUBTOTAL
  { title: 'Subtotal', dataIndex: 'subtotal', key: 'subtotal',
    render: (subtotal: number) => formatCurrency(subtotal) },
];
```

**Resultado:**
```
ANTES:
Producto          | Cantidad | Precio Unit. | Subtotal
Fertilizante NPK  | 1000     | $45.50       | $45500.00

DESPUÉS:
Producto          | Cantidad  | Precio Unit. | Subtotal
Fertilizante NPK  | 1,000.00  | $45.50       | $45,500.00
```

---

## 📊 Comparación Visual

### Tabla de Compras

**ANTES:**
```
COMP-2025-000001  |  Proveedor ABC  |  Pending    |  $1234567.89
COMP-2025-000002  |  Proveedor XYZ  |  Received   |  $987654.32
```

**DESPUÉS:**
```
COMP-2025-000001  |  Proveedor ABC  |  Disponible para Recepción  |  $1,234,567.89
COMP-2025-000002  |  Proveedor XYZ  |  Recibido                   |  $987,654.32
```

### Modal de Detalles

**ANTES:**
```
Subtotal: $450000.00
IVA:      $85500.00
Total:    $535500.00
```

**DESPUÉS:**
```
Subtotal: $450,000.00
IVA:      $85,500.00
Total:    $535,500.00
```

---

## ⚠️ IMPORTANTE: Qué NO cambiar

### ❌ NO formatear inputs de formulario

**INCORRECTO:**
```typescript
<InputNumber
  value={formatCurrency(price)}  // ❌ MAL
  onChange={handleChange}
/>
```

**CORRECTO:**
```typescript
<InputNumber
  value={price}  // ✅ BIEN - valor numérico real
  onChange={handleChange}
  // Opcionalmente puedes agregar un formatter visual para InputNumber de Ant Design
  formatter={value => `$ ${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
  parser={value => value!.replace(/\$\s?|(,*)/g, '')}
/>
```

### ❌ NO formatear datos enviados al backend

**INCORRECTO:**
```typescript
const purchaseData = {
  total: formatCurrency(total),  // ❌ MAL - envía "$1,234.56"
  quantity: formatQuantity(qty)  // ❌ MAL - envía "1,234.56"
};
```

**CORRECTO:**
```typescript
const purchaseData = {
  total: total,      // ✅ BIEN - envía 1234.56
  quantity: quantity // ✅ BIEN - envía 1234.56
};
```

---

## 🎯 Módulos a Actualizar

Recomendación de prioridad:

1. **Alta prioridad:**
   - ✅ Compras (`Purchases.tsx`)
   - ✅ Salidas (`Outputs.tsx`)
   - ✅ Recepciones (`Reception.tsx`)
   - ✅ Inventario (`Inventory.tsx`)

2. **Media prioridad:**
   - Órdenes Técnicas (`Orders.tsx`)
   - Dashboard (si tiene números)

3. **Baja prioridad:**
   - Configuraciones
   - Formularios de creación

---

## 🚀 Checklist de Implementación por Módulo

Para cada módulo:

1. [ ] Importar funciones de formateo
2. [ ] Actualizar columnas de tabla (mobile)
3. [ ] Actualizar columnas de tabla (desktop)
4. [ ] Actualizar modal de detalles
5. [ ] Actualizar cards/badges con números
6. [ ] Verificar que inputs NO usen formatters
7. [ ] Probar que el backend recibe datos correctos
8. [ ] Verificar que los cálculos funcionan

---

## 💡 Tips

1. **Buscar y reemplazar con cuidado:**
   - Busca: `$${value.toFixed(2)}`
   - Reemplaza con: `{formatCurrency(value)}`

2. **Para cantidades sin decimales:**
   ```typescript
   formatQuantity(value, 0)  // Muestra: 1,234
   ```

3. **Para totales en footers:**
   ```typescript
   const totalSum = items.reduce((sum, item) => sum + item.total, 0);
   return <strong>{formatCurrency(totalSum)}</strong>;
   ```

4. **Para badges/tags:**
   ```typescript
   <Badge count={formatQuantity(pendingCount, 0)} />
   ```

---

## ✅ Resultado Final

Después de aplicar en todos los módulos, tu aplicación mostrará:

- **Números grandes más legibles**: 1,234,567.89 en lugar de 1234567.89
- **Formato consistente** en toda la app
- **Mejor experiencia de usuario**
- **Sin afectar funcionalidad** del backend

🎉 ¡Todo funcionará exactamente igual, pero se verá mucho mejor!
