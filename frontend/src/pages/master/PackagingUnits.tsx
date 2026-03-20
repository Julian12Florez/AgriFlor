import React, { useState, useEffect, useRef } from 'react';
import { Button, Input, Select, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Typography, InputNumber } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { packagingUnitsApi, baseUnitsApi, handleApiError } from '../../services/api';
import ResponsiveTable from '../../components/ResponsiveTable';

const { Text } = Typography;
const { Search } = Input;
const { Option } = Select;

interface PackagingUnit {
  id: string;
  name: string;
  baseQuantity: number;
  baseUnit: string;
  createdAt?: string;
}

const PackagingUnits: React.FC = () => {
  const queryClient = useQueryClient();
  const [isMobile, setIsMobile] = useState(false);
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingUnit, setEditingUnit] = useState<PackagingUnit | null>(null);
  const [form] = Form.useForm();
  const [searchText, setSearchText] = useState('');
  const [baseUnitFilter, setBaseUnitFilter] = useState<string | undefined>();

  // Ref to prevent double submissions (synchronous check)
  const isSubmittingRef = useRef(false);

  // Fetch packaging units from API
  const { data: unitsData, isLoading: unitsLoading } = useQuery({
    queryKey: ['packagingUnits', searchText, baseUnitFilter],
    queryFn: () => packagingUnitsApi.list({
      search: searchText || undefined,
      base_unit: baseUnitFilter || undefined
    }),
  });

  // Fetch base units for the form
  const { data: baseUnitsData } = useQuery({
    queryKey: ['baseUnits'],
    queryFn: () => baseUnitsApi.list(),
  });

  const packagingUnits = unitsData?.data || [];
  const baseUnits = baseUnitsData?.data || [];

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: any) => packagingUnitsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['packagingUnits'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Unidad de empaque creada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al crear la unidad de empaque', form);
    },
  });

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => packagingUnitsApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['packagingUnits'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingUnit(null);
      message.success('Unidad de empaque actualizada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al actualizar la unidad de empaque', form);
    },
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: string) => packagingUnitsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['packagingUnits'] });
      message.success('Unidad de empaque eliminada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al eliminar unidad de empaque');
    },
  });

  useEffect(() => {
    const checkScreenSize = () => {
      setIsMobile(window.innerWidth < 768);
    };

    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);
    return () => window.removeEventListener('resize', checkScreenSize);
  }, []);

  const handleEdit = (record: PackagingUnit) => {
    setEditingUnit(record);
    form.setFieldsValue({
      name: record.name,
      baseQuantity: record.baseQuantity,
      baseUnit: record.baseUnit
    });
    setIsModalVisible(true);
  };

  const handleDelete = (id: string) => {
    deleteMutation.mutate(id);
  };

  const handleSave = (values: any) => {
    // Prevent multiple submissions using ref (synchronous check)
    if (isSubmittingRef.current) {
      return;
    }
    isSubmittingRef.current = true;

    const unitData = {
      name: values.name,
      base_quantity: values.baseQuantity,
      base_unit: values.baseUnit,
    };

    const mutationOptions = {
      onSettled: () => {
        isSubmittingRef.current = false;
      }
    };

    if (editingUnit) {
      updateMutation.mutate({ id: editingUnit.id, data: unitData }, mutationOptions);
    } else {
      createMutation.mutate(unitData, mutationOptions);
    }
  };

  const mobileColumns: ColumnsType<PackagingUnit> = [
    {
      title: 'Unidad de Empaque',
      key: 'unit',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.name}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            {record.baseQuantity} {record.baseUnit}
          </div>
        </div>
      ),
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 100,
      fixed: 'right' as const,
      render: (_, record) => (
        <Space size="small">
          <Button
            type="link"
            icon={<EditOutlined />}
            onClick={() => handleEdit(record)}
            size="small"
          />
          <Popconfirm
            title="¿Eliminar?"
            onConfirm={() => handleDelete(record.id)}
            okText="Sí"
            cancelText="No"
          >
            <Button type="link" danger icon={<DeleteOutlined />} size="small" />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  const desktopColumns: ColumnsType<PackagingUnit> = [
    {
      title: 'Nombre',
      dataIndex: 'name',
      key: 'name',
      sorter: (a, b) => a.name.localeCompare(b.name),
    },
    {
      title: 'Cantidad Base',
      dataIndex: 'baseQuantity',
      key: 'baseQuantity',
      render: (baseQuantity: number) => baseQuantity.toLocaleString(),
      sorter: (a, b) => a.baseQuantity - b.baseQuantity,
    },
    {
      title: 'Unidad Base',
      dataIndex: 'baseUnit',
      key: 'baseUnit',
      render: (baseUnit: string) => (
        <Tag color="blue">{baseUnit}</Tag>
      ),
      filters: [
        { text: 'Kilogramos (kg)', value: 'kg' },
        { text: 'Litros', value: 'litros' },
        { text: 'Unidades', value: 'unidades' },
      ],
      onFilter: (value, record) => record.baseUnit === value,
    },
    {
      title: 'Acciones',
      key: 'actions',
      fixed: 'right' as const,
      width: 150,
      render: (_, record) => (
        <Space size="middle">
          <Button
            type="link"
            icon={<EditOutlined />}
            onClick={() => handleEdit(record)}
          >
            Editar
          </Button>
          <Popconfirm
            title="¿Está seguro de eliminar esta unidad de empaque?"
            onConfirm={() => handleDelete(record.id)}
            okText="Sí"
            cancelText="No"
          >
            <Button type="link" danger icon={<DeleteOutlined />}>
              Eliminar
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Unidades de Empaque</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Gestiona las unidades de empaque disponibles para productos
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingUnit(null);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nueva Unidad
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} md={12}>
              <Search
                placeholder="Buscar unidades de empaque..."
                allowClear
                value={searchText}
                onChange={(e) => setSearchText(e.target.value)}
              />
            </Col>
            <Col xs={24} sm={12} md={12}>
              <Select
                placeholder="Filtrar por unidad base"
                allowClear
                style={{ width: '100%' }}
                value={baseUnitFilter}
                onChange={setBaseUnitFilter}
              >
                {baseUnits
                  .filter((unit: any) => unit.status === 'active')
                  .map((unit: any) => (
                    <Option key={unit.id} value={unit.symbol}>
                      {unit.name} ({unit.symbol})
                    </Option>
                  ))}
              </Select>
            </Col>
          </Row>
        </div>

        <ResponsiveTable
          mobileColumns={mobileColumns}
          desktopColumns={desktopColumns}
          dataSource={packagingUnits}
          rowKey="id"
          loading={unitsLoading || createMutation.isPending || updateMutation.isPending || deleteMutation.isPending}
          entityName="unidades de empaque"
          pagination={{
            total: packagingUnits.length,
            pageSize: 10,
          }}
        />
      </Card>

      <Modal
        title={editingUnit ? 'Editar Unidad de Empaque' : 'Nueva Unidad de Empaque'}
        open={isModalVisible}
        onCancel={() => {
          setIsModalVisible(false);
          form.resetFields();
          setEditingUnit(null);
        }}
        footer={null}
        width={500}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSave}
        >
          <Form.Item
            name="name"
            label="Nombre de la Unidad"
            rules={[{ required: true, message: 'El nombre es requerido' }]}
          >
            <Input placeholder="Ej: Bulto, Saco, Galón" />
          </Form.Item>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="baseQuantity"
                label="Cantidad Base"
                rules={[{ required: true, message: 'La cantidad es requerida' }]}
              >
                <InputNumber
                  min={0}
                  step={0.01}
                  placeholder="Ej: 50"
                  style={{ width: '100%' }}
                />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="baseUnit"
                label="Unidad Base"
                rules={[{ required: true, message: 'La unidad base es requerida' }]}
              >
                <Select
                  placeholder="Seleccione la unidad"
                  loading={!baseUnitsData}
                  showSearch
                  optionFilterProp="children"
                >
                  {baseUnits
                    .filter((unit: any) => unit.status === 'active')
                    .map((unit: any) => (
                      <Option key={unit.id} value={unit.symbol}>
                        {unit.name} ({unit.symbol})
                      </Option>
                    ))}
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <div style={{ textAlign: 'right' }}>
            <Space>
              <Button onClick={() => {
                setIsModalVisible(false);
                form.resetFields();
                setEditingUnit(null);
              }}>
                Cancelar
              </Button>
              <Button
                type="primary"
                htmlType="submit"
                loading={createMutation.isPending || updateMutation.isPending}
                disabled={createMutation.isPending || updateMutation.isPending}
              >
                {editingUnit ? 'Actualizar' : 'Crear'}
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default PackagingUnits;
