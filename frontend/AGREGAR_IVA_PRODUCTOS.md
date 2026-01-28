# Agregar Campo IVA al Formulario de Productos

## Backend

✅ **Ya implementado**:
- Migración: `2026_01_19_125722_add_iva_to_products_table.php`
- Modelo actualizado: `app/Models/Product.php`

---

## Frontend: Modificar Formulario de Productos

### Ubicación del archivo:
```
frontend/src/pages/master/products/ProductsPage.tsx
```

### Cambios necesarios:

#### 1. Agregar campo IVA en el formulario (Modal de crear/editar)

Busca la sección del formulario y agrega el campo IVA después del campo `base_unit`:

```tsx
<Form.Item
  name="base_unit"
  label="Unidad Base"
  rules={[{ required: true, message: 'La unidad base es requerida' }]}
>
  <Select placeholder="Seleccione unidad">
    {/* Opciones de unidades */}
  </Select>
</Form.Item>

{/* AGREGAR ESTE CAMPO */}
<Form.Item
  name="iva"
  label="IVA (%)"
  rules={[{ required: true, message: 'El IVA es requerido' }]}
  tooltip="Porcentaje de IVA aplicable al producto"
>
  <Select placeholder="Seleccione % de IVA">
    <Option value={0}>0% (Exento)</Option>
    <Option value={5}>5%</Option>
    <Option value={16}>16%</Option>
    <Option value={19}>19%</Option>
  </Select>
</Form.Item>
```

#### 2. Agregar IVA al inicializar valores del formulario

Cuando se edita un producto, asegúrate de incluir el campo `iva`:

```tsx
const handleEditProduct = (record: Product) => {
  setSelectedProduct(record);
  form.setFieldsValue({
    name: record.name,
    product_code: record.productCode,
    brand_id: record.brandId,
    category: record.category,
    base_unit: record.baseUnit,
    iva: record.iva || 0, // AGREGAR ESTA LÍNEA
    active_ingredient: record.activeIngredient,
    min_stock: record.minStock,
    description: record.description,
    status: record.status,
  });
  setIsModalVisible(true);
};
```

#### 3. Agregar IVA al crear/actualizar producto

Asegúrate de que el campo `iva` se envíe en el payload:

```tsx
const handleCreateProduct = () => {
  form
    .validateFields()
    .then((values) => {
      const productData = {
        name: values.name,
        product_code: values.product_code,
        brand_id: values.brand_id,
        category: values.category,
        base_unit: values.base_unit,
        iva: values.iva, // AGREGAR ESTA LÍNEA
        active_ingredient: values.active_ingredient,
        min_stock: values.min_stock,
        description: values.description,
        status: values.status,
      };

      createProductMutation.mutate(productData);
    })
    .catch((error) => {
      console.error('Validation error:', error);
    });
};
```

#### 4. (Opcional) Mostrar IVA en la tabla de productos

Si quieres mostrar el IVA en la tabla de productos:

```tsx
const columns: ColumnsType<Product> = [
  {
    title: 'Código',
    dataIndex: 'productCode',
    key: 'productCode',
  },
  {
    title: 'Nombre',
    dataIndex: 'name',
    key: 'name',
  },
  {
    title: 'Categoría',
    dataIndex: 'category',
    key: 'category',
  },
  {
    title: 'Unidad Base',
    dataIndex: 'baseUnit',
    key: 'baseUnit',
  },
  // AGREGAR ESTA COLUMNA (opcional)
  {
    title: 'IVA',
    dataIndex: 'iva',
    key: 'iva',
    width: 80,
    render: (iva: number) => `${iva}%`,
  },
  {
    title: 'Estado',
    dataIndex: 'status',
    key: 'status',
    render: (status: string) => (
      <Tag color={status === 'active' ? 'green' : 'red'}>
        {status === 'active' ? 'Activo' : 'Inactivo'}
      </Tag>
    ),
  },
  // ... más columnas
];
```

#### 5. Actualizar interfaz TypeScript (si existe)

Si tienes una interfaz `Product` en TypeScript, agrégale el campo:

```tsx
interface Product {
  id: string;
  name: string;
  productCode: string;
  brandId: string;
  brandName?: string;
  category: string;
  baseUnit: string;
  iva: number; // AGREGAR ESTA LÍNEA
  activeIngredient?: string;
  minStock: number;
  status: string;
  description?: string;
  createdAt?: string;
  updatedAt?: string;
}
```

---

## Valores de IVA comunes en Colombia

- **0%**: Productos exentos de IVA
- **5%**: Algunos productos básicos
- **16%**: IVA estándar (hasta 2024)
- **19%**: IVA estándar (desde 2024)

Puedes personalizar los porcentajes según tu país o necesidades.

---

## Validación en Backend

El backend ya acepta el campo `iva` en:

- **Controlador**: `ProductController::store()` y `ProductController::update()`
- **Validación**: Asegúrate de agregar la regla de validación:

```php
// app/Http/Controllers/Api/ProductController.php

$validator = Validator::make($request->all(), [
    'name' => 'required|string|max:255',
    'product_code' => 'required|string|max:100|unique:products,product_code,' . $id,
    'brand_id' => 'required|uuid|exists:brands,id',
    'category' => 'required|string',
    'base_unit' => 'required|string|max:50',
    'iva' => 'required|integer|min:0|max:100', // AGREGAR ESTA LÍNEA
    'active_ingredient' => 'nullable|string',
    'min_stock' => 'nullable|numeric|min:0',
    'status' => 'required|in:active,inactive',
    'description' => 'nullable|string',
]);
```

