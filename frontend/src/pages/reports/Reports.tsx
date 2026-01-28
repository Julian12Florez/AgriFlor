import React, { useState } from 'react';
import { Card, Row, Col, Select, DatePicker, Button, Tag, Statistic, Progress, Descriptions } from 'antd';
import { BarChartOutlined, FileExcelOutlined, FilePdfOutlined, PrinterOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';

const { Option } = Select;
const { RangePicker } = DatePicker;

const Reports: React.FC = () => {
  const [reportType, setReportType] = useState<string>('inventory');
  const [dateRange, setDateRange] = useState<any>(null);

  const mockReportData = {
    inventory: [
      { category: 'Fertilizantes', quantity: 2450, value: 6125000, percentage: 45 },
      { category: 'Herbicidas', quantity: 890, value: 4005000, percentage: 25 },
      { category: 'Pesticidas', quantity: 650, value: 3250000, percentage: 20 },
      { category: 'Fungicidas', quantity: 320, value: 1600000, percentage: 10 }
    ],
    purchases: [
      { month: 'Enero', amount: 15000000, orders: 25 },
      { month: 'Febrero', amount: 12500000, orders: 20 },
      { month: 'Marzo', amount: 18000000, orders: 30 }
    ],
    applications: [
      { farm: 'Finca La Esperanza', applications: 15, products: 8, cost: 2500000 },
      { farm: 'Finca El Porvenir', applications: 12, products: 6, cost: 1800000 }
    ]
  };

  const inventoryMobileColumns: ColumnsType<any> = [
    {
      title: 'Categoría',
      key: 'category',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.category}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            <Progress percent={record.percentage} size="small" showInfo={false} />
            <span style={{ marginLeft: 8 }}>{record.percentage}%</span>
          </div>
        </div>
      ),
    },
    {
      title: 'Valor',
      dataIndex: 'value',
      key: 'value',
      width: 100,
      render: (val: number) => (
        <span style={{ fontWeight: 500, fontSize: '14px' }}>
          ${val.toLocaleString('es-CO')}
        </span>
      ),
    },
  ];

  const inventoryDesktopColumns: ColumnsType<any> = [
    { title: 'Categoría', dataIndex: 'category', key: 'category' },
    { title: 'Cantidad', dataIndex: 'quantity', key: 'quantity', render: (val: number) => `${val.toLocaleString()} unidades` },
    { title: 'Valor', dataIndex: 'value', key: 'value', render: (val: number) => `$${val.toLocaleString('es-CO')}` },
    {
      title: 'Participación',
      dataIndex: 'percentage',
      key: 'percentage',
      render: (val: number) => <Progress percent={val} size="small" />
    }
  ];

  const inventoryExpandedRowRender = (record: any) => (
    <Descriptions size="small" column={1}>
      <Descriptions.Item label="Cantidad">{record.quantity.toLocaleString()} unidades</Descriptions.Item>
      <Descriptions.Item label="Valor total">${record.value.toLocaleString('es-CO')}</Descriptions.Item>
      <Descriptions.Item label="Participación">{record.percentage}% del inventario total</Descriptions.Item>
    </Descriptions>
  );

  const purchasesMobileColumns: ColumnsType<any> = [
    {
      title: 'Período',
      key: 'period',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.month}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            {record.orders} órdenes
          </div>
        </div>
      ),
    },
    {
      title: 'Monto',
      dataIndex: 'amount',
      key: 'amount',
      width: 100,
      render: (val: number) => (
        <span style={{ fontWeight: 500, fontSize: '14px' }}>
          ${val.toLocaleString('es-CO')}
        </span>
      ),
    },
  ];

  const purchasesDesktopColumns: ColumnsType<any> = [
    { title: 'Mes', dataIndex: 'month', key: 'month' },
    { title: 'Monto', dataIndex: 'amount', key: 'amount', render: (val: number) => `$${val.toLocaleString('es-CO')}` },
    { title: 'Órdenes', dataIndex: 'orders', key: 'orders' }
  ];

  const purchasesExpandedRowRender = (record: any) => (
    <Descriptions size="small" column={1}>
      <Descriptions.Item label="Período">{record.month}</Descriptions.Item>
      <Descriptions.Item label="Monto total">${record.amount.toLocaleString('es-CO')}</Descriptions.Item>
      <Descriptions.Item label="Número de órdenes">{record.orders}</Descriptions.Item>
      <Descriptions.Item label="Promedio por orden">
        ${Math.round(record.amount / record.orders).toLocaleString('es-CO')}
      </Descriptions.Item>
    </Descriptions>
  );

  const applicationsMobileColumns: ColumnsType<any> = [
    {
      title: 'Finca',
      key: 'farm',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.farm}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            {record.applications} aplicaciones • {record.products} productos
          </div>
        </div>
      ),
    },
    {
      title: 'Costo',
      dataIndex: 'cost',
      key: 'cost',
      width: 100,
      render: (val: number) => (
        <span style={{ fontWeight: 500, fontSize: '14px' }}>
          ${val.toLocaleString('es-CO')}
        </span>
      ),
    },
  ];

  const applicationsDesktopColumns: ColumnsType<any> = [
    { title: 'Finca', dataIndex: 'farm', key: 'farm' },
    { title: 'Aplicaciones', dataIndex: 'applications', key: 'applications' },
    { title: 'Productos', dataIndex: 'products', key: 'products' },
    { title: 'Costo', dataIndex: 'cost', key: 'cost', render: (val: number) => `$${val.toLocaleString('es-CO')}` }
  ];

  const applicationsExpandedRowRender = (record: any) => (
    <Descriptions size="small" column={1}>
      <Descriptions.Item label="Finca">{record.farm}</Descriptions.Item>
      <Descriptions.Item label="Total aplicaciones">{record.applications}</Descriptions.Item>
      <Descriptions.Item label="Productos utilizados">{record.products}</Descriptions.Item>
      <Descriptions.Item label="Costo total">${record.cost.toLocaleString('es-CO')}</Descriptions.Item>
      <Descriptions.Item label="Costo promedio por aplicación">
        ${Math.round(record.cost / record.applications).toLocaleString('es-CO')}
      </Descriptions.Item>
    </Descriptions>
  );

  return (
    <div>
      <div style={{ marginBottom: 16 }}>
        <h1 style={{ margin: 0, color: '#2E7D32' }}>Reportes Inteligentes</h1>
        <p style={{ color: '#666', margin: 0 }}>
          Análisis y reportes del sistema AgriFlor
        </p>
      </div>

      <Card style={{ marginBottom: 16 }}>
        <Row gutter={16} align="middle">
          <Col span={6}>
            <Select
              value={reportType}
              onChange={setReportType}
              style={{ width: '100%' }}
              placeholder="Tipo de reporte"
            >
              <Option value="inventory">Inventario</Option>
              <Option value="purchases">Compras</Option>
              <Option value="applications">Aplicaciones</Option>
              <Option value="costs">Análisis de Costos</Option>
            </Select>
          </Col>
          <Col span={8}>
            <RangePicker
              value={dateRange}
              onChange={setDateRange}
              style={{ width: '100%' }}
            />
          </Col>
          <Col span={10}>
            <Button.Group>
              <Button icon={<BarChartOutlined />}>Ver Reporte</Button>
              <Button icon={<FileExcelOutlined />}>Excel</Button>
              <Button icon={<FilePdfOutlined />}>PDF</Button>
              <Button icon={<PrinterOutlined />}>Imprimir</Button>
            </Button.Group>
          </Col>
        </Row>
      </Card>

      {reportType === 'inventory' && (
        <>
          <Row gutter={16} style={{ marginBottom: 16 }}>
            <Col span={6}>
              <Card>
                <Statistic title="Total Productos" value={4310} valueStyle={{ color: '#3f8600' }} />
              </Card>
            </Col>
            <Col span={6}>
              <Card>
                <Statistic title="Valor Total" value={14980000} prefix="$" valueStyle={{ color: '#cf1322' }} />
              </Card>
            </Col>
            <Col span={6}>
              <Card>
                <Statistic title="Categorías" value={4} valueStyle={{ color: '#1890ff' }} />
              </Card>
            </Col>
            <Col span={6}>
              <Card>
                <Statistic title="Ubicaciones" value={8} valueStyle={{ color: '#722ed1' }} />
              </Card>
            </Col>
          </Row>
          <Card title="Reporte de Inventario por Categoría">
            <ResponsiveTable
              mobileColumns={inventoryMobileColumns}
              desktopColumns={inventoryDesktopColumns}
              dataSource={mockReportData.inventory}
              rowKey="category"
              expandedRowRender={inventoryExpandedRowRender}
              entityName="categorías"
              pagination={false}
            />
          </Card>
        </>
      )}

      {reportType === 'purchases' && (
        <Card title="Reporte de Compras">
          <ResponsiveTable
            mobileColumns={purchasesMobileColumns}
            desktopColumns={purchasesDesktopColumns}
            dataSource={mockReportData.purchases}
            rowKey="month"
            expandedRowRender={purchasesExpandedRowRender}
            entityName="períodos"
            pagination={false}
          />
        </Card>
      )}

      {reportType === 'applications' && (
        <Card title="Reporte de Aplicaciones">
          <ResponsiveTable
            mobileColumns={applicationsMobileColumns}
            desktopColumns={applicationsDesktopColumns}
            dataSource={mockReportData.applications}
            rowKey="farm"
            expandedRowRender={applicationsExpandedRowRender}
            entityName="fincas"
            pagination={false}
          />
        </Card>
      )}
    </div>
  );
};

export default Reports;