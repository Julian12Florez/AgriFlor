# Guía de Uso: Formateo Visual de Números

## 📋 Resumen

Se creó el archivo `/src/utils/formatters.ts` con funciones para formatear números **SOLO VISUALMENTE**.

**IMPORTANTE:** Estas funciones NO afectan:
- ❌ Los valores enviados al backend
- ❌ Los valores almacenados en el estado
- ❌ Los cálculos matemáticos
- ✅ Solo afectan cómo se **muestran** en la pantalla

---

## 🔧 Funciones Disponibles

### 1. `formatNumber(value, decimals)`
Formatea un número con separador de miles.

```typescript
import { formatNumber } from '../utils/formatters';

// Ejemplo
formatNumber(1234.56)        // "1,234.56"
formatNumber(1234.56, 0)     // "1,235"
formatNumber(1000000)        // "1,000,000.00"
```

### 2. `formatCurrency(value)`
Formatea un número como moneda (Peso Colombiano).

```typescript
import { formatCurrency } from '../utils/formatters';

// Ejemplo
formatCurrency(1234.56)      // "$1,234.56"
formatCurrency(1000000)      // "$1,000,000.00"
```

### 3. `formatQuantity(value, decimals)`
Formatea una cantidad (igual que formatNumber, pero semánticamente diferente).

```typescript
import { formatQuantity } from '../utils/formatters';

// Ejemplo
formatQuantity(1234.56)      // "1,234.56"
formatQuantity(100, 0)       // "100"
```

### 4. `formatPercentage(value, decimals)`
Formatea un porcentaje.

```typescript
import { formatPercentage } from '../utils/formatters';

// Ejemplo
formatPercentage(85.5)       // "85.50%"
formatPercentage(100)        // "100.00%"
```

---

## 💡 Ejemplos de Implementación

### Ejemplo 1: En una tabla (mostrar precio)

**ANTES:**
```typescript
<td>${record.total}</td>
```

**DESPUÉS:**
```typescript
import { formatCurrency } from '../../utils/formatters';

<td>{formatCurrency(record.total)}</td>
```

**Resultado visual:**
- Antes: `$123456.78`
- Después: `$123,456.78`

---

### Ejemplo 2: En una tabla (mostrar cantidad)

**ANTES:**
```typescript
<div>{item.quantity}</div>
```

**DESPUÉS:**
```typescript
import { formatQuantity } from '../../utils/formatters';

<div>{formatQuantity(item.quantity)}</div>
```

**Resultado visual:**
- Antes: `1234.56`
- Después: `1,234.56`

---

### Ejemplo 3: En detalles/descriptions

**ANTES:**
```typescript
<Descriptions.Item label="Total">${purchase.total}</Descriptions.Item>
<Descriptions.Item label="Cantidad">{item.quantity}</Descriptions.Item>
```

**DESPUÉS:**
```typescript
import { formatCurrency, formatQuantity } from '../../utils/formatters';

<Descriptions.Item label="Total">{formatCurrency(purchase.total)}</Descriptions.Item>
<Descriptions.Item label="Cantidad">{formatQuantity(item.quantity)}</Descriptions.Item>
```

---

### Ejemplo 4: En Tag o Badge

**ANTES:**
```typescript
<span style={{ marginLeft: 8 }}>${record.total.toLocaleString()}</span>
```

**DESPUÉS:**
```typescript
import { formatCurrency } from '../../utils/formatters';

<span style={{ marginLeft: 8 }}>{formatCurrency(record.total)}</span>
```

---

### Ejemplo 5: En progreso/totales

**ANTES:**
```typescript
<div>{totalReceived} / {totalExpected} productos</div>
```

**DESPUÉS:**
```typescript
import { formatQuantity } from '../../utils/formatters';

<div>{formatQuantity(totalReceived, 0)} / {formatQuantity(totalExpected, 0)} productos</div>
```

**Resultado visual:**
- Antes: `1250 / 2500 productos`
- Después: `1,250 / 2,500 productos`

