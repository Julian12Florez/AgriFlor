import React, { useState, useEffect, useRef } from 'react';
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { baseUnitsApi, handleApiError } from '../../services/api';
import ResponsiveTable from '../../components/ResponsiveTable';

const { Search } = Input;

interface BaseUnit {
  id: string;
  name: string;
  symbol: string;
  description?: string;
  status: string;
  createdAt?: string;
}

const BaseUnits: React.FC = () => {
  const queryClient = useQueryClient();
  const [isMobile, setIsMobile] = useState(false);
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingUnit, setEditingUnit] = useState<BaseUnit | null>(null);
  const [form] = Form.useForm();
  const [searchText, setSearchText] = useState('');

  // Ref to prevent double submissions (synchronous check)
  const isSubmittingRef = useRef(false);

  // Fetch base units from API
  const { data: unitsData, isLoading: unitsLoading } = useQuery({
    queryKey: ['baseUnits', searchText],
    queryFn: () => baseUnitsApi.list({
      search: searchText || undefined
    }),
  });

  const baseUnits = unitsData?.data || [];

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: any) => baseUnitsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['baseUnits'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Unidad base creada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al crear la unidad base', form);
    },
  });

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => baseUnitsApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['baseUnits'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingUnit(null);
      message.success('Unidad base actualizada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al actualizar la unidad base', form);
    },
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: string) => baseUnitsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['baseUnits'] });
      message.success('Unidad base eliminada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al eliminar unidad base');
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

  const handleEdit = (record: BaseUnit) => {
    setEditingUnit(record);
    form.setFieldsValue({
      name: record.name,
      symbol: record.symbol,
      description: record.description
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
      symbol: values.symbol,
      description: values.description || undefined,
      status: 'active',
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

  const mobileColumns: ColumnsType<BaseUnit> = [
    {
      title: 'Unidad Base',
      key: 'unit',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            {record.name}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            <Tag color="blue">{record.symbol}</Tag>
          </div>
        </div>
      ),
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      width: 80,
      render: (status: string) => (
        <Tag color={status === 'active' ? 'green' : 'red'} size="small">
          {status === 'active' ? 'Activo' : 'Inactivo'}
        </Tag>
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

  const desktopColumns: ColumnsType<BaseUnit> = [
    {
      title: 'Nombre',
      dataIndex: 'name',
      key: 'name',
      sorter: (a, b) => a.name.localeCompare(b.name),
    },
    {
      title: 'Símbolo',
      dataIndex: 'symbol',
      key: 'symbol',
      render: (symbol: string) => (
        <Tag color="blue" style={{ fontWeight: 'bold' }}>{symbol}</Tag>
      ),
    },
    {
      title: 'Descripción',
      dataIndex: 'description',
      key: 'description',
      render: (description: string) => description || '-',
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
            title="¿Está seguro de eliminar esta unidad base?"
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
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Unidades Base</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Gestiona las unidades base de medida (kg, litros, unidades, etc.)
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
          Nueva Unidad Base
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24}>
              <Search
                placeholder="Buscar unidades base..."
                allowClear
                value={searchText}
                onChange={(e) => setSearchText(e.target.value)}
              />
            </Col>
          </Row>
        </div>

        <ResponsiveTable
          mobileColumns={mobileColumns}
          desktopColumns={desktopColumns}
          dataSource={baseUnits}
          rowKey="id"
          loading={unitsLoading || createMutation.isPending || updateMutation.isPending || deleteMutation.isPending}
          entityName="unidades base"
          pagination={{
            total: baseUnits.length,
            pageSize: 10,
          }}
        />
      </Card>

      <Modal
        title={editingUnit ? 'Editar Unidad Base' : 'Nueva Unidad Base'}
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
            <Input placeholder="Ej: Kilogramos, Litros, Unidades" />
          </Form.Item>

          <Form.Item
            name="symbol"
            label="Símbolo"
            rules={[{ required: true, message: 'El símbolo es requerido' }]}
          >
            <Input placeholder="Ej: kg, L, u" maxLength={20} />
          </Form.Item>

          <Form.Item
            name="description"
            label="Descripción"
          >
            <Input.TextArea
              rows={3}
              placeholder="Descripción de la unidad (opcional)"
            />
          </Form.Item>

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

export default BaseUnits;