---

## Ejemplo Completo del Formulario

```tsx
<Form form={form} layout="vertical">
  <Row gutter={16}>
    <Col span={12}>
      <Form.Item
        name="name"
        label="Nombre del Producto"
        rules={[{ required: true, message: 'El nombre es requerido' }]}
      >
        <Input placeholder="Nombre del producto" />
      </Form.Item>
    </Col>
    <Col span={12}>
      <Form.Item
        name="product_code"
        label="Código"
        rules={[{ required: true, message: 'El código es requerido' }]}
      >
        <Input placeholder="Código del producto" />
      </Form.Item>
    </Col>
  </Row>

  <Row gutter={16}>
    <Col span={12}>
      <Form.Item
        name="brand_id"
        label="Marca"
        rules={[{ required: true, message: 'La marca es requerida' }]}
      >
        <Select placeholder="Seleccione marca">
          {brands.map((brand) => (
            <Option key={brand.id} value={brand.id}>
              {brand.name}
            </Option>
          ))}
        </Select>
      </Form.Item>
    </Col>
    <Col span={12}>
      <Form.Item
        name="category"
        label="Categoría"
        rules={[{ required: true, message: 'La categoría es requerida' }]}
      >
        <Select placeholder="Seleccione categoría">
          <Option value="fertilizante">Fertilizante</Option>
          <Option value="pesticida">Pesticida</Option>
          <Option value="herbicida">Herbicida</Option>
          <Option value="fungicida">Fungicida</Option>
          <Option value="insecticida">Insecticida</Option>
          <Option value="otro">Otro</Option>
        </Select>
      </Form.Item>
    </Col>
  </Row>

  <Row gutter={16}>
    <Col span={12}>
      <Form.Item
        name="base_unit"
        label="Unidad Base"
        rules={[{ required: true, message: 'La unidad base es requerida' }]}
      >
        <Select placeholder="Seleccione unidad">
          <Option value="L">Litros (L)</Option>
          <Option value="kg">Kilogramos (kg)</Option>
          <Option value="g">Gramos (g)</Option>
          <Option value="ml">Mililitros (ml)</Option>
        </Select>
      </Form.Item>
    </Col>
    <Col span={12}>
      {/* CAMPO IVA */}
      <Form.Item
        name="iva"
        label="IVA (%)"
        rules={[{ required: true, message: 'El IVA es requerido' }]}
        tooltip="Porcentaje de IVA aplicable al producto"
      >
        <Select placeholder="Seleccione % de IVA">
          <Option value={0}>0% (Exento)</Option>
          <Option value={5}>5%</Option>
          <Option value={16}>16%</Option>
          <Option value={19}>19%</Option>
        </Select>
      </Form.Item>
    </Col>
  </Row>

  <Row gutter={16}>
    <Col span={12}>
      <Form.Item name="active_ingredient" label="Ingrediente Activo">
        <Input placeholder="Ingrediente activo" />
      </Form.Item>
    </Col>
    <Col span={12}>
      <Form.Item
        name="min_stock"
        label="Stock Mínimo"
        rules={[{ required: true, message: 'El stock mínimo es requerido' }]}
      >
        <InputNumber min={0} style={{ width: '100%' }} placeholder="0" />
      </Form.Item>
    </Col>
  </Row>

  <Form.Item name="description" label="Descripción">
    <TextArea rows={3} placeholder="Descripción del producto" />
  </Form.Item>

  <Form.Item
    name="status"
    label="Estado"
    rules={[{ required: true, message: 'El estado es requerido' }]}
  >
    <Select placeholder="Seleccione estado">
      <Option value="active">Activo</Option>
      <Option value="inactive">Inactivo</Option>
    </Select>
  </Form.Item>
</Form>
```

---

## Notas Importantes

1. **Migración**: Ejecutar `php artisan migrate` para agregar el campo a la base de datos
2. **Valor por defecto**: El campo tiene valor por defecto 0 (exento de IVA)
3. **Productos existentes**: Los productos creados antes de la migración tendrán IVA = 0 automáticamente
4. **Validación**: El IVA debe ser un número entero entre 0 y 100

---

## Uso del Campo IVA

El campo IVA puede usarse para:

1. **Cálculo de precios con impuestos**:
   ```tsx
   const precioConIVA = precioBase * (1 + (iva / 100));
   ```

2. **Reportes de impuestos**:
   ```tsx
   const totalIVA = precioBase * (iva / 100);
   ```

3. **Facturas y cotizaciones**:
   ```tsx
   <Descriptions>
     <Descriptions.Item label="Precio base">
       {formatCurrency(precioBase)}
     </Descriptions.Item>
     <Descriptions.Item label="IVA ({producto.iva}%)">
       {formatCurrency(precioBase * (producto.iva / 100))}
     </Descriptions.Item>
     <Descriptions.Item label="Total">
       {formatCurrency(precioBase * (1 + (producto.iva / 100)))}
     </Descriptions.Item>
   </Descriptions>
   ```