---

## 🚫 Dónde NO Usarlo

**NO uses formatters en:**

1. **Valores de formularios (inputs)**
   ```typescript
   // ❌ MAL
   <Input value={formatNumber(value)} />

   // ✅ BIEN - El input debe tener el valor numérico real
   <Input value={value} />
   ```

2. **Datos enviados al backend**
   ```typescript
   // ❌ MAL
   const data = { total: formatCurrency(total) };

   // ✅ BIEN
   const data = { total: total };
   ```

3. **Cálculos matemáticos**
   ```typescript
   // ❌ MAL
   const sum = parseFloat(formatNumber(a)) + parseFloat(formatNumber(b));

   // ✅ BIEN
   const sum = a + b;
   ```

---

## ✅ Dónde SÍ Usarlo

**USA formatters en:**

1. **Mostrar en tablas (columnas de solo lectura)**
2. **Mostrar en cards/badges/tags**
3. **Mostrar en Descriptions/detalles**
4. **Mostrar en modales de información**
5. **Mostrar en tooltips**
6. **Mostrar en reportes/PDFs**

---

## 🎯 Patrón Recomendado

```typescript
// 1. Importa las funciones necesarias
import { formatCurrency, formatQuantity } from '../../utils/formatters';

// 2. Mantén los valores reales en el estado
const [total, setTotal] = useState(123456.78);
const [quantity, setQuantity] = useState(1234.56);

// 3. Usa formatters SOLO en el render para mostrar
return (
  <div>
    {/* Solo visual - no afecta el estado */}
    <span>Total: {formatCurrency(total)}</span>
    <span>Cantidad: {formatQuantity(quantity)}</span>

    {/* El input sigue usando el valor real */}
    <Input
      type="number"
      value={quantity}
      onChange={e => setQuantity(parseFloat(e.target.value))}
    />
  </div>
);
```

---

## 📝 Checklist de Implementación

Para implementar formateo en un módulo:

- [ ] Importar las funciones necesarias
- [ ] Aplicar `formatCurrency()` en todos los precios/montos
- [ ] Aplicar `formatQuantity()` en todas las cantidades
- [ ] Aplicar `formatPercentage()` en todos los porcentajes
- [ ] Verificar que los inputs NO usen formatters
- [ ] Verificar que el backend recibe valores sin formatear
- [ ] Probar que los cálculos siguen funcionando correctamente

---

## 🔍 Búsqueda Rápida

Para encontrar dónde aplicar formatters, busca en el código:

```bash
# Buscar precios/totales
grep -r "\.total" --include="*.tsx"
grep -r "price" --include="*.tsx"

# Buscar cantidades
grep -r "quantity" --include="*.tsx"
grep -r "\.amount" --include="*.tsx"

# Buscar porcentajes
grep -r "percentage" --include="*.tsx"
grep -r "\.percent" --include="*.tsx"
```

---

## ⚡ Beneficios

1. **Mejor legibilidad**: 1,234,567.89 es más fácil de leer que 1234567.89
2. **Consistencia**: Mismo formato en toda la app
3. **Sin librerías externas**: Usa API nativa de JavaScript
4. **Cero impacto**: No afecta funcionalidad, solo presentación
5. **Fácil mantenimiento**: Todo centralizado en un archivo

---

## 🌍 Localización

El formato usa `es-CO` (Español - Colombia):
- Separador de miles: `,` (coma)
- Separador decimal: `.` (punto)
- Moneda: `COP` (Peso Colombiano)

Si necesitas cambiar:
```typescript
// En formatters.ts, cambia 'es-CO' por:
'es-ES'  // España
'es-MX'  // México
'es-AR'  // Argentina
'en-US'  // Estados Unidos
```

---

## 🆘 Soporte

Si tienes dudas, revisa:
1. Este archivo de documentación
2. El archivo `/src/utils/formatters.ts`
3. Los ejemplos de implementación en los módulos
