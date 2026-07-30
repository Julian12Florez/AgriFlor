import React, { useState } from 'react';
import { Card, Row, Col, DatePicker, Button, Tag, Select, message, Spin, Descriptions, Badge } from 'antd';
import { AuditOutlined, FileExcelOutlined, FilePdfOutlined, ReloadOutlined, FilterOutlined, UserOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import { useQuery } from '@tanstack/react-query';
import { inventoryApi, productsApi, locationsApi, usersApi } from '../../services/api';
import dayjs, { Dayjs } from 'dayjs';
import { buildMovementTypeParams, matchesMovementTypeFilter } from '../../utils/movementFilters';

const { RangePicker } = DatePicker;
const { Option } = Select;

interface AuditEntry {
  id: string;
  type: 'entry' | 'exit' | 'transfer' | 'application' | 'adjustment';
  product_name: string;
  product_code: string;
  brand_name: string;
  location_name: string;
  location_type: string;
  quantity: number;
  unit: string;
  base_quantity?: number;
  base_unit?: string;
  total_quantity_in_base_unit?: number;
  unit_price: number;
  total_price: number;
  responsible_user_name: string;
  responsible_user: string;
  related_document_type: string;
  related_document_id: string;
  observations: string;
  created_at: string;
  expiration_date?: string;
}

const InventoryAudit: React.FC = () => {
  const [dateRange, setDateRange] = useState<[Dayjs | null, Dayjs | null] | null>(null);
  const [locationId, setLocationId] = useState<string | undefined>(undefined);
  const [productId, setProductId] = useState<string | undefined>(undefined);
  const [userId, setUserId] = useState<string | undefined>(undefined);
  const [type, setType] = useState<string | undefined>(undefined);
  const [shouldFetch, setShouldFetch] = useState(false);

  // Fetch products for filter
  const { data: productsData } = useQuery({
    queryKey: ['products-all'],
    queryFn: () => productsApi.list({ per_page: 1000 }),
  });

  // Fetch locations for filter
  const { data: locationsData } = useQuery({
    queryKey: ['locations-all'],
    queryFn: () => locationsApi.list({ per_page: 1000 }),
  });

  // Fetch users for filter
  const { data: usersData } = useQuery({
    queryKey: ['users-simple'],
    queryFn: () => usersApi.listSimple(),
  });

  // Fetch movements
  const { data: movementsData, isLoading, refetch } = useQuery({
    queryKey: ['inventory-movements-audit', dateRange, locationId, productId, userId, type],
    queryFn: () => {
      const params: any = {};

      if (dateRange && dateRange[0] && dateRange[1]) {
        params.start_date = dateRange[0].format('YYYY-MM-DD');
        params.end_date = dateRange[1].format('YYYY-MM-DD');
      }

      if (locationId) params.location_id = locationId;
      if (productId) params.product_id = productId;
      Object.assign(params, buildMovementTypeParams(type));

      return inventoryApi.getMovements(params);
    },
    enabled: shouldFetch,
  });

  const movements: AuditEntry[] = (movementsData?.data || []).filter((m: AuditEntry) => {
    if (userId && m.responsible_user !== userId) return false;
    return true;
  });

  const handleGenerateReport = () => {
    if (!dateRange || !dateRange[0] || !dateRange[1]) {
      message.warning('Por favor selecciona un rango de fechas');
      return;
    }
    setShouldFetch(true);
    refetch();
  };

  const handleClearFilters = () => {
    setDateRange(null);
    setLocationId(undefined);
    setProductId(undefined);
    setUserId(undefined);
    setType(undefined);
    setShouldFetch(false);
  };

  const handleExportExcel = () => {
    message.info('Funcionalidad de exportación a Excel en desarrollo');
  };

  const handleExportPDF = () => {
    message.info('Funcionalidad de exportación a PDF en desarrollo');
  };

  const getTypeTag = (type: string) => {
    const typeConfig: Record<string, { color: string; label: string }> = {
      entry: { color: 'green', label: 'Entrada' },
      exit: { color: 'red', label: 'Salida' },
      transfer: { color: 'blue', label: 'Transferencia' },
      application: { color: 'orange', label: 'Aplicación' },
      adjustment: { color: 'purple', label: 'Ajuste' },
    };

    const config = typeConfig[type] || { color: 'default', label: type };
    return <Tag color={config.color}>{config.label}</Tag>;
  };

  const getDocumentType = (docType: string) => {
    const docLabels: Record<string, string> = {
      'App\\Models\\Reception': 'Recepción',
      'App\\Models\\Purchase': 'Compra',
      'App\\Models\\ProductOutput': 'Salida',
      'App\\Models\\TechnicalOrder': 'Orden Técnica',
      'App\\Models\\InventoryAdjustment': 'Ajuste',
    };

    return docLabels[docType] || docType.split('\\').pop() || docType;
  };

  const getFullPackagingUnitName = (unit: string, baseQuantity?: number, baseUnit?: string) => {
    if (baseQuantity && baseQuantity > 1 && baseUnit) {
      return `${unit} de ${baseQuantity} ${baseUnit}`;
    }
    return unit || 'unidades';
  };

  const mobileColumns: ColumnsType<AuditEntry> = [
    {
      title: 'Auditoría',
      key: 'audit',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.product_name}
          </div>
          <div style={{ fontSize: '12px', color: '#666', marginBottom: 4 }}>
            {record.brand_name} • {record.location_name}
          </div>
          <div style={{ marginBottom: 4 }}>
            {getTypeTag(record.type)}
            <Tag icon={<UserOutlined />} color="default" style={{ marginLeft: 4 }}>
              {record.responsible_user_name}
            </Tag>
          </div>
          <div style={{ fontSize: '12px', color: '#999' }}>
            {dayjs(record.created_at).format('DD/MM/YYYY HH:mm')}
          </div>
          <div style={{ fontSize: '12px', color: '#666', marginTop: 4 }}>
            Doc: {getDocumentType(record.related_document_type)}
          </div>
        </div>
      ),
    },
    {
      title: 'Cantidad',
      key: 'quantity',
      width: 140,
      render: (_, record) => {
        const sign = record.type === 'exit' ? '-' : '+';
        const color = record.type === 'exit' ? '#cf1322' : '#3f8600';
        const hasPackaging = record.base_quantity && record.base_quantity > 1;
        const fullUnitName = getFullPackagingUnitName(record.unit, record.base_quantity, record.base_unit);

        return (
          <div style={{ textAlign: 'right' }}>
            <div style={{ fontWeight: 600, fontSize: '13px', color, marginBottom: 2 }}>
              {sign}{record.quantity.toLocaleString()} {record.unit}
            </div>
            <div style={{ fontSize: '11px', color: '#666', marginBottom: 4 }}>
              {fullUnitName}
            </div>
            {hasPackaging && (
              <div style={{ fontSize: '12px', color: '#2E7D32', fontWeight: 500, marginBottom: 4 }}>
                = {sign}{(record.total_quantity_in_base_unit || 0).toLocaleString()} {record.base_unit}
              </div>
            )}
            <div style={{ fontSize: '11px', color: '#999' }}>
              ${record.total_price.toLocaleString('es-CO')}
            </div>
          </div>
        );
      },
    },
  ];

  const desktopColumns: ColumnsType<AuditEntry> = [
    {
      title: 'Fecha/Hora',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 150,
      render: (date: string) => (
        <div>
          <div>{dayjs(date).format('DD/MM/YYYY')}</div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            {dayjs(date).format('HH:mm:ss')}
          </div>
        </div>
      ),
      sorter: (a, b) => dayjs(a.created_at).unix() - dayjs(b.created_at).unix(),
      defaultSortOrder: 'descend',
    },
    {
      title: 'Tipo',
      dataIndex: 'type',
      key: 'type',
      width: 120,
      render: (type: string) => getTypeTag(type),
      filters: [
        { text: 'Entrada', value: 'entry' },
        { text: 'Salida', value: 'exit' },
        { text: 'Transferencia', value: 'transfer' },
        { text: 'Aplicación', value: 'application' },
        { text: 'Ajuste', value: 'adjustment' },
      ],
      onFilter: (value, record) => matchesMovementTypeFilter(String(value), record),
    },
    {
      title: 'Producto',
      dataIndex: 'product_name',
      key: 'product_name',
      width: 200,
      render: (name: string, record) => (
        <div>
          <div style={{ fontWeight: 500 }}>{name}</div>
          <div style={{ fontSize: '12px', color: '#666' }}>{record.product_code}</div>
        </div>
      ),
    },
    {
      title: 'Marca',
      dataIndex: 'brand_name',
      key: 'brand_name',
      width: 120,
    },
    {
      title: 'Ubicación',
      dataIndex: 'location_name',
      key: 'location_name',
      width: 150,
      render: (name: string, record) => (
        <div>
          <div>{name}</div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            {record.location_type === 'warehouse' ? 'Bodega' : 'Finca'}
          </div>
        </div>
      ),
    },
    {
      title: 'Cantidad',
      dataIndex: 'quantity',
      key: 'quantity',
      width: 100,
      align: 'right',
      render: (qty: number, record) => {
        const sign = record.type === 'exit' ? '-' : '+';
        const color = record.type === 'exit' ? '#cf1322' : '#3f8600';

        return (
          <span style={{ color, fontWeight: 600, fontSize: '14px' }}>
            {sign}{qty.toLocaleString()}
          </span>
        );
      },
    },
    {
      title: 'Unidad',
      key: 'unit',
      width: 140,
      render: (_, record) => {
        const fullUnitName = getFullPackagingUnitName(record.unit, record.base_quantity, record.base_unit);
        return (
          <div style={{ fontSize: '13px' }}>
            {fullUnitName}
          </div>
        );
      },
    },
    {
      title: 'Cantidad Total',
      key: 'total_quantity',
      width: 130,
      align: 'right',
      render: (_, record) => {
        const hasPackaging = record.base_quantity && record.base_quantity > 1;
        if (!hasPackaging) return '-';

        const sign = record.type === 'exit' ? '-' : '+';
        const color = record.type === 'exit' ? '#cf1322' : '#3f8600';

        return (
          <div style={{ textAlign: 'right', fontWeight: 500, color, fontSize: '14px' }}>
            {sign}{(record.total_quantity_in_base_unit || 0).toLocaleString()} {record.base_unit}
          </div>
        );
      },
    },
    {
      title: 'Valor Total',
      dataIndex: 'total_price',
      key: 'total_price',
      width: 120,
      align: 'right',
      render: (price: number) => (
        <span style={{ fontWeight: 500 }}>
          ${price.toLocaleString('es-CO')}
        </span>
      ),
    },
    {
      title: 'Usuario Responsable',
      dataIndex: 'responsible_user_name',
      key: 'responsible_user_name',
      width: 150,
      render: (name: string) => (
        <span>
          <UserOutlined style={{ marginRight: 4, color: '#1890ff' }} />
          {name}
        </span>
      ),
    },
    {
      title: 'Documento',
      key: 'document',
      width: 150,
      render: (_, record) => (
        <div>
          <div style={{ fontSize: '12px' }}>
            {getDocumentType(record.related_document_type)}
          </div>
          <div style={{ fontSize: '11px', color: '#999', fontFamily: 'monospace' }}>
            {record.related_document_id.substring(0, 8)}...
          </div>
        </div>
      ),
    },
  ];

  const expandedRowRender = (record: AuditEntry) => (
    <div style={{ padding: '12px', background: '#fafafa' }}>
      <Descriptions column={2} size="small" bordered>
        <Descriptions.Item label="ID Movimiento" span={2}>
          <code style={{ fontSize: '11px' }}>{record.id}</code>
        </Descriptions.Item>
        <Descriptions.Item label="Usuario Responsable">
          <UserOutlined /> {record.responsible_user_name}
        </Descriptions.Item>
        <Descriptions.Item label="Tipo de Documento">
          {getDocumentType(record.related_document_type)}
        </Descriptions.Item>
        <Descriptions.Item label="ID Documento" span={2}>
          <code style={{ fontSize: '11px' }}>{record.related_document_id}</code>
        </Descriptions.Item>
        <Descriptions.Item label="Precio Unitario">
          ${record.unit_price.toLocaleString('es-CO')}
        </Descriptions.Item>
        <Descriptions.Item label="Precio Total">
          ${record.total_price.toLocaleString('es-CO')}
        </Descriptions.Item>
        {record.expiration_date && (
          <Descriptions.Item label="Fecha de Vencimiento" span={2}>
            {dayjs(record.expiration_date).format('DD/MM/YYYY')}
          </Descriptions.Item>
        )}
        <Descriptions.Item label="Observaciones" span={2}>
          {record.observations || 'Sin observaciones'}
        </Descriptions.Item>
      </Descriptions>
    </div>
  );

  const totalEntries = movements.filter(m => m.type === 'entry').length;
  const totalExits = movements.filter(m => m.type === 'exit').length;
  const totalValue = movements.reduce((sum, m) => sum + (m.total_price || 0), 0);
  const uniqueUsers = new Set(movements.map(m => m.responsible_user)).size;

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>
          <AuditOutlined /> Auditoría de Inventario
        </h1>
        <p style={{ color: '#666', margin: 0 }}>
          Trazabilidad completa de movimientos con información de responsables y documentos
        </p>
      </div>

      {/* Filters Card */}
      <Card style={{ marginBottom: 16 }} title={<><FilterOutlined /> Filtros de Auditoría</>}>
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Rango de Fechas *</div>
            <RangePicker
              value={dateRange}
              onChange={(dates) => setDateRange(dates)}
              style={{ width: '100%' }}
              format="DD/MM/YYYY"
              placeholder={['Fecha inicio', 'Fecha fin']}
            />
          </Col>

          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Usuario Responsable</div>
            <Select
              value={userId}
              onChange={setUserId}
              style={{ width: '100%' }}
              placeholder="Todos los usuarios"
              allowClear
              showSearch
              optionFilterProp="children"
              filterOption={(input, option) => {
                const label = option?.children;
                return String(label || '').toLowerCase().includes(input.toLowerCase());
              }}
            >
              {usersData?.data?.map((user: any) => (
                <Option key={user.id} value={user.id}>
                  {user.name}
                </Option>
              ))}
            </Select>
          </Col>

          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Ubicación</div>
            <Select
              value={locationId}
              onChange={setLocationId}
              style={{ width: '100%' }}
              placeholder="Todas las ubicaciones"
              allowClear
            >
              {locationsData?.data?.map((location: any) => (
                <Option key={location.id} value={location.id}>
                  {location.name}
                </Option>
              ))}
            </Select>
          </Col>

          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Producto</div>
            <Select
              value={productId}
              onChange={setProductId}
              style={{ width: '100%' }}
              placeholder="Todos los productos"
              allowClear
              showSearch
              optionFilterProp="children"
              filterOption={(input, option) => {
                const label = option?.children;
                return String(label || '').toLowerCase().includes(input.toLowerCase());
              }}
            >
              {productsData?.data?.map((product: any) => (
                <Option key={product.id} value={product.id}>
                  {product.name}
                </Option>
              ))}
            </Select>
          </Col>

          <Col xs={24} sm={12} md={8} lg={6}>
            <div style={{ marginBottom: 8, fontWeight: 500 }}>Tipo de Movimiento</div>
            <Select
              value={type}
              onChange={setType}
              style={{ width: '100%' }}
              placeholder="Todos los tipos"
              allowClear
            >
              <Option value="entry">Entrada</Option>
              <Option value="exit">Salida</Option>
              <Option value="transfer">Transferencia</Option>
              <Option value="application">Aplicación</Option>
              <Option value="adjustment">Ajuste</Option>
            </Select>
          </Col>
        </Row>

        <Row gutter={16} style={{ marginTop: 16 }}>
          <Col>
            <Button
              type="primary"
              icon={<AuditOutlined />}
              onClick={handleGenerateReport}
              loading={isLoading}
            >
              Generar Auditoría
            </Button>
          </Col>
          <Col>
            <Button icon={<ReloadOutlined />} onClick={handleClearFilters}>
              Limpiar Filtros
            </Button>
          </Col>
          {shouldFetch && movements.length > 0 && (
            <>
              <Col>
                <Button icon={<FileExcelOutlined />} onClick={handleExportExcel}>
                  Exportar Excel
                </Button>
              </Col>
              <Col>
                <Button icon={<FilePdfOutlined />} onClick={handleExportPDF}>
                  Exportar PDF
                </Button>
              </Col>
            </>
          )}
        </Row>
      </Card>

      {/* Loading */}
      {isLoading && (
        <Card>
          <div style={{ textAlign: 'center', padding: '40px' }}>
            <Spin size="large" />
            <div style={{ marginTop: 16, color: '#666' }}>Generando auditoría...</div>
          </div>
        </Card>
      )}

      {/* No Data */}
      {!isLoading && shouldFetch && movements.length === 0 && (
        <Card>
          <div style={{ textAlign: 'center', padding: '40px', color: '#999' }}>
            No se encontraron movimientos con los filtros seleccionados
          </div>
        </Card>
      )}

      {/* Results */}
      {!isLoading && shouldFetch && movements.length > 0 && (
        <>
          {/* Summary Stats */}
          <Row gutter={16} style={{ marginBottom: 16 }}>
            <Col xs={12} sm={6}>
              <Card>
                <div style={{ fontSize: '14px', color: '#666' }}>Total Movimientos</div>
                <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#1890ff' }}>
                  <Badge count={movements.length} showZero style={{ backgroundColor: '#1890ff' }} />
                </div>
              </Card>
            </Col>
            <Col xs={12} sm={6}>
              <Card>
                <div style={{ fontSize: '14px', color: '#666' }}>Entradas / Salidas</div>
                <div style={{ fontSize: '20px', fontWeight: 'bold' }}>
                  <span style={{ color: '#3f8600' }}>{totalEntries}</span>
                  {' / '}
                  <span style={{ color: '#cf1322' }}>{totalExits}</span>
                </div>
              </Card>
            </Col>
            <Col xs={12} sm={6}>
              <Card>
                <div style={{ fontSize: '14px', color: '#666' }}>Usuarios Involucrados</div>
                <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#722ed1' }}>
                  <UserOutlined /> {uniqueUsers}
                </div>
              </Card>
            </Col>
            <Col xs={12} sm={6}>
              <Card>
                <div style={{ fontSize: '14px', color: '#666' }}>Valor Total</div>
                <div style={{ fontSize: '20px', fontWeight: 'bold', color: '#2E7D32' }}>
                  ${totalValue.toLocaleString('es-CO')}
                </div>
              </Card>
            </Col>
          </Row>

          {/* Audit Table */}
          <Card title={`Auditoría de Movimientos (${movements.length})`}>
            <ResponsiveTable
              mobileColumns={mobileColumns}
              desktopColumns={desktopColumns}
              dataSource={movements}
              rowKey="id"
              expandedRowRender={expandedRowRender}
              entityName="movimientos"
              pagination={{
                pageSize: 50,
                showSizeChanger: true,
                showTotal: (total) => `Total: ${total} movimientos`,
              }}
            />
          </Card>
        </>
      )}
    </div>
  );
};

export default InventoryAudit;
