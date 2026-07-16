import React, { useState, useEffect, useRef } from 'react';
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, DatePicker, InputNumber, Badge, Descriptions, Divider, List, Typography, Drawer } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, ExportOutlined, EnvironmentOutlined, CheckCircleOutlined, ClockCircleOutlined, InboxOutlined, MinusCircleOutlined, PlusCircleOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import dayjs from 'dayjs';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { outputsApi, productsApi, locationsApi, ordersApi, usersApi, outputTypesApi, farmLotsApi, handleApiError } from '../../services/api';
import { usePermissions } from '../../hooks/usePermissions';

// Roles restringidos a su(s) ubicación(es): supervisor (encargado de finca) y farm
// (operario de finca). Cualquier otro rol elige/ve todas las ubicaciones. Se usa lista de
// restringidos (no de globales) para ser fail-safe: un rol no contemplado ve todo.
const LOCATION_SCOPED_ROLES = ['supervisor', 'farm'];

const { Text, Title } = Typography;
import type { ProductOutput, OutputProduct, Product, PackagingUnit } from '../../data/types';

// Interfaces importadas desde types.ts

const { Search } = Input;
const { Option } = Select;
const { TextArea } = Input;

const Outputs: React.FC = () => {
  const [isMobile, setIsMobile] = useState(false);
  const queryClient = useQueryClient();

  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingOutput, setEditingOutput] = useState<any | null>(null);
  const [searchText, setSearchText] = useState('');
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs, dayjs.Dayjs] | null>(null);
  const [selectedLocationId, setSelectedLocationId] = useState<string | undefined>();
  const [selectedOriginLocationId, setSelectedOriginLocationId] = useState<string | undefined>();
  const [selectedDestinationLocationId, setSelectedDestinationLocationId] = useState<string | undefined>();
  const [selectedOutputTypeId, setSelectedOutputTypeId] = useState<string | undefined>();
  const [productsForOutputs, setProductsForOutputs] = useState<any[]>([]);
  const [availableFarmLots, setAvailableFarmLots] = useState<any[]>([]);
  const [isReadOnly, setIsReadOnly] = useState(false);
  const [form] = Form.useForm();

  // Ref to prevent double submissions (synchronous check)
  const isSubmittingRef = useRef(false);

  // Fetch outputs from backend
  const { data: outputsData, isLoading: outputsLoading } = useQuery({
    queryKey: ['outputs', searchText, statusFilter, dateRange?.[0]?.format('YYYY-MM-DD'), dateRange?.[1]?.format('YYYY-MM-DD')],
    queryFn: () => outputsApi.list({
      search: searchText || undefined,
      status: statusFilter || undefined,
      start_date: dateRange?.[0]?.format('YYYY-MM-DD'),
      end_date: dateRange?.[1]?.format('YYYY-MM-DD'),
    }),
  });

  // Fetch locations
  const { data: locationsData } = useQuery({
    queryKey: ['locations'],
    queryFn: () => locationsApi.list({ per_page: 999 }),
  });

  // Fetch products (per_page alto para traer todos)
  const { data: productsData } = useQuery({
    queryKey: ['products', 'all-for-select'],
    queryFn: () => productsApi.list({ per_page: 9999 }),
  });

  // Fetch technical orders
  const { data: ordersData } = useQuery({
    queryKey: ['orders'],
    queryFn: () => ordersApi.list(),
  });

  // Fetch users
  const { data: usersData } = useQuery({
    queryKey: ['users-simple'],
    queryFn: () => usersApi.listSimple(),
  });

  // Fetch output types
  const { data: outputTypesData } = useQuery({
    queryKey: ['output-types'],
    queryFn: () => outputTypesApi.list({ status: 'active' }),
  });

  const outputs = outputsData?.data || [];
  const availableLocations = locationsData?.data || [];

  // Aislamiento por ubicación: el responsable solo puede elegir como ORIGEN de la salida
  // una de las ubicaciones que tiene asignadas. Los roles globales pueden elegir cualquiera.
  const { user, getRoleName, isAdmin } = usePermissions();
  const canViewAllLocations = isAdmin() || !LOCATION_SCOPED_ROLES.includes(getRoleName());
  const availableProducts = productsData?.data || [];
  const availableOrders = ordersData?.data || [];
  const availableUsers = usersData?.data || [];

  // Fix: Ensure availableOutputTypes is always an array with robust checking
  const availableOutputTypes = React.useMemo(() => {
    if (!outputTypesData) return [];
    if (Array.isArray(outputTypesData.data)) return outputTypesData.data;
    if (outputTypesData.data && Array.isArray(outputTypesData.data.data)) return outputTypesData.data.data;
    return [];
  }, [outputTypesData]);

  // Mutation for creating outputs
  const createOutputMutation = useMutation({
    mutationFn: (data: any) => outputsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['outputs'] });
      queryClient.invalidateQueries({ queryKey: ['inventory'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingOutput(null);
      setSelectedOriginLocationId(undefined);
      setSelectedDestinationLocationId(undefined);
      setSelectedOutputTypeId(undefined);
      setProductsForOutputs([]);
      setAvailableFarmLots([]);
      message.success('Salida creada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al crear la salida', form);
    },
  });

  // Mutation for updating outputs
  const updateOutputMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => outputsApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['outputs'] });
      queryClient.invalidateQueries({ queryKey: ['inventory'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingOutput(null);
      setSelectedOriginLocationId(undefined);
      setSelectedDestinationLocationId(undefined);
      setSelectedOutputTypeId(undefined);
      setProductsForOutputs([]);
      setAvailableFarmLots([]);
      message.success('Salida actualizada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al actualizar la salida', form);
    },
  });

  // Mutation for approving outputs (triggers FIFO)
  const approveOutputMutation = useMutation({
    mutationFn: (id: string) => outputsApi.approve(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['outputs'] });
      queryClient.invalidateQueries({ queryKey: ['inventory'] });
      message.success('Salida aprobada exitosamente. Inventario actualizado con FIFO.');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al aprobar la salida');
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

  const getRoleLabel = (role: string): string => {
    const labels: Record<string, string> = {
      admin: 'Administrador',
      agronomist: 'Agrónomo',
      warehouse: 'Bodeguero',
      supervisor: 'Supervisor',
      farm: 'Operario de Finca',
    };
    return labels[role] || role;
  };

  const getStatusColor = (status: string) => {
    const colors = {
      pending: 'blue',
      partial: 'orange',
      completed: 'green'
    };
    return colors[status as keyof typeof colors] || 'default';
  };

  const getStatusText = (status: string) => {
    const texts = {
      pending: 'Disponible para Recepción',
      partial: 'Recepción Iniciada',
      completed: 'Completada'
    };
    return texts[status as keyof typeof texts] || status;
  };

  const getStatusIcon = (status: string) => {
    const icons = {
      pending: <ClockCircleOutlined />,
      partial: <InboxOutlined />,
      completed: <CheckCircleOutlined />
    };
    return icons[status as keyof typeof icons] || <ClockCircleOutlined />;
  };

  const getEquivalenceText = (productId: string, quantity: number, unit: string) => {
    const product = availableProducts.find((p: any) => p.id === productId);
    if (!product || !product.packaging_units || quantity <= 0) {
      return null;
    }

    // Look for a packaging unit that matches the quantity
    const packagingUnit = product.packaging_units.find((pu: any) =>
      pu.base_unit === unit && pu.base_quantity > 1
    );

    if (!packagingUnit) {
      return null;
    }

    // Calculate equivalence
    const packagingQuantity = Math.floor(quantity / packagingUnit.base_quantity);
    const remainingQuantity = quantity % packagingUnit.base_quantity;

    if (packagingQuantity > 0) {
      if (remainingQuantity > 0) {
        return `${packagingQuantity} ${packagingUnit.name}${packagingQuantity > 1 ? 's' : ''} + ${remainingQuantity} ${unit} = ${quantity} ${unit}`;
      } else {
        return `${packagingQuantity} ${packagingUnit.name}${packagingQuantity > 1 ? 's' : ''} × ${packagingUnit.base_quantity} ${unit} = ${quantity} ${unit}`;
      }
    }

    return null;
  };

  const handleEdit = async (record: ProductOutput) => {
    setEditingOutput(record);

    // Check if output is in a state that prevents editing
    const canEdit = record.status === 'pending';
    setIsReadOnly(!canEdit);

    // Load products for the origin location
    let loadedProducts: any[] = [];
    if (record.originLocation?.id) {
      setSelectedOriginLocationId(record.originLocation.id);
      try {
        const response = await productsApi.getForOutputs({ location_id: record.originLocation.id });
        loadedProducts = response.data || [];
        setProductsForOutputs(loadedProducts);
      } catch (error: any) {
        message.error(`Error al cargar productos: ${error.message}`);
      }
    }

    // Load farm lots for destination if it's a farm and output type requires lots
    let farmLotIds: string[] = [];
    if (record.destinationLocation?.id) {
      setSelectedDestinationLocationId(record.destinationLocation.id);

      // Load farm lots if destination is a farm
      if (record.destinationLocation.type === 'farm') {
        try {
          const response = await farmLotsApi.getByLocation(record.destinationLocation.id);
          setAvailableFarmLots(response.data || []);

          // Extract lot IDs from the record if they exist
          if (record.farmLots && Array.isArray(record.farmLots)) {
            farmLotIds = record.farmLots.map((lot: any) => lot.id);
          }
        } catch (error: any) {
          message.error(`Error al cargar lotes: ${error.message}`);
        }
      }
    }

    // Set the output type ID if it exists
    if (record.outputType?.id) {
      setSelectedOutputTypeId(record.outputType.id);
    }

    // Map products to labelInValue format
    const mappedProducts = record.products.map(p => {
      const inventoryItem = loadedProducts.find((item: any) => item.product_id === p.productId);

      return {
        productId: inventoryItem ? {
          value: inventoryItem.inventory_id,
          label: inventoryItem.display_label
        } : p.productId,
        realProductId: p.productId,
        brandId: p.brandId,
        inventoryId: inventoryItem?.inventory_id,
        maxQuantity: inventoryItem?.base_quantity || inventoryItem?.quantity,
        baseQuantity: inventoryItem?.base_quantity || inventoryItem?.quantity,
        baseUnit: inventoryItem?.base_unit || inventoryItem?.unit,
        quantityRequested: p.quantityRequested,
        quantityDelivered: p.quantityDelivered,
        batchNumber: p.batchNumber,
        unit: inventoryItem?.unit
      };
    });

    form.setFieldsValue({
      outputTypeId: record.outputType?.id,
      orderNumber: record.orderNumber,
      outputDate: dayjs(record.outputDate),
      originLocationId: record.originLocation?.id,
      destinationLocationId: record.destinationLocation?.id,
      farmLotIds: farmLotIds,
      responsibleUser: record.responsibleUserDetails ? {
        value: record.responsibleUser,
        label: `${record.responsibleUserDetails.name} - ${getRoleLabel(record.responsibleUserDetails.role || 'Usuario')}`
      } : record.responsibleUser,
      observations: record.observations,
      products: mappedProducts
    });
    setIsModalVisible(true);
  };

  // Mutation for deleting outputs
  const deleteOutputMutation = useMutation({
    mutationFn: (id: string) => outputsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['outputs'] });
      queryClient.invalidateQueries({ queryKey: ['inventory'] });
      message.success('Salida eliminada exitosamente');
    },
    onError: (error: any) => {
      message.error(`Error al eliminar la salida: ${error.message}`);
    },
  });

  const handleDelete = (id: string) => {
    deleteOutputMutation.mutate(id);
  };

  const handleOrderChange = (orderId: string) => {
    // When an order is selected, we could auto-fill products from that order
    // For now, just update the form field
    form.setFieldValue('orderNumber', orderId);
  };

  const handleOriginLocationChange = async (locationId: string) => {
    setSelectedOriginLocationId(locationId);
    // Fetch products available for outputs in this location
    try {
      const response = await productsApi.getForOutputs({ location_id: locationId });
      setProductsForOutputs(response.data || []);
    } catch (error: any) {
      message.error(`Error al cargar productos: ${error.message}`);
      setProductsForOutputs([]);
    }

    // If consumption type is selected, auto-update destination to match origin
    const selectedType = availableOutputTypes.find((t: any) => t.id === selectedOutputTypeId);
    if (selectedType && selectedType.code === 'consumption') {
      form.setFieldValue('destinationLocationId', locationId);
      setSelectedDestinationLocationId(locationId);

      // Load farm lots for the destination (same as origin for consumption)
      const location = availableLocations.find((loc: any) => loc.id === locationId);
      if (location && location.type === 'farm') {
        try {
          const response = await farmLotsApi.getByLocation(locationId);
          setAvailableFarmLots(response.data || []);
        } catch (error: any) {
          message.error(`Error al cargar lotes: ${error.message}`);
          setAvailableFarmLots([]);
        }
      } else {
        setAvailableFarmLots([]);
      }
    }
  };

  const handleDestinationLocationChange = async (locationId: string) => {
    setSelectedDestinationLocationId(locationId);
    // If destination is a farm, fetch its lots
    const location = availableLocations.find((loc: any) => loc.id === locationId);
    if (location && location.type === 'farm') {
      try {
        const response = await farmLotsApi.getByLocation(locationId);
        setAvailableFarmLots(response.data || []);
      } catch (error: any) {
        message.error(`Error al cargar lotes: ${error.message}`);
        setAvailableFarmLots([]);
      }
    } else {
      setAvailableFarmLots([]);
    }
  };

  const handleOutputTypeChange = (outputTypeId: string) => {
    setSelectedOutputTypeId(outputTypeId);

    const selectedType = availableOutputTypes.find((t: any) => t.id === outputTypeId);

    // If remanente type, clear invalid locations (origin must be farm, destination must be warehouse)
    if (selectedType && selectedType.code === 'remanente') {
      const currentOriginId = form.getFieldValue('originLocationId');
      const currentDestId = form.getFieldValue('destinationLocationId');
      const originLoc = availableLocations.find((loc: any) => loc.id === currentOriginId);
      const destLoc = availableLocations.find((loc: any) => loc.id === currentDestId);

      if (originLoc && originLoc.type !== 'farm') {
        form.setFieldValue('originLocationId', undefined);
        setSelectedOriginLocationId('');
        setAvailableProducts([]);
      }
      if (destLoc && destLoc.type !== 'warehouse') {
        form.setFieldValue('destinationLocationId', undefined);
        setSelectedDestinationLocationId('');
      }
    }

    // If consumption type, auto-set destination to origin
    if (selectedType && selectedType.code === 'consumption') {
      const originLocationId = form.getFieldValue('originLocationId');
      if (originLocationId) {
        form.setFieldValue('destinationLocationId', originLocationId);
        setSelectedDestinationLocationId(originLocationId);

        // Load farm lots for the destination (same as origin for consumption)
        const location = availableLocations.find((loc: any) => loc.id === originLocationId);
        if (location && location.type === 'farm') {
          farmLotsApi.getByLocation(originLocationId)
            .then((response) => {
              setAvailableFarmLots(response.data || []);
            })
            .catch((error: any) => {
              message.error(`Error al cargar lotes: ${error.message}`);
              setAvailableFarmLots([]);
            });
        }
      }
    }
  };

  const validateQuantity = (requestedQuantity: number, deliveredQuantity: number) => {
    const maxAllowed = requestedQuantity * 1.05; // 5% adicional permitido
    return deliveredQuantity <= maxAllowed;
  };

  const handleSave = (values: any) => {
    // Prevent multiple submissions using ref (synchronous check)
    if (isSubmittingRef.current) {
      return;
    }

    // Validate 5% rule
    if (values.products) {
      for (const product of values.products) {
        if (!validateQuantity(product.quantityRequested, product.quantityDelivered)) {
          const maxAllowed = product.quantityRequested * 1.05;
          message.error(`La cantidad entregada no puede exceder ${maxAllowed.toFixed(2)} (5% adicional permitido)`);
          return;
        }
      }
    }

    isSubmittingRef.current = true;

    // Format data for backend API (snake_case)
    const outputData = {
      output_type_id: values.outputTypeId,
      output_date: values.outputDate.format('YYYY-MM-DD'),
      origin_location_id: values.originLocationId,
      destination_location_id: values.destinationLocationId,
      responsible_user: values.responsibleUser?.value || values.responsibleUser || undefined,
      observations: values.observations || undefined,
      farm_lot_ids: values.farmLotIds || undefined,
      products: values.products?.map((p: any) => {
        // IMPORTANTE: Usar los datos del inventario seleccionado (p.*), NO del producto general
        // El inventario tiene el brand_id, unit y batch_number específicos del stock disponible
        return {
          product_id: p.realProductId || p.productId?.value || p.productId,
          brand_id: p.brandId,  // ✅ Usar brand_id del inventario seleccionado
          quantity_requested: p.quantityRequested,
          quantity_delivered: p.quantityDelivered,
          unit: p.baseUnit || p.unit || 'kg',  // ✅ Usar unidad del inventario seleccionado
          batch_number: p.batchNumber || undefined,  // ✅ Incluir batch_number si existe
        };
      }) || []
    };

    const mutationOptions = {
      onSettled: () => {
        isSubmittingRef.current = false;
      }
    };

    // Call backend API to create or update output
    if (editingOutput) {
      updateOutputMutation.mutate({ id: editingOutput.id, data: outputData }, mutationOptions);
    } else {
      createOutputMutation.mutate(outputData, mutationOptions);
    }
  };

  // La búsqueda y el estado se resuelven en el backend (por producto, código, finca, etc.).
  // No se filtra por texto en cliente para no ocultar resultados que matchean por producto/finca.
  const filteredOutputs = outputs.filter((output: any) => {
    const matchesStatus = !statusFilter || output.status === statusFilter;
    return matchesStatus;
  });

  const mobileColumns: ColumnsType<ProductOutput> = [
    {
      title: 'Salida',
      key: 'output',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: '14px', marginBottom: 4 }}>
            <ExportOutlined style={{ marginRight: 8, color: '#1890ff' }} />
            {record.outputNumber}
          </div>
          <div style={{ fontSize: '12px', color: '#666' }}>
            <Tag color={getStatusColor(record.status)} size="small" icon={getStatusIcon(record.status)}>
              {getStatusText(record.status)}
            </Tag>
            <span style={{ marginLeft: 8 }}>{record.products.length} productos</span>
          </div>
        </div>
      ),
    },
    {
      title: 'Costo',
      dataIndex: 'totalCost',
      key: 'totalCost',
      width: 100,
      render: (cost: number) => (
        <span style={{ fontWeight: 500, fontSize: '14px' }}>
          ${cost.toLocaleString('es-CO')}
        </span>
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

  const desktopColumns: ColumnsType<ProductOutput> = [
    {
      title: 'Salida',
      key: 'output',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: 14, marginBottom: 4 }}>
            <ExportOutlined style={{ marginRight: 8, color: '#1890ff' }} />
            {record.outputNumber}
          </div>
          <div style={{ color: '#666', fontSize: 12 }}>
            {record.orderNumber} - {record.orderName}
          </div>
        </div>
      ),
    },
    {
      title: 'Destino',
      key: 'destination',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: 14 }}>
            <EnvironmentOutlined style={{ marginRight: 4 }} />
            {record.farmName}
          </div>
          <div style={{ color: '#666', fontSize: 12 }}>
            {dayjs(record.outputDate).format('DD/MM/YYYY')}
          </div>
        </div>
      ),
    },
    {
      title: 'Productos',
      dataIndex: 'products',
      key: 'products',
      render: (products: OutputProduct[]) => (
        <div>
          <div style={{ fontSize: 14, marginBottom: 4 }}>
            {products.length} producto{products.length !== 1 ? 's' : ''}
          </div>
          {products.slice(0, 2).map((product, index) => (
            <div key={index} style={{ color: '#666', fontSize: 12 }}>
              • {product.productName} ({product.quantityDelivered}/{product.quantityRequested} {product.unit})
            </div>
          ))}
          {products.length > 2 && (
            <div style={{ color: '#999', fontSize: 12 }}>
              +{products.length - 2} más...
            </div>
          )}
        </div>
      ),
    },
    {
      title: 'Responsable',
      dataIndex: 'responsibleUser',
      key: 'responsibleUser',
      render: (text: string, record: any) => (
        <span style={{ color: '#2E7D32' }}>
          {record.responsibleUserDetails?.name || text || 'Sin asignar'}
        </span>
      ),
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={getStatusColor(status)} icon={getStatusIcon(status)}>
          {getStatusText(status)}
        </Tag>
      ),
      filters: [
        { text: 'Disponible para Recepción', value: 'pending' },
        { text: 'Recepción Iniciada', value: 'partial' },
        { text: 'Completada', value: 'completed' },
      ],
      onFilter: (value, record) => record.status === value,
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
            title="¿Está seguro de eliminar esta salida?"
            description="Esta acción no se puede deshacer"
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

  const expandedRowRender = (record: ProductOutput) => (
    <Descriptions size="small" column={1}>
      <Descriptions.Item label="Orden técnica">{record.orderNumber} - {record.orderName}</Descriptions.Item>
      <Descriptions.Item label="Destino">{record.farmName}</Descriptions.Item>
      <Descriptions.Item label="Fecha de salida">
        {dayjs(record.outputDate).format('DD/MM/YYYY')}
      </Descriptions.Item>
      <Descriptions.Item label="Responsable">
        {record.responsibleUserDetails?.name || record.responsibleUser || 'Sin asignar'}
      </Descriptions.Item>
      <Descriptions.Item label="Productos">
        {record.products.map((product, index) => (
          <div key={index} style={{ marginBottom: 4 }}>
            <strong>{product.productName}</strong> - {product.brandName}
            <br />
            <span style={{ color: '#666', fontSize: 12 }}>
              Solicitado: {product.quantityRequested} {product.unit} |
              Entregado: {product.quantityDelivered} {product.unit}
              {product.batchNumber && ` | Lote: ${product.batchNumber}`}
            </span>
          </div>
        ))}
      </Descriptions.Item>
      {record.observations && (
        <Descriptions.Item label="Observaciones">{record.observations}</Descriptions.Item>
      )}
    </Descriptions>
  );

  const renderFormContent = () => (
    <Form
      form={form}
      layout="vertical"
      onFinish={handleSave}
      disabled={isReadOnly}
    >
      {isReadOnly && (
        <div style={{ marginBottom: 16 }}>
          <Tag color="warning" style={{ padding: '8px 12px', fontSize: '14px', width: '100%', textAlign: 'center' }}>
            ⚠️ Esta salida ya tiene recepción iniciada o completada y no puede ser editada
          </Tag>
        </div>
      )}
      <Row gutter={isMobile ? [0, 16] : 16}>
        <Col xs={24} sm={24} md={12}>
          <Form.Item
            name="outputTypeId"
            label="Tipo de Salida"
            rules={[{ required: true, message: 'El tipo de salida es requerido' }]}
          >
            <Select
              placeholder="Seleccione el tipo de salida"
              onChange={handleOutputTypeChange}
            >
              {availableOutputTypes.map((type: any) => (
                <Option key={type.id} value={type.id}>
                  {type.name}
                </Option>
              ))}
            </Select>
          </Form.Item>
        </Col>
        <Col xs={24} sm={24} md={12}>
          <Form.Item
            name="orderNumber"
            label="Orden Técnica (Opcional)"
          >
            <Select
              placeholder="Seleccione la orden técnica (opcional)"
              onChange={handleOrderChange}
              allowClear
            >
              {availableOrders.map(order => (
                <Option key={order.id} value={order.id}>
                  {order.id} - {order.name} ({order.status === 'approved' ? '✅ Aprobada' : '⏳ Borrador'})
                </Option>
              ))}
            </Select>
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={isMobile ? [0, 16] : 16}>
        <Col xs={24} sm={12} md={8}>
          <Form.Item
            name="outputDate"
            label="Fecha de Salida"
            rules={[{ required: true, message: 'La fecha de salida es requerida' }]}
          >
            <DatePicker
              style={{ width: '100%' }}
              format="DD/MM/YYYY"
              placeholder="Seleccione la fecha"
            />
          </Form.Item>
        </Col>
        <Col xs={24} sm={12} md={8}>
          <Form.Item
            name="originLocationId"
            label="Ubicación Origen"
            rules={[{ required: true, message: 'La ubicación origen es requerida' }]}
          >
            <Select
              placeholder="Seleccione ubicación origen"
              onChange={handleOriginLocationChange}
            >
              {availableLocations
                .filter(loc => {
                  if (loc.status !== 'active') return false;
                  // Un responsable solo puede elegir como origen una de sus ubicaciones.
                  if (!canViewAllLocations && loc.responsible_user_id !== user?.id) return false;
                  const selectedType = availableOutputTypes.find((t: any) => t.id === selectedOutputTypeId);
                  if (selectedType && selectedType.code === 'remanente') return loc.type === 'farm';
                  return true;
                })
                .map(location => (
                  <Option key={location.id} value={location.id}>
                    {location.type === 'farm' ? '🏡' : '🏢'} {location.name} ({location.municipality})
                  </Option>
                ))}
            </Select>
          </Form.Item>
        </Col>
        <Col xs={24} sm={12} md={8}>
          <Form.Item
            name="destinationLocationId"
            label="Ubicación Destino"
            rules={[{ required: true, message: 'La ubicación destino es requerida' }]}
            tooltip={
              (() => {
                const selectedType = availableOutputTypes.find((t: any) => t.id === selectedOutputTypeId);
                if (selectedType && selectedType.code === 'consumption')
                  return 'Para consumos, el destino se establece automáticamente igual al origen';
                if (selectedType && selectedType.code === 'remanente')
                  return 'Para remanentes, el destino debe ser una bodega';
                return undefined;
              })()
            }
          >
            <Select
              placeholder="Seleccione ubicación destino"
              onChange={handleDestinationLocationChange}
              disabled={(() => {
                const selectedType = availableOutputTypes.find((t: any) => t.id === selectedOutputTypeId);
                return selectedType && selectedType.code === 'consumption';
              })()}
            >
              {availableLocations
                .filter(loc => {
                  if (loc.status !== 'active') return false;
                  const selectedType = availableOutputTypes.find((t: any) => t.id === selectedOutputTypeId);
                  if (selectedType && selectedType.code === 'remanente') return loc.type === 'warehouse';
                  return true;
                })
                .map(location => (
                  <Option key={location.id} value={location.id}>
                    {location.type === 'farm' ? '🏡' : '🏢'} {location.name} ({location.municipality})
                  </Option>
                ))}
            </Select>
          </Form.Item>
        </Col>
      </Row>

      <Row gutter={isMobile ? [0, 16] : 16}>
        <Col xs={24}>
          <Form.Item
            name="responsibleUser"
            label="Responsable"
            rules={[{ required: true, message: 'El responsable es requerido' }]}
          >
            <Select
              placeholder="Seleccione el responsable"
              showSearch
              labelInValue
              optionFilterProp="children"
              filterOption={(input, option) =>
                (option?.children?.toString() ?? '').toLowerCase().includes(input.toLowerCase())
              }
            >
              {availableUsers
                .filter((user: any) => user.status === 'active')
                .map((user: any) => (
                  <Option key={user.id} value={user.id}>
                    {user.name} - {getRoleLabel(user.role)}
                  </Option>
                ))}
            </Select>
          </Form.Item>
        </Col>
      </Row>

      {/* Selector de lotes - Solo visible si el tipo de salida requiere lotes */}
      {(() => {
        const selectedType = availableOutputTypes.find((t: any) => t.id === selectedOutputTypeId);
        if (selectedType && selectedType.requires_lots) {
          return (
            <Row gutter={isMobile ? [0, 16] : 16}>
              <Col xs={24}>
                <Form.Item
                  name="farmLotIds"
                  label="Lotes de Finca"
                  rules={[{ required: true, message: 'Debe seleccionar al menos un lote' }]}
                >
                  <Select
                    mode="multiple"
                    placeholder="Seleccione los lotes de la finca"
                    disabled={!selectedDestinationLocationId || availableFarmLots.length === 0}
                    notFoundContent={
                      !selectedDestinationLocationId
                        ? "Primero seleccione una finca como destino"
                        : availableFarmLots.length === 0
                        ? "No hay lotes disponibles en esta finca"
                        : null
                    }
                  >
                    {availableFarmLots.map((lot: any) => (
                      <Option key={lot.id} value={lot.id}>
                        {lot.name} {lot.area ? `- ${lot.area} ${lot.area_unit}` : ''}
                      </Option>
                    ))}
                  </Select>
                </Form.Item>
              </Col>
            </Row>
          );
        }
        return null;
      })()}

      <Divider>Productos a Entregar</Divider>

      <Form.List name="products">
        {(fields, { add, remove }) => (
          <>
            {fields.map(({ key, name, ...restField }) => (
              <Card key={key} size="small" style={{ marginBottom: 16 }}>
                {isMobile ? (
                  <div>
                    <Row gutter={[0, 12]}>
                      <Col span={24}>
                        <Form.Item
                          {...restField}
                          name={[name, 'productId']}
                          label="Producto"
                          rules={[{ required: true, message: 'Seleccione un producto' }]}
                        >
                          <Select
                            placeholder="Seleccione producto (ordenado por vencimiento)"
                            showSearch
                            labelInValue
                            optionFilterProp="children"
                            filterOption={(input, option) =>
                              (option?.children?.toString() ?? '').toLowerCase().includes(input.toLowerCase())
                            }
                            onChange={(selected) => {
                              // selected is {value: inventory_id, label: display_label}
                              const item = productsForOutputs.find((p: any) => p.inventory_id === selected.value);
                              if (item) {
                                const currentProducts = form.getFieldValue('products') || [];
                                currentProducts[name] = {
                                  ...currentProducts[name],
                                  productId: selected, // Store the full object {value, label}
                                  realProductId: item.product_id,
                                  brandId: item.brand_id,
                                  unit: item.unit,
                                  batchNumber: item.batch_number,
                                  inventoryId: item.inventory_id,
                                  maxQuantity: item.base_quantity || item.quantity,
                                  baseQuantity: item.base_quantity || item.quantity,
                                  baseUnit: item.base_unit || item.unit,
                                  expirationDate: item.expiration_date
                                };
                                form.setFieldValue('products', currentProducts);
                              }
                            }}
                            disabled={!selectedOriginLocationId}
                            notFoundContent={
                              !selectedOriginLocationId
                                ? "Primero seleccione la ubicación origen"
                                : productsForOutputs.length === 0
                                ? "No hay productos disponibles"
                                : null
                            }
                          >
                            {productsForOutputs.map((item: any) => (
                              <Option key={item.inventory_id} value={item.inventory_id}>
                                {isMobile ? item.short_label : item.display_label}
                              </Option>
                            ))}
                          </Select>
                        </Form.Item>
                        <Form.Item
                          {...restField}
                          name={[name, 'brandId']}
                          hidden
                        >
                          <Input />
                        </Form.Item>
                        <Form.Item
                          {...restField}
                          name={[name, 'unit']}
                          hidden
                        >
                          <Input />
                        </Form.Item>
                        <Form.Item
                          {...restField}
                          name={[name, 'inventoryId']}
                          hidden
                        >
                          <Input />
                        </Form.Item>
                        <Form.Item
                          {...restField}
                          name={[name, 'maxQuantity']}
                          hidden
                        >
                          <Input />
                        </Form.Item>
                        <Form.Item
                          {...restField}
                          name={[name, 'expirationDate']}
                          hidden
                        >
                          <Input />
                        </Form.Item>
                      </Col>
                      <Col span={12}>
                        <Form.Item
                          {...restField}
                          name={[name, 'quantityRequested']}
                          label="Solicitado"
                          rules={[
                            { required: true, message: 'Cantidad requerida' },
                            ({ getFieldValue }) => ({
                              validator(_, value) {
                                const products = getFieldValue('products') || [];
                                const currentProduct = products[name];
                                const maxStock = currentProduct?.maxQuantity || 0;

                                if (!value || value <= maxStock) {
                                  return Promise.resolve();
                                }
                                return Promise.reject(new Error(`Stock disponible: ${maxStock.toFixed(2)} ${currentProduct?.baseUnit || ''}`));
                              },
                            }),
                          ]}
                        >
                          <InputNumber
                            min={0}
                            precision={2}
                            style={{ width: '100%' }}
                            placeholder="0"
                          />
                        </Form.Item>
                      </Col>
                      <Col span={12}>
                        <Form.Item
                          {...restField}
                          name={[name, 'quantityDelivered']}
                          label="Entregado (máx +5%)"
                          rules={[
                            { required: true, message: 'Cantidad requerida' },
                            ({ getFieldValue }) => ({
                              validator(_, value) {
                                const products = getFieldValue('products') || [];
                                const currentProduct = products[name];
                                const requestedQty = currentProduct?.quantityRequested || 0;
                                const maxStock = currentProduct?.maxQuantity || 0;
                                const maxAllowed = Math.min(requestedQty * 1.05, maxStock);

                                if (!value || value <= maxAllowed) {
                                  return Promise.resolve();
                                }
                                if (value > maxStock) {
                                  return Promise.reject(new Error(`Stock disponible: ${maxStock.toFixed(2)} ${currentProduct?.baseUnit || ''}`));
                                }
                                return Promise.reject(new Error(`Máximo permitido: ${maxAllowed.toFixed(2)}`));
                              },
                            }),
                          ]}
                        >
                          <InputNumber
                            min={0}
                            precision={2}
                            style={{ width: '100%' }}
                            placeholder="0"
                          />
                        </Form.Item>
                      </Col>
                      <Col span={6}>
                        <Form.Item
                          dependencies={[[name, 'productId']]}
                          label="Unidad Base"
                        >
                          {({ getFieldValue }) => {
                            const products = getFieldValue('products') || [];
                            const currentProduct = products[name];
                            const productId = currentProduct?.productId;
                            const product = availableProducts.find((p: any) => p.id === productId);
                            const unit = product?.base_unit || '';
                            return (
                              <Input
                                value={unit}
                                disabled
                                style={{
                                  width: '100%',
                                  backgroundColor: '#f5f5f5',
                                  color: '#666',
                                  textAlign: 'center',
                                  fontWeight: 'bold'
                                }}
                                placeholder="-"
                              />
                            );
                          }}
                        </Form.Item>
                      </Col>
                      <Col span={14}>
                        <Form.Item
                          {...restField}
                          name={[name, 'batchNumber']}
                          label="Lote"
                        >
                          <Input placeholder="Número de lote" />
                        </Form.Item>
                      </Col>
                      <Col span={4}>
                        <Form.Item label=" ">
                          <Button
                            type="text"
                            danger
                            icon={<MinusCircleOutlined />}
                            onClick={() => remove(name)}
                            size="small"
                          />
                        </Form.Item>
                      </Col>
                    </Row>
                  </div>
                ) : (
                  <Row gutter={16} align="bottom">
                    <Col span={10}>
                      <Form.Item
                        {...restField}
                        name={[name, 'productId']}
                        label="Producto (ordenado por vencimiento)"
                        rules={[{ required: true, message: 'Seleccione un producto' }]}
                      >
                        <Select
                          placeholder="Seleccione producto"
                          showSearch
                          labelInValue
                          optionFilterProp="children"
                          filterOption={(input, option) =>
                            (option?.children?.toString() ?? '').toLowerCase().includes(input.toLowerCase())
                          }
                          onChange={(selected) => {
                            // selected is {value: inventory_id, label: display_label}
                            const item = productsForOutputs.find((p: any) => p.inventory_id === selected.value);
                            if (item) {
                              const currentProducts = form.getFieldValue('products') || [];
                              currentProducts[name] = {
                                ...currentProducts[name],
                                productId: selected, // Store the full object {value, label}
                                realProductId: item.product_id,
                                brandId: item.brand_id,
                                unit: item.unit,
                                batchNumber: item.batch_number,
                                inventoryId: item.inventory_id,
                                maxQuantity: item.base_quantity || item.quantity,
                                baseQuantity: item.base_quantity || item.quantity,
                                baseUnit: item.base_unit || item.unit,
                                expirationDate: item.expiration_date
                              };
                              form.setFieldValue('products', currentProducts);
                            }
                          }}
                          disabled={!selectedOriginLocationId}
                          notFoundContent={
                            !selectedOriginLocationId
                              ? "Primero seleccione la ubicación origen"
                              : productsForOutputs.length === 0
                              ? "No hay productos disponibles"
                              : null
                          }
                        >
                          {productsForOutputs.map((item: any) => (
                            <Option key={item.inventory_id} value={item.inventory_id}>
                              {item.display_label}
                            </Option>
                          ))}
                        </Select>
                      </Form.Item>
                      <Form.Item
                        {...restField}
                        name={[name, 'brandId']}
                        hidden
                      >
                        <Input />
                      </Form.Item>
                      <Form.Item
                        {...restField}
                        name={[name, 'unit']}
                        hidden
                      >
                        <Input />
                      </Form.Item>
                      <Form.Item
                        {...restField}
                        name={[name, 'inventoryId']}
                        hidden
                      >
                        <Input />
                      </Form.Item>
                      <Form.Item
                        {...restField}
                        name={[name, 'maxQuantity']}
                        hidden
                      >
                        <Input />
                      </Form.Item>
                      <Form.Item
                        {...restField}
                        name={[name, 'expirationDate']}
                        hidden
                      >
                        <Input />
                      </Form.Item>
                    </Col>
                    <Col span={4}>
                      <Form.Item
                        {...restField}
                        name={[name, 'quantityRequested']}
                        label="Solicitado"
                        rules={[
                          { required: true, message: 'Cantidad requerida' },
                          ({ getFieldValue }) => ({
                            validator(_, value) {
                              const products = getFieldValue('products') || [];
                              const currentProduct = products[name];
                              const maxStock = currentProduct?.maxQuantity || 0;

                              if (!value || value <= maxStock) {
                                return Promise.resolve();
                              }
                              return Promise.reject(new Error(`Stock disponible: ${maxStock.toFixed(2)} ${currentProduct?.baseUnit || ''}`));
                            },
                          }),
                        ]}
                      >
                        <InputNumber
                          min={0}
                          precision={2}
                          style={{ width: '100%' }}
                          placeholder="0"
                        />
                      </Form.Item>
                    </Col>
                    <Col span={4}>
                      <Form.Item
                        {...restField}
                        name={[name, 'quantityDelivered']}
                        label="Entregado (máx +5%)"
                        rules={[
                          { required: true, message: 'Cantidad requerida' },
                          ({ getFieldValue }) => ({
                            validator(_, value) {
                              const products = getFieldValue('products') || [];
                              const currentProduct = products[name];
                              const requestedQty = currentProduct?.quantityRequested || 0;
                              const maxStock = currentProduct?.maxQuantity || 0;
                              const maxAllowed = Math.min(requestedQty * 1.05, maxStock);

                              if (!value || value <= maxAllowed) {
                                return Promise.resolve();
                              }
                              if (value > maxStock) {
                                return Promise.reject(new Error(`Stock disponible: ${maxStock.toFixed(2)} ${currentProduct?.baseUnit || ''}`));
                              }
                              return Promise.reject(new Error(`Máximo permitido: ${maxAllowed.toFixed(2)}`));
                            },
                          }),
                        ]}
                      >
                        <InputNumber
                          min={0}
                          precision={2}
                          style={{ width: '100%' }}
                          placeholder="0"
                        />
                      </Form.Item>
                    </Col>
                    <Col span={2}>
                      <Form.Item
                        dependencies={[[name, 'productId']]}
                        label="Unidad"
                      >
                        {({ getFieldValue }) => {
                          const products = getFieldValue('products') || [];
                          const currentProduct = products[name];
                          const productId = currentProduct?.productId;
                          const product = availableProducts.find((p: any) => p.id === productId);
                          const unit = product?.base_unit || '';
                          return (
                            <Input
                              value={unit}
                              disabled
                              style={{
                                width: '100%',
                                backgroundColor: '#f5f5f5',
                                color: '#666',
                                textAlign: 'center',
                                fontWeight: 'bold',
                                fontSize: '11px'
                              }}
                              placeholder="-"
                            />
                          );
                        }}
                      </Form.Item>
                    </Col>
                    <Col span={4}>
                      <Form.Item
                        {...restField}
                        name={[name, 'batchNumber']}
                        label="Lote"
                      >
                        <Input placeholder="Número de lote" />
                      </Form.Item>
                    </Col>
                    <Col span={2}>
                      <Button
                        type="link"
                        danger
                        icon={<MinusCircleOutlined />}
                        onClick={() => remove(name)}
                      >
                        Eliminar
                      </Button>
                    </Col>
                  </Row>
                )}
              </Card>
            ))}
            <Form.Item>
              <Button
                type="dashed"
                onClick={() => add()}
                block
                icon={<PlusCircleOutlined />}
              >
                Agregar Producto
              </Button>
            </Form.Item>
          </>
        )}
      </Form.List>

      <Row gutter={16}>
        <Col span={24}>
          <Form.Item
            name="observations"
            label="Observaciones"
          >
            <TextArea
              rows={isMobile ? 2 : 3}
              placeholder="Observaciones adicionales sobre la salida..."
            />
          </Form.Item>
        </Col>
      </Row>

      <div style={{ textAlign: 'right', marginTop: 24 }}>
        <Space direction={isMobile ? 'vertical' : 'horizontal'} style={{ width: isMobile ? '100%' : 'auto' }}>
          <Button
            onClick={() => {
              setIsModalVisible(false);
              form.resetFields();
              setEditingOutput(null);
              setSelectedOriginLocationId(undefined);
              setSelectedDestinationLocationId(undefined);
              setSelectedOutputTypeId(undefined);
              setProductsForOutputs([]);
              setAvailableFarmLots([]);
            }}
            style={{ width: isMobile ? '100%' : 'auto' }}
          >
            Cancelar
          </Button>
          {!isReadOnly && (
            <Button
              type="primary"
              htmlType="submit"
              style={{ width: isMobile ? '100%' : 'auto' }}
              loading={createOutputMutation.isPending || updateOutputMutation.isPending}
              disabled={createOutputMutation.isPending || updateOutputMutation.isPending}
            >
              {editingOutput ? 'Actualizar' : 'Crear'} Salida
            </Button>
          )}
        </Space>
      </div>
    </Form>
  );


  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Salidas de Productos</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Control de salidas de inventario para aplicaciones en campo
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingOutput(null);
            setSelectedOriginLocationId(undefined);
            setSelectedDestinationLocationId(undefined);
            setSelectedOutputTypeId(undefined);
            setProductsForOutputs([]);
            setAvailableFarmLots([]);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nueva Salida
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} md={8}>
              <Search
                placeholder="Buscar por N° salida, producto, código o finca..."
                allowClear
                value={searchText}
                onChange={(e) => setSearchText(e.target.value)}
              />
            </Col>
            <Col xs={24} sm={12} md={8}>
              <Select
                placeholder="Filtrar por estado"
                allowClear
                style={{ width: '100%' }}
                value={statusFilter}
                onChange={setStatusFilter}
              >
                <Option value="pending">Pendiente</Option>
                <Option value="partial">Parcial</Option>
                <Option value="completed">Completada</Option>
              </Select>
            </Col>
            <Col xs={24} sm={12} md={8}>
              <DatePicker.RangePicker
                style={{ width: '100%' }}
                format="DD/MM/YYYY"
                placeholder={['Fecha desde', 'Fecha hasta']}
                value={dateRange as any}
                onChange={(v) => setDateRange(v as any)}
              />
            </Col>
          </Row>
        </div>

        <ResponsiveTable
          mobileColumns={mobileColumns}
          desktopColumns={desktopColumns}
          dataSource={filteredOutputs}
          rowKey="id"
          loading={outputsLoading}
          expandedRowRender={expandedRowRender}
          entityName="salidas"
          pagination={{
            total: filteredOutputs.length,
            pageSize: 10,
          }}
        />
      </Card>

      {isMobile ? (
        <Drawer
          title={editingOutput ? (isReadOnly ? 'Ver Salida' : 'Editar Salida') : 'Nueva Salida'}
          placement="bottom"
          height="90%"
          open={isModalVisible}
          onClose={() => {
            setIsModalVisible(false);
            form.resetFields();
            setEditingOutput(null);
            setIsReadOnly(false);
            setSelectedOriginLocationId(undefined);
            setSelectedDestinationLocationId(undefined);
            setSelectedOutputTypeId(undefined);
            setProductsForOutputs([]);
            setAvailableFarmLots([]);
          }}
          styles={{
            body: { padding: 16 }
          }}
        >
          {renderFormContent()}
        </Drawer>
      ) : (
        <Modal
          title={editingOutput ? (isReadOnly ? 'Ver Salida' : 'Editar Salida') : 'Nueva Salida'}
          open={isModalVisible}
          onCancel={() => {
            setIsModalVisible(false);
            form.resetFields();
            setEditingOutput(null);
            setIsReadOnly(false);
            setSelectedOriginLocationId(undefined);
            setSelectedDestinationLocationId(undefined);
            setSelectedOutputTypeId(undefined);
            setProductsForOutputs([]);
            setAvailableFarmLots([]);
          }}
          footer={null}
          width={1200}
        >
          {renderFormContent()}
        </Modal>
      )}
    </div>
  );
};

export default Outputs;