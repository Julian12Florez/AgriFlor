import React, { useState, useEffect, useRef } from 'react';
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, DatePicker, InputNumber, Badge, Descriptions, Divider, Alert, List, Typography, Drawer, Progress, Steps, Timeline } from 'antd';
import { CheckCircleOutlined, ClockCircleOutlined, ExclamationCircleOutlined, EyeOutlined, InboxOutlined, EnvironmentOutlined, WarningOutlined, PlusOutlined, ShoppingCartOutlined, SwapOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import dayjs from 'dayjs';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { receptionsApi, productsApi, purchasesApi, locationsApi, outputsApi, usersApi, handleApiError } from '../../services/api';
import type { Reception, ReceptionItem, ReceptionBatch, ReceptionBatchItem, Product, PackagingUnit } from '../../data/types';
import { formatCurrency, formatQuantity, formatPercentage } from '../../utils/formatters';

const { Text, Title } = Typography;
const { Search } = Input;
const { Option } = Select;
const { TextArea } = Input;
const { Step } = Steps;

const ReceptionPage: React.FC = () => {
  const [isMobile, setIsMobile] = useState(false);
  const queryClient = useQueryClient();

  const [isModalVisible, setIsModalVisible] = useState(false);
  const [isNewBatchModalVisible, setIsNewBatchModalVisible] = useState(false);
  const [isSourceDetailsModalVisible, setIsSourceDetailsModalVisible] = useState(false);
  const [selectedReception, setSelectedReception] = useState<Reception | null>(null);
  const [selectedSource, setSelectedSource] = useState<any | null>(null);
  const [searchText, setSearchText] = useState('');
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [dateRange, setDateRange] = useState<[dayjs.Dayjs, dayjs.Dayjs] | null>(null);
  const [sourceTypeFilter, setSourceTypeFilter] = useState<string | undefined>();
  const [viewMode, setViewMode] = useState<'available' | 'receptions'>('available'); // New state for view toggle
  const [form] = Form.useForm();
  const [batchForm] = Form.useForm();
  const [sourceReceptionForm] = Form.useForm();

  // Ref to prevent double submissions (synchronous check)
  const isSubmittingRef = useRef(false);

  // Fetch available sources (purchases and outputs ready for reception)
  const { data: availableSourcesData, isLoading: sourcesLoading } = useQuery({
    queryKey: ['available-sources'],
    queryFn: () => receptionsApi.availableSources(),
  });

  // Fetch receptions from backend (only completed receptions)
  const { data: receptionsData, isLoading: receptionsLoading } = useQuery({
    queryKey: ['receptions', searchText, sourceTypeFilter, dateRange?.[0]?.format('YYYY-MM-DD'), dateRange?.[1]?.format('YYYY-MM-DD')],
    queryFn: () => receptionsApi.list({
      search: searchText || undefined,
      sourceType: sourceTypeFilter || undefined,
      date_from: dateRange?.[0]?.format('YYYY-MM-DD'),
      date_to: dateRange?.[1]?.format('YYYY-MM-DD'),
    }),
  });

  // Fetch products for reference (per_page alto para traer todos)
  const { data: productsData } = useQuery({
    queryKey: ['products', 'all-for-select'],
    queryFn: () => productsApi.list({ per_page: 9999 }),
  });

  // Fetch selected source details
  const { data: selectedSourceDetailsData, isLoading: sourceDetailsLoading } = useQuery({
    queryKey: ['source-details', selectedSource?.source_type, selectedSource?.id],
    queryFn: () => {
      if (!selectedSource) return Promise.resolve(null);
      if (selectedSource.source_type === 'purchase') {
        return purchasesApi.get(selectedSource.id);
      } else {
        return outputsApi.get(selectedSource.id);
      }
    },
    enabled: !!selectedSource,
  });

  // Fetch purchases for linking (only pending ones available for reception)
  const { data: purchasesData } = useQuery({
    queryKey: ['purchases', 'pending'],
    queryFn: () => purchasesApi.list({ status: 'pending' }),
  });

  // Fetch locations
  const { data: locationsData } = useQuery({
    queryKey: ['locations'],
    queryFn: () => locationsApi.list({ per_page: 999 }),
  });

  // Fetch outputs (exclude completed ones that are fully received)
  const { data: outputsData } = useQuery({
    queryKey: ['outputs', 'available'],
    queryFn: () => outputsApi.list(),
  });

  // Fetch users for "Recibido por" field
  const { data: usersData } = useQuery({
    queryKey: ['users-simple'],
    queryFn: () => usersApi.listSimple(),
  });

  const receptions = receptionsData?.data || [];
  const availableSources = availableSourcesData?.data || [];
  const products = productsData?.data || [];
  const purchases = purchasesData?.data || [];
  const locations = locationsData?.data || [];
  const outputs = outputsData?.data || [];
  const users = usersData?.data || [];

  // Mutation for creating a new reception
  const createReceptionMutation = useMutation({
    mutationFn: (data: any) => receptionsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['receptions'] });
      queryClient.invalidateQueries({ queryKey: ['available-sources'] });
      queryClient.invalidateQueries({ queryKey: ['purchases'] });
      queryClient.invalidateQueries({ queryKey: ['outputs'] });
      message.success('Recepción creada exitosamente');
      setViewMode('receptions'); // Switch to receptions view after creating
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al crear la recepción', form);
    },
  });

  // Mutation for adding a batch
  const addBatchMutation = useMutation({
    mutationFn: ({ receptionId, batchData }: { receptionId: string; batchData: any }) =>
      receptionsApi.addBatch(receptionId, batchData),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['receptions'] });
      queryClient.invalidateQueries({ queryKey: ['available-sources'] });
      queryClient.invalidateQueries({ queryKey: ['purchases'] });
      queryClient.invalidateQueries({ queryKey: ['outputs'] });
      setIsNewBatchModalVisible(false);
      batchForm.resetFields();
      message.success('Recepción parcial registrada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al registrar la recepción', batchForm);
    },
  });

  // Mutation: finalizar recepción con la cantidad ya recibida (libera el remanente
  // comprometido, SIN recibir ni mover inventario adicional).
  const finalizeMutation = useMutation({
    mutationFn: (receptionId: string) => receptionsApi.finalize(receptionId),
    onSuccess: (resp: any) => {
      queryClient.invalidateQueries({ queryKey: ['receptions'] });
      queryClient.invalidateQueries({ queryKey: ['available-sources'] });
      queryClient.invalidateQueries({ queryKey: ['outputs'] });
      queryClient.invalidateQueries({ queryKey: ['inventory'] });
      setIsDetailModalVisible(false);
      setSelectedReception(null);
      message.success(resp?.message || 'Recepción finalizada. Remanente liberado.');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al finalizar la recepción');
    },
  });

  const doFinalize = (receptionId?: string) => {
    const id = receptionId || selectedReception?.id;
    if (!id) return;
    finalizeMutation.mutate(id);
  };
  const finalizeTitle = 'Finalizar recepción';
  const finalizeDescription = (
    <div style={{ maxWidth: 320 }}>
      La recepción se cierra con la <strong>cantidad ya recibida</strong> y el remanente pendiente queda <strong>liberado</strong>. <strong>No</strong> se recibe ni se descuenta stock adicional. <span style={{ color: '#cf1322' }}>No se puede deshacer.</span>
    </div>
  );

  // Mutation for creating direct reception from source
  const createDirectReceptionMutation = useMutation({
    mutationFn: (data: any) => receptionsApi.createDirectReception(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['receptions'] });
      queryClient.invalidateQueries({ queryKey: ['available-sources'] });
      queryClient.invalidateQueries({ queryKey: ['purchases'] });
      queryClient.invalidateQueries({ queryKey: ['outputs'] });
      setIsSourceDetailsModalVisible(false);
      setIsNewBatchModalVisible(false);
      setSelectedSource(null);
      sourceReceptionForm.resetFields();
      message.success('Recepción registrada exitosamente');
      setViewMode('receptions');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al registrar la recepción', sourceReceptionForm);
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
      pending: 'orange',
      partial: 'yellow',
      completed: 'green',
      cancelled: 'red'
    };
    return colors[status as keyof typeof colors] || 'default';
  };

  const getStatusText = (status: string) => {
    const texts = {
      pending: 'Pendiente',
      partial: 'Parcial',
      completed: 'Completada',
      cancelled: 'Cancelada'
    };
    return texts[status as keyof typeof texts] || status;
  };

  const getStatusIcon = (status: string) => {
    const icons = {
      pending: <ClockCircleOutlined />,
      partial: <WarningOutlined />,
      completed: <CheckCircleOutlined />,
      cancelled: <ExclamationCircleOutlined />
    };
    return icons[status as keyof typeof icons] || <ClockCircleOutlined />;
  };

  const getSourceTypeColor = (sourceType: string) => {
    return sourceType === 'purchase' ? 'blue' : 'green';
  };

  const getSourceTypeText = (sourceType: string) => {
    return sourceType === 'purchase' ? 'Compra' : 'Salida';
  };

  const getSourceTypeIcon = (sourceType: string) => {
    return sourceType === 'purchase' ? <ShoppingCartOutlined /> : <SwapOutlined />;
  };

  const getConditionColor = (condition: string) => {
    const colors = {
      good: 'green',
      damaged: 'orange',
      expired: 'red'
    };
    return colors[condition as keyof typeof colors] || 'default';
  };

  const getConditionText = (condition: string) => {
    const texts = {
      good: 'Bueno',
      damaged: 'Dañado',
      expired: 'Vencido'
    };
    return texts[condition as keyof typeof texts] || condition;
  };

  const getEquivalenceText = (productId: string, quantity: number, unit: string) => {
    const product = products.find((p: any) => p.id === productId);
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

  const calculateReceptionStatus = (items: ReceptionItem[]): string => {
    const totalExpected = items.reduce((sum, item) => sum + item.quantityExpected, 0);
    const totalReceived = items.reduce((sum, item) => sum + item.quantityReceived, 0);

    if (totalReceived === 0) return 'pending';
    if (totalReceived >= totalExpected) return 'completed';
    return 'partial';
  };

  const calculateCompletionPercentage = (items: ReceptionItem[]): number => {
    const totalExpected = items.reduce((sum, item) => sum + item.quantityExpected, 0);
    const totalReceived = items.reduce((sum, item) => sum + item.quantityReceived, 0);

    if (totalExpected === 0) return 0;
    return Math.round((totalReceived / totalExpected) * 100);
  };

  const handleViewReception = (record: Reception) => {
    setSelectedReception(record);
    setIsModalVisible(true);
  };

  const handleViewAvailableSource = (source: any) => {
    setSelectedSource(source);
    setIsSourceDetailsModalVisible(true);
  };

  const handleNewPartialReception = () => {
    if (!selectedReception) return;

    // Preparar formulario para nueva recepción parcial
    batchForm.setFieldsValue({
      receptionDate: dayjs(),
      receivedBy: '',
      observations: '',
      items: selectedReception.items?.map((item: any) => ({
        productId: item.productId,
        quantityToReceive: undefined,
        condition: 'good',
        expirationDate: item.expirationDate
          ? dayjs(item.expirationDate)
          : (item.suggestedExpirationDate ? dayjs(item.suggestedExpirationDate) : undefined),
        observations: ''
      })) || []
    });
    setIsNewBatchModalVisible(true);
  };

  const handleCreateReceptionFromSource = (source: any) => {
    // Prevent multiple submissions using ref (synchronous check)
    if (isSubmittingRef.current) {
      return;
    }
    isSubmittingRef.current = true;

    const receptionData = {
      reception_number: `REC-${new Date().getFullYear()}-${String(Date.now()).slice(-6)}`,
      source_id: source.id,
      source_type: source.source_type,
      origin_location_id: source.origin_location?.id || undefined,
      destination_location_id: source.destination_location.id,
      shipment_date: source.date,
      observations: undefined,
    };

    createReceptionMutation.mutate(receptionData, {
      onSettled: () => {
        isSubmittingRef.current = false;
      }
    });
  };

  const handleSavePartialReception = (values: any) => {
    // Prevent multiple submissions using ref (synchronous check)
    if (isSubmittingRef.current) {
      return;
    }

    if (!selectedReception) return;

    isSubmittingRef.current = true;

    // Format batch data for backend API (snake_case)
    const batchData = {
      batch_number: selectedReception.batches.length + 1,
      reception_date: values.receptionDate.format('YYYY-MM-DD'),
      received_by: values.receivedBy,
      observations: values.observations || undefined,
      items: values.items
        .filter((item: any) => item.quantityToReceive && item.quantityToReceive > 0)
        .map((item: any, index: number) => {
          const receptionItem = selectedReception.items[index];
          return {
            productId: receptionItem.productId,
            brandId: receptionItem.brandId,
            quantityReceived: item.quantityToReceive,
            condition: item.condition,
            expirationDate: item.expirationDate ? item.expirationDate.format('YYYY-MM-DD') : undefined,
            observations: item.observations || undefined,
          };
        }),
    };

    // Call backend API to add batch
    addBatchMutation.mutate({
      receptionId: selectedReception.id,
      batchData,
    }, {
      onSettled: () => {
        isSubmittingRef.current = false;
      }
    });
  };

  const handleSaveDirectReception = (values: any) => {
    // Prevent multiple submissions using ref (synchronous check)
    if (isSubmittingRef.current) {
      return;
    }

    console.log('=== handleSaveDirectReception called ===');
    console.log('selectedSource:', selectedSource);
    console.log('Form values:', values);

    if (!selectedSource) {
      console.error('No selectedSource found!');
      return;
    }

    const itemsToReceive = (values.items || [])
      .filter((item: any) => item && item.quantityReceived && item.quantityReceived > 0);

    console.log('Items to receive:', itemsToReceive);

    if (itemsToReceive.length === 0) {
      message.error('Debe ingresar al menos un producto con cantidad mayor a 0');
      return;
    }

    isSubmittingRef.current = true;

    const receptionData = {
      source_id: selectedSource.id,
      source_type: selectedSource.source_type,
      reception_date: values.receptionDate.format('YYYY-MM-DD'),
      received_by: values.receivedBy,
      observations: values.observations || undefined,
      items: itemsToReceive.map((item: any) => ({
        reception_item_id: item.receptionItemId || undefined,
        product_id: item.productId,
        brand_id: item.brandId,
        quantity_received: item.quantityReceived,
        condition: item.condition || 'good',
        expiration_date: item.expirationDate ? item.expirationDate.format('YYYY-MM-DD') : undefined,
        observations: item.observations || undefined,
      })),
    };

    console.log('Reception data to send:', receptionData);

    createDirectReceptionMutation.mutate(receptionData, {
      onSettled: () => {
        isSubmittingRef.current = false;
      }
    });
  };

  const handleCreateEmptyReception = () => {
    if (!selectedSource) return;

    const receptionData = {
      source_id: selectedSource.id,
      source_type: selectedSource.source_type,
    };

    createReceptionMutation.mutate(receptionData);
  };

  const handleOpenReceptionFromSource = () => {
    if (!selectedSource || !selectedSourceDetailsData) return;

    const sourceDetails = selectedSourceDetailsData.data;

    // If there's already a reception, use reception items (backend provides per-item quantities).
    // Otherwise, use source items (purchase items or output products) - first reception.
    const hasReceptionItems = selectedSource.reception?.items && selectedSource.reception.items.length > 0;
    const allItems = hasReceptionItems
      ? selectedSource.reception.items
      : (selectedSource.source_type === 'purchase'
          ? sourceDetails.items || []
          : sourceDetails.products || []);

    // Filter items with pending quantities only
    const itemsWithPending = allItems.filter((item: any) => {
      if (hasReceptionItems) {
        // Backend provides quantityPending per individual item (handles duplicates correctly)
        const quantityPending = parseFloat(item.quantityPending) || 0;
        return quantityPending > 0;
      }

      // First reception - all items are pending (nothing received yet)
      const quantityExpected = selectedSource.source_type === 'purchase'
        ? (parseFloat(item.quantityInBaseUnits) || parseFloat(item.quantity) || 0)
        : (parseFloat(item.quantityDelivered) || parseFloat(item.quantity_delivered) || 0);
      return quantityExpected > 0;
    });

    // Preparar formulario con productos que tienen pendientes
    console.log('=== Initializing form fields ===');
    console.log('All items:', allItems.length);
    console.log('Items with pending:', itemsWithPending.length);

    if (itemsWithPending.length === 0) {
      message.warning('No hay productos pendientes por recepcionar');
      return;
    }

    sourceReceptionForm.setFieldsValue({
      receptionDate: dayjs(),
      receivedBy: selectedSource?.destination_location?.responsible_user_id || undefined,
      observations: undefined,
      items: itemsWithPending.map((item: any) => ({
        receptionItemId: item.id || undefined,
        productId: selectedSource.source_type === 'purchase' ? item.productId : item.productId || item.product_id,
        brandId: item.brandId || item.brand_id,
        quantityReceived: undefined,
        condition: 'good',
        expirationDate: item.expirationDate
          ? dayjs(item.expirationDate)
          : (item.suggestedExpirationDate ? dayjs(item.suggestedExpirationDate) : undefined)
      }))
    });

    console.log('Form values after init:', sourceReceptionForm.getFieldsValue());

    // Cerrar modal informativo y abrir modal de recepción
    setIsSourceDetailsModalVisible(false);
    setIsNewBatchModalVisible(true);
  };

  const filteredReceptions = receptions.filter((reception: any) => {
    // La búsqueda (N° recepción, producto, código, finca) se resuelve en el backend;
    // no se filtra por texto en cliente para no ocultar matches por producto/finca.
    const matchesSourceType = !sourceTypeFilter || reception.sourceType === sourceTypeFilter;
    return matchesSourceType;
  });

  const filteredAvailableSources = availableSources.filter((source: any) => {
    const matchesSearch = source.document_number?.toLowerCase().includes(searchText.toLowerCase()) ||
                         source.destination_location?.name?.toLowerCase().includes(searchText.toLowerCase()) ||
                         source.supplier_name?.toLowerCase().includes(searchText.toLowerCase());
    const matchesSourceType = !sourceTypeFilter || source.source_type === sourceTypeFilter;
    return matchesSearch && matchesSourceType;
  });

  const mobileColumns: ColumnsType<any> = [
    {
      title: 'Productos',
      key: 'reception',
      render: (_, record) => {
        const completionPercentage = record.items?.length > 0
          ? Math.round((record.items.reduce((sum: number, item: any) => sum + (item.quantityReceived || 0), 0) /
              record.items.reduce((sum: number, item: any) => sum + item.quantityExpected, 0)) * 100)
          : 0;

        return (
          <div>
            <div style={{ fontWeight: 500, fontSize: 14, marginBottom: 4 }}>
              <InboxOutlined style={{ marginRight: 8, color: '#1890ff' }} />
              {record.receptionNumber}
            </div>
            <div style={{ marginBottom: 4 }}>
              <Tag color={getSourceTypeColor(record.sourceType)} icon={getSourceTypeIcon(record.sourceType)}>
                {getSourceTypeText(record.sourceType)}
              </Tag>
              <Tag color={getStatusColor(record.status)} icon={getStatusIcon(record.status)}>
                {getStatusText(record.status)}
              </Tag>
            </div>
            <Progress
              percent={completionPercentage}
              size="small"
              status={record.status === 'completed' ? 'success' : 'active'}
            />
          </div>
        );
      },
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 50,
      render: (_, record) => (
        <Button
          type="link"
          icon={<EyeOutlined />}
          onClick={() => handleViewReception(record)}
          size="small"
        />
      ),
    },
  ];

  const desktopColumns: ColumnsType<any> = [
    {
      title: 'Recepción',
      key: 'reception',
      render: (_, record) => {
        const sourceNumber = record.sourceType === 'purchase'
          ? record.sourceDetails?.orderNumber
          : record.sourceDetails?.outputNumber;

        return (
          <div>
            <div style={{ fontWeight: 500, fontSize: 14, marginBottom: 4 }}>
              <InboxOutlined style={{ marginRight: 8, color: '#1890ff' }} />
              {record.receptionNumber}
            </div>
            <div style={{ color: '#666', fontSize: 12, marginBottom: 4 }}>
              <Tag color={getSourceTypeColor(record.sourceType)} icon={getSourceTypeIcon(record.sourceType)}>
                {getSourceTypeText(record.sourceType)}
              </Tag>
            </div>
            <div style={{ color: '#1890ff', fontSize: 12, fontWeight: 500 }}>
              {record.sourceType === 'purchase' ? 'Compra: ' : 'Salida: '}
              {sourceNumber || 'N/A'}
            </div>
          </div>
        );
      },
    },
    {
      title: 'Ruta',
      key: 'route',
      render: (_, record) => (
        <div>
          {record.origin_location && (
            <div style={{ fontSize: 12, color: '#666', marginBottom: 4 }}>
              <strong>Origen:</strong> {record.origin_location.name}
            </div>
          )}
          <div style={{ fontSize: 12, color: '#666' }}>
            <strong>Destino:</strong> {record.destinationLocation?.name || 'N/A'}
          </div>
        </div>
      ),
    },
    {
      title: 'Progreso',
      key: 'progress',
      render: (_, record) => {
        const totalExpected = record.items?.reduce((sum: number, item: any) => sum + item.quantityExpected, 0) || 0;
        const totalReceived = record.items?.reduce((sum: number, item: any) => sum + (item.quantityReceived || 0), 0) || 0;
        const completionPercentage = totalExpected > 0 ? Math.round((totalReceived / totalExpected) * 100) : 0;

        return (
          <div>
            <div style={{ fontSize: 12, marginBottom: 4 }}>
              {totalReceived} / {totalExpected} productos
            </div>
            <Progress
              percent={completionPercentage}
              size="small"
              status={record.status === 'completed' ? 'success' : 'active'}
            />
            <div style={{ fontSize: 11, color: '#666', marginTop: 2 }}>
              {record.batches?.length || 0} recepción{(record.batches?.length || 0) !== 1 ? 'es' : ''}
            </div>
          </div>
        );
      },
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
        { text: 'Pendiente', value: 'pending' },
        { text: 'Parcial', value: 'partial' },
        { text: 'Completada', value: 'completed' },
        { text: 'Cancelada', value: 'cancelled' },
      ],
      onFilter: (value, record) => record.status === value,
    },
    {
      title: 'Responsable',
      dataIndex: 'responsible_user',
      key: 'responsible_user',
      render: (text: string) => (
        <span style={{ color: text ? '#2E7D32' : '#999' }}>
          {text || 'Pendiente'}
        </span>
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
            icon={<EyeOutlined />}
            onClick={() => handleViewReception(record)}
          >
            Ver
          </Button>
        </Space>
      ),
    },
  ];

  // Columns for available sources (purchases and outputs)
  const availableSourcesMobileColumns: ColumnsType<any> = [
    {
      title: 'Productos',
      key: 'products',
      render: (_, record) => (
        <div style={{ overflow: 'hidden' }}>
          <div style={{ fontWeight: 500, fontSize: 13, marginBottom: 4, display: 'flex', alignItems: 'center', gap: 6 }}>
            <Tag color={getSourceTypeColor(record.source_type)} icon={getSourceTypeIcon(record.source_type)} style={{ margin: 0 }}>
              {getSourceTypeText(record.source_type)}
            </Tag>
            <span style={{ color: '#999', fontSize: 11 }}>{record.document_number}</span>
          </div>
          <div style={{ marginBottom: 4 }}>
            {(record.items_names || []).map((name: string, idx: number) => (
              <div key={idx} style={{ fontSize: 13, color: '#333', lineHeight: '20px' }}>
                • {name}
              </div>
            ))}
          </div>
          {record.reception ? (
            <Progress
              percent={record.reception.completion_percentage}
              size="small"
              status={record.reception.status === 'completed' ? 'success' : 'active'}
            />
          ) : (
            <Tag color="default" style={{ fontSize: 11 }}>Sin iniciar</Tag>
          )}
        </div>
      ),
    },
    {
      title: '',
      key: 'actions',
      width: 44,
      render: (_, record) => (
        <Button
          type="primary"
          size="small"
          icon={<EyeOutlined />}
          onClick={() => handleViewAvailableSource(record)}
        />
      ),
    },
  ];

  const availableSourcesColumns: ColumnsType<any> = [
    {
      title: 'Documento',
      key: 'document',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: 14, marginBottom: 4 }}>
            {record.document_number}
          </div>
          <div>
            <Tag color={getSourceTypeColor(record.source_type)} icon={getSourceTypeIcon(record.source_type)}>
              {getSourceTypeText(record.source_type)}
            </Tag>
          </div>
        </div>
      ),
    },
    {
      title: 'Origen / Proveedor',
      key: 'origin',
      render: (_, record) => (
        <div>
          {record.source_type === 'purchase' ? (
            <span>{record.supplier_name || 'N/A'}</span>
          ) : (
            <span>{record.origin_location?.name || 'N/A'}</span>
          )}
        </div>
      ),
    },
    {
      title: 'Destino',
      dataIndex: ['destination_location', 'name'],
      key: 'destination',
    },
    {
      title: 'Fecha',
      dataIndex: 'date',
      key: 'date',
      render: (date: string) => dayjs(date).format('DD/MM/YYYY'),
    },
    {
      title: 'Items',
      dataIndex: 'items_count',
      key: 'items_count',
      render: (count: number) => `${count} productos`,
    },
    {
      title: 'Progreso',
      key: 'reception_progress',
      width: 180,
      render: (_, record) => {
        if (!record.reception) {
          return <Tag color="default">Sin iniciar</Tag>;
        }
        const { status, completion_percentage } = record.reception;
        const statusColors: any = {
          'pending': 'orange',
          'partial': 'blue',
          'completed': 'green',
        };
        return (
          <div>
            <Progress
              percent={completion_percentage}
              size="small"
              status={status === 'completed' ? 'success' : 'active'}
            />
            <div style={{ marginTop: 4, fontSize: 11 }}>
              <Tag color={statusColors[status]}>
                {status === 'pending' ? 'Pendiente' : status === 'partial' ? 'Parcial' : 'Completada'}
              </Tag>
            </div>
          </div>
        );
      },
    },
    {
      title: 'Acciones',
      key: 'actions',
      fixed: 'right' as const,
      width: 180,
      render: (_, record) => (
        <Button
          type="primary"
          icon={<EyeOutlined />}
          onClick={() => handleViewAvailableSource(record)}
        >
          {record.reception ? 'Ver Detalles' : 'Crear Recepción'}
        </Button>
      ),
    },
  ];

  const expandedRowRender = (record: any) => {
    const totalExpected = record.items?.reduce((sum: number, item: any) => sum + item.quantityExpected, 0) || 0;
    const totalReceived = record.items?.reduce((sum: number, item: any) => sum + (item.quantityReceived || 0), 0) || 0;
    const completionPercentage = totalExpected > 0 ? Math.round((totalReceived / totalExpected) * 100) : 0;

    return (
      <Descriptions size="small" column={1}>
        <Descriptions.Item label="Tipo de fuente">
          <Tag color={getSourceTypeColor(record.sourceType)} icon={getSourceTypeIcon(record.sourceType)}>
            {getSourceTypeText(record.sourceType)}
          </Tag>
        </Descriptions.Item>
        <Descriptions.Item label="Documento origen">{record.sourceDetails?.purchaseNumber || record.sourceDetails?.outputNumber || 'N/A'}</Descriptions.Item>
        {record.origin_location && (
          <Descriptions.Item label="Origen">{record.origin_location.name}</Descriptions.Item>
        )}
        <Descriptions.Item label="Destino">{record.destinationLocation?.name || 'N/A'}</Descriptions.Item>
        {record.shipment_date && (
          <Descriptions.Item label="Fecha de envío">
            {dayjs(record.shipment_date).format('DD/MM/YYYY')}
          </Descriptions.Item>
        )}
        <Descriptions.Item label="Productos esperados">{totalExpected}</Descriptions.Item>
        <Descriptions.Item label="Productos recibidos">{totalReceived}</Descriptions.Item>
        <Descriptions.Item label="Progreso">
          <Progress percent={completionPercentage} size="small" />
        </Descriptions.Item>
        <Descriptions.Item label="Recepciones realizadas">{record.batches?.length || 0}</Descriptions.Item>
        {record.observations && (
          <Descriptions.Item label="Observaciones">{record.observations}</Descriptions.Item>
        )}
      </Descriptions>
    );
  };

  const renderReceptionDetails = () => {
    if (!selectedReception) return null;

    const totalExpected = selectedReception.items?.reduce((sum: number, item: any) => sum + item.quantityExpected, 0) || 0;
    const totalReceived = selectedReception.items?.reduce((sum: number, item: any) => sum + (item.quantityReceived || 0), 0) || 0;
    const completionPercentage = totalExpected > 0 ? Math.round((totalReceived / totalExpected) * 100) : 0;

    return (
      <div>
        <Alert
          message="Información de la Recepción"
          description={
            <Descriptions size="small" column={isMobile ? 1 : 2}>
              <Descriptions.Item label="Número de Recepción">{selectedReception.receptionNumber}</Descriptions.Item>
              <Descriptions.Item label="Tipo">
                <Tag color={getSourceTypeColor(selectedReception.sourceType)} icon={getSourceTypeIcon(selectedReception.sourceType)}>
                  {getSourceTypeText(selectedReception.sourceType)}
                </Tag>
              </Descriptions.Item>
              <Descriptions.Item label={selectedReception.sourceType === 'purchase' ? 'Número de Compra' : 'Número de Salida'}>
                <span style={{ fontWeight: 500, color: '#1890ff' }}>
                  {selectedReception.sourceType === 'purchase'
                    ? selectedReception.sourceDetails?.orderNumber
                    : selectedReception.sourceDetails?.outputNumber || 'N/A'}
                </span>
              </Descriptions.Item>
              <Descriptions.Item label="Estado">
                <Tag color={getStatusColor(selectedReception.status)} icon={getStatusIcon(selectedReception.status)}>
                  {getStatusText(selectedReception.status)}
                </Tag>
              </Descriptions.Item>
              {selectedReception.origin_location && (
                <Descriptions.Item label="Origen">{selectedReception.origin_location.name}</Descriptions.Item>
              )}
              <Descriptions.Item label="Destino">{selectedReception.destinationLocation?.name || 'N/A'}</Descriptions.Item>
              <Descriptions.Item label="Progreso">
                <Progress percent={completionPercentage} size="small" />
                <div style={{ fontSize: 12, color: '#666' }}>
                  {totalReceived} / {totalExpected} productos
                </div>
              </Descriptions.Item>
            </Descriptions>
          }
          type="info"
          style={{ marginBottom: 24 }}
        />

        <Divider>Productos</Divider>
        {selectedReception.items?.map((item: any, index: number) => {
          const quantityPending = item.quantityExpected - (item.quantityReceived || 0);
          return (
            <Card key={item.id} size="small" style={{ marginBottom: 16 }}>
              <Row gutter={16}>
                <Col xs={24} sm={12} md={8}>
                  <div>
                    <strong>{item.product?.name || 'N/A'}</strong>
                    <div style={{ color: '#666', fontSize: 12 }}>
                      Marca: {item.brand?.name || 'N/A'}
                    </div>
                    {item.expirationDate && selectedReception.sourceType === 'purchase' && (
                      <div style={{ color: '#666', fontSize: 12 }}>
                        Vence: {dayjs(item.expirationDate).format('DD/MM/YYYY')}
                      </div>
                    )}
                  </div>
                </Col>
                <Col xs={24} sm={12} md={4}>
                  <div>
                    <div style={{ fontSize: 12, color: '#666' }}>Esperado</div>
                    <strong>{formatQuantity(item.quantityExpected)} {item.unit}</strong>
                    {getEquivalenceText(item.productId, item.quantityExpected, item.unit) && (
                      <div style={{ fontSize: 10, color: '#999', marginTop: 2 }}>
                        {getEquivalenceText(item.productId, item.quantityExpected, item.unit)}
                      </div>
                    )}
                  </div>
                </Col>
                <Col xs={24} sm={12} md={4}>
                  <div>
                    <div style={{ fontSize: 12, color: '#666' }}>Recibido</div>
                    <strong style={{ color: '#52c41a' }}>{formatQuantity(item.quantityReceived || 0)} {item.unit}</strong>
                    {getEquivalenceText(item.productId, item.quantityReceived || 0, item.unit) && (
                      <div style={{ fontSize: 10, color: '#999', marginTop: 2 }}>
                        {getEquivalenceText(item.productId, item.quantityReceived || 0, item.unit)}
                      </div>
                    )}
                  </div>
                </Col>
                <Col xs={24} sm={12} md={4}>
                  <div>
                    <div style={{ fontSize: 12, color: '#666' }}>Pendiente</div>
                    <strong style={{ color: quantityPending > 0 ? '#fa8c16' : '#52c41a' }}>
                      {formatQuantity(quantityPending)} {item.unit}
                    </strong>
                    {getEquivalenceText(item.productId, quantityPending, item.unit) && (
                      <div style={{ fontSize: 10, color: '#999', marginTop: 2 }}>
                        {getEquivalenceText(item.productId, quantityPending, item.unit)}
                      </div>
                    )}
                  </div>
                </Col>
                <Col xs={24} sm={12} md={4}>
                  {item.condition && (
                    <Tag color={getConditionColor(item.condition)}>
                      {getConditionText(item.condition)}
                    </Tag>
                  )}
                </Col>
              </Row>
              {item.observations && (
                <div style={{ marginTop: 8, fontSize: 12, color: '#666' }}>
                  <strong>Observaciones:</strong> {item.observations}
                </div>
              )}
            </Card>
          );
        })}

        <Divider>Historial de Recepciones</Divider>
        <Timeline>
          {selectedReception.batches?.map((batch: any, index: number) => (
            <Timeline.Item
              key={batch.id}
              color={index === selectedReception.batches.length - 1 ? 'green' : 'blue'}
              dot={<CheckCircleOutlined />}
            >
              <div>
                <strong>Recepción #{batch.batch_number}</strong>
                <div style={{ color: '#666', fontSize: 12 }}>
                  {dayjs(batch.reception_date).format('DD/MM/YYYY HH:mm')} - {batch.received_by}
                </div>
                <div style={{ marginTop: 8 }}>
                  {batch.batch_items?.map((batchItem: any) => {
                    const receptionItem = selectedReception.items.find((i: any) =>
                      batchItem.receptionItemId
                        ? i.id === batchItem.receptionItemId
                        : i.productId === batchItem.productId
                    );
                    return (
                      <div key={batchItem.id} style={{ fontSize: 12, marginBottom: 4 }}>
                        • {receptionItem?.product?.name || 'N/A'}: {formatQuantity(batchItem.quantityReceived)} {receptionItem?.unit || 'kg'}
                        <Tag color={getConditionColor(batchItem.condition)} style={{ marginLeft: 8 }}>
                          {getConditionText(batchItem.condition)}
                        </Tag>
                      </div>
                    );
                  })}
                </div>
                {batch.observations && (
                  <div style={{ marginTop: 8, fontSize: 12, color: '#666', fontStyle: 'italic' }}>
                    {batch.observations}
                  </div>
                )}
              </div>
            </Timeline.Item>
          ))}
        </Timeline>

        {selectedReception.status !== 'completed' && selectedReception.status !== 'cancelled' && (
          <div style={{ textAlign: 'center', marginTop: 24 }}>
            <Space wrap>
              <Button
                type="primary"
                icon={<PlusOutlined />}
                onClick={handleNewPartialReception}
                size="large"
              >
                Nueva Recepción Parcial
              </Button>
              <Popconfirm
                title={finalizeTitle}
                description={finalizeDescription}
                okText="Sí, finalizar y liberar"
                cancelText="Cancelar"
                okButtonProps={{ danger: true }}
                onConfirm={() => doFinalize()}
              >
                <Button danger size="large" loading={finalizeMutation.isPending}>
                  Finalizar recepción
                </Button>
              </Popconfirm>
            </Space>
            <div style={{ marginTop: 8, fontSize: 12, color: '#888' }}>
              "Finalizar recepción" cierra con la cantidad ya recibida y libera el remanente pendiente, sin recibir ni descontar más stock.
            </div>
          </div>
        )}
      </div>
    );
  };

  const renderSourceReceptionForm = () => {
    if (!selectedSource || !selectedSourceDetailsData) return null;

    const sourceDetails = selectedSourceDetailsData.data;
    // Use the SAME data source as handleOpenReceptionFromSource to keep indices synchronized
    const hasReceptionItems = selectedSource.reception?.items && selectedSource.reception.items.length > 0;
    const allItems = hasReceptionItems
      ? selectedSource.reception.items
      : (selectedSource.source_type === 'purchase'
          ? sourceDetails.items || []
          : sourceDetails.products || []);

    // Filter items with pending quantities only - SAME logic as handleOpenReceptionFromSource
    const items = allItems.filter((item: any) => {
      if (hasReceptionItems) {
        // Backend provides quantityPending per item (no need to recalculate)
        const quantityPending = parseFloat(item.quantityPending) || 0;
        return quantityPending > 0;
      }

      // For source items without reception, calculate from source data
      const quantityExpected = selectedSource.source_type === 'purchase'
        ? (parseFloat(item.quantityInBaseUnits) || parseFloat(item.quantity) || 0)
        : (parseFloat(item.quantityDelivered) || parseFloat(item.quantity_delivered) || 0);

      return quantityExpected > 0;
    });

    // If no items with pending quantities, show message
    if (items.length === 0) {
      return (
        <div style={{ padding: '40px', textAlign: 'center' }}>
          <Alert
            message="Todos los productos completados"
            description="Todos los productos de esta orden ya han sido completamente recepcionados."
            type="success"
            showIcon
          />
        </div>
      );
    }

    return (
      <Form
        form={sourceReceptionForm}
        layout="vertical"
        onFinish={handleSaveDirectReception}
        onFinishFailed={(errorInfo) => {
          console.log('=== Form validation failed ===');
          console.log('Error info:', errorInfo);
          message.error('Por favor complete todos los campos requeridos');
        }}
      >
        <Row gutter={16}>
          <Col xs={24} sm={12}>
            <Form.Item
              name="receptionDate"
              label="Fecha de Recepción"
              rules={[{ required: true, message: 'La fecha es requerida' }]}
              initialValue={dayjs()}
            >
              <DatePicker
                style={{ width: '100%' }}
                format="DD/MM/YYYY"
                placeholder="Seleccione la fecha"
              />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item
              name="receivedBy"
              label="Recibido por"
              rules={[{ required: true, message: 'El responsable es requerido' }]}
            >
              <Select
                placeholder="Seleccione quien recibe"
                showSearch
                optionFilterProp="children"
                filterOption={(input, option) =>
                  (option?.children as string)?.toLowerCase().includes(input.toLowerCase())
                }
              >
                {users
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

        <Divider>Productos a Recibir</Divider>

        <Form.List name="items">
          {(fields) => (
            <>
              {fields.map(({ key, name, ...restField }, index) => {
                const item = items[index];
                if (!item) return null;

                const productName = item.productName || item.product?.name;
                const brandName = item.brandName || item.brand?.name;

                // Get quantities - handle both reception.items and source.products formats
                let quantityExpected: number;
                let quantityAlreadyReceived: number;
                let quantityPending: number;

                if (hasReceptionItems) {
                  // reception.items provides all quantities pre-calculated by backend
                  quantityExpected = parseFloat(item.quantityExpected) || 0;
                  quantityAlreadyReceived = parseFloat(item.quantityReceived) || 0;
                  quantityPending = parseFloat(item.quantityPending) || 0;
                } else {
                  // Source items (purchase or output) - first reception, no previous batches
                  quantityExpected = selectedSource.source_type === 'purchase'
                    ? (parseFloat(item.quantityInBaseUnits) || parseFloat(item.quantity) || 0)
                    : (parseFloat(item.quantityDelivered) || parseFloat(item.quantity_delivered) || 0);
                  quantityAlreadyReceived = 0;
                  quantityPending = quantityExpected;
                }

                // Build unit display - quantities are now in base units (kg, L, etc.)
                let unitDisplay = '';
                if (selectedSource.source_type === 'purchase') {
                  if (hasReceptionItems) {
                    // Reception items already have base unit after backend fix
                    unitDisplay = item.unit;
                  } else {
                    // First reception - use base unit from purchase item
                    unitDisplay = item.packagingUnitBaseUnit || item.unit;
                  }
                } else {
                  unitDisplay = item.unit;
                }

                const productId = item.productId || item.product_id;

                return (
                  <Card key={key} size="small" style={{ marginBottom: 16 }}>
                    <Row gutter={16} align="middle">
                      <Col xs={24} sm={4}>
                        <div>
                          <strong style={{ fontSize: 13 }}>{productName || 'N/A'}</strong>
                          <div style={{ color: '#666', fontSize: 11 }}>
                            {brandName && `${brandName}`}
                          </div>
                        </div>
                      </Col>
                      <Col xs={6} sm={2}>
                        <Form.Item
                          {...restField}
                          name={[name, 'receptionItemId']}
                          hidden
                        >
                          <Input />
                        </Form.Item>
                        <Form.Item
                          {...restField}
                          name={[name, 'productId']}
                          hidden
                        >
                          <Input />
                        </Form.Item>
                        <Form.Item label="Esperada" style={{ marginBottom: 0 }}>
                          <div style={{
                            textAlign: 'center',
                            padding: '4px 8px',
                            backgroundColor: '#f0f0f0',
                            border: '1px solid #d9d9d9',
                            borderRadius: '4px',
                            fontSize: '13px',
                            fontWeight: 'bold',
                            color: '#1890ff'
                          }}>
                            {formatQuantity(quantityExpected)}
                            <div style={{ fontSize: '10px', color: '#666', fontWeight: 'normal' }}>
                              {unitDisplay}
                            </div>
                          </div>
                        </Form.Item>
                      </Col>
                      <Col xs={6} sm={2}>
                        <Form.Item label="Recibida" style={{ marginBottom: 0 }}>
                          <div style={{
                            textAlign: 'center',
                            padding: '4px 8px',
                            backgroundColor: '#f6ffed',
                            border: '1px solid #b7eb8f',
                            borderRadius: '4px',
                            fontSize: '13px',
                            fontWeight: 'bold',
                            color: '#52c41a'
                          }}>
                            {formatQuantity(quantityAlreadyReceived)}
                            <div style={{ fontSize: '10px', color: '#666', fontWeight: 'normal' }}>
                              {unitDisplay}
                            </div>
                          </div>
                        </Form.Item>
                      </Col>
                      <Col xs={6} sm={2}>
                        <Form.Item label="Pendiente" style={{ marginBottom: 0 }}>
                          <div style={{
                            textAlign: 'center',
                            padding: '4px 8px',
                            backgroundColor: '#fff7e6',
                            border: '1px solid #ffd591',
                            borderRadius: '4px',
                            fontSize: '13px',
                            fontWeight: 'bold',
                            color: '#ff9800'
                          }}>
                            {formatQuantity(quantityPending)}
                            <div style={{ fontSize: '10px', color: '#666', fontWeight: 'normal' }}>
                              {unitDisplay}
                            </div>
                          </div>
                        </Form.Item>
                      </Col>
                      <Col xs={6} sm={3}>
                        <Form.Item
                          {...restField}
                          name={[name, 'quantityReceived']}
                          label="A Recibir"
                          rules={[
                            {
                              validator: (_, value) => {
                                if (value && value > quantityPending) {
                                  return Promise.reject(`Máximo: ${quantityPending}`);
                                }
                                return Promise.resolve();
                              }
                            }
                          ]}
                          initialValue={0}
                        >
                          <InputNumber
                            min={0}
                            max={quantityPending}
                            step={0.1}
                            precision={2}
                            style={{ width: '100%' }}
                            placeholder="0"
                          />
                        </Form.Item>
                        {selectedSource.source_type === 'purchase' && (() => {
                          // Get packaging info from either reception item or source item
                          const pkgName = item.packagingInfo?.packagingUnitName || item.packagingUnitName;
                          const pkgBaseQty = parseFloat(item.packagingInfo?.baseQuantityPerUnit || item.packagingUnitBaseQuantity);
                          return pkgName && !isNaN(pkgBaseQty) && pkgBaseQty > 0;
                        })() && (
                          <Form.Item noStyle shouldUpdate>
                            {() => {
                              const currentValue = sourceReceptionForm.getFieldValue(['items', index, 'quantityReceived']) || 0;
                              const pkgName = item.packagingInfo?.packagingUnitName || item.packagingUnitName;
                              const pkgBaseQty = parseFloat(item.packagingInfo?.baseQuantityPerUnit || item.packagingUnitBaseQuantity);

                              if (!isNaN(pkgBaseQty) && pkgBaseQty > 0 && currentValue > 0) {
                                // Input is now in base units (kg/L), show equivalent in packaging units
                                const packagingUnits = currentValue / pkgBaseQty;
                                return (
                                  <div style={{
                                    marginTop: -8,
                                    fontSize: 11,
                                    color: '#1890ff',
                                    fontWeight: 500,
                                    textAlign: 'center'
                                  }}>
                                    = {formatQuantity(packagingUnits)} {pkgName}
                                  </div>
                                );
                              }
                              return null;
                            }}
                          </Form.Item>
                        )}
                      </Col>
                      <Col xs={12} sm={3}>
                        <Form.Item
                          {...restField}
                          name={[name, 'condition']}
                          label="Estado"
                          initialValue="good"
                        >
                          <Select>
                            <Option value="good">Bueno</Option>
                            <Option value="damaged">Dañado</Option>
                            <Option value="expired">Vencido</Option>
                          </Select>
                        </Form.Item>
                      </Col>
                      <Col xs={12} sm={5}>
                        <Form.Item
                          dependencies={[[name, 'quantityReceived']]}
                          {...restField}
                          name={[name, 'expirationDate']}
                          label="F. Vencimiento"
                          rules={[
                            {
                              validator: (_, value) => {
                                const quantityReceived = sourceReceptionForm.getFieldValue(['items', name, 'quantityReceived']);

                                // Solo para COMPRAS: vencimiento obligatorio y futuro
                                // (producto nuevo del proveedor no debe llegar ya vencido)
                                if (quantityReceived > 0 && selectedSource.source_type === 'purchase') {
                                  if (!value) {
                                    return Promise.reject('Fecha de vencimiento requerida');
                                  }
                                  if (value.isBefore(dayjs(), 'day')) {
                                    return Promise.reject('La fecha debe ser mayor o igual a hoy');
                                  }
                                }

                                // Para REMANENTES/SALIDAS: el vencimiento puede ser cualquier fecha
                                // (un remanente que vuelve de finca puede estar ya vencido y aún así debe registrarse)
                                return Promise.resolve();
                              }
                            }
                          ]}
                        >
                          <DatePicker
                            style={{ width: '100%' }}
                            format="DD/MM/YYYY"
                            placeholder="Vencimiento"
                            disabled={selectedSource?.source_type !== 'purchase'}
                            disabledDate={(current) => {
                              // Solo bloquear fechas pasadas en COMPRAS; remanentes permiten cualquier fecha
                              if (selectedSource?.source_type !== 'purchase') return false;
                              return current && current.isBefore(dayjs().startOf('day'));
                            }}
                          />
                        </Form.Item>
                      </Col>
                    </Row>
                  </Card>
                );
              })}
            </>
          )}
        </Form.List>

        <Form.Item
          name="observations"
          label="Observaciones Generales"
        >
          <TextArea
            rows={3}
            placeholder="Observaciones sobre esta recepción..."
          />
        </Form.Item>

        <div style={{ textAlign: 'right', marginTop: 24 }}>
          <Space>
            <Button onClick={() => {
              setIsNewBatchModalVisible(false);
              sourceReceptionForm.resetFields();
              setSelectedSource(null);
            }}>
              Cancelar
            </Button>
            <Button
              type="primary"
              htmlType="submit"
              loading={createDirectReceptionMutation.isPending}
              disabled={createDirectReceptionMutation.isPending}
            >
              Guardar Recepción Parcial
            </Button>
          </Space>
        </div>
      </Form>
    );
  };

  const renderNewBatchForm = () => (
    <Form
      form={batchForm}
      layout="vertical"
      onFinish={handleSavePartialReception}
    >
      <Row gutter={16}>
        <Col xs={24} sm={12}>
          <Form.Item
            name="receptionDate"
            label="Fecha de Recepción"
            rules={[{ required: true, message: 'La fecha es requerida' }]}
          >
            <DatePicker
              style={{ width: '100%' }}
              format="DD/MM/YYYY"
              placeholder="Seleccione la fecha"
            />
          </Form.Item>
        </Col>
        <Col xs={24} sm={12}>
          <Form.Item
            name="receivedBy"
            label="Recibido por"
            rules={[{ required: true, message: 'El responsable es requerido' }]}
          >
            <Select
              placeholder="Seleccione quien recibe"
              showSearch
              optionFilterProp="children"
              filterOption={(input, option) =>
                (option?.children as string)?.toLowerCase().includes(input.toLowerCase())
              }
            >
              {users
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

      <Divider>Productos a Recibir</Divider>

      <Form.List name="items">
        {(fields) => (
          <>
            {fields.map(({ key, name, ...restField }, index) => {
              const item = selectedReception?.items?.[index];
              if (!item) return null;

              const quantityPending = item.quantityExpected - (item.quantityReceived || 0);
              if (quantityPending <= 0) return null;

              return (
                <Card key={key} size="small" style={{ marginBottom: 16 }}>
                  <Row gutter={16}>
                    <Col xs={24} sm={6}>
                      <div>
                        <strong>{item.product?.name || 'N/A'}</strong>
                        <div style={{ color: '#666', fontSize: 12 }}>
                          Marca: {item.brand?.name || 'N/A'}
                        </div>
                      </div>
                    </Col>
                    <Col xs={8} sm={3}>
                      <Form.Item label="Cantidad Esperada">
                        <div style={{
                          textAlign: 'center',
                          padding: '5px 11px',
                          backgroundColor: '#f0f0f0',
                          border: '1px solid #d9d9d9',
                          borderRadius: '6px',
                          fontSize: '14px',
                          fontWeight: 'bold',
                          color: '#1890ff'
                        }}>
                          {formatQuantity(item.quantityExpected)}
                          <div style={{ fontSize: '11px', color: '#666', fontWeight: 'normal' }}>
                            {item.unit}
                          </div>
                        </div>
                      </Form.Item>
                    </Col>
                    <Col xs={8} sm={3}>
                      <Form.Item label="Cantidad Pendiente">
                        <div style={{
                          textAlign: 'center',
                          padding: '5px 11px',
                          backgroundColor: '#fff7e6',
                          border: '1px solid #ffd591',
                          borderRadius: '6px',
                          fontSize: '14px',
                          fontWeight: 'bold',
                          color: '#fa8c16'
                        }}>
                          {quantityPending}
                          <div style={{ fontSize: '11px', color: '#666', fontWeight: 'normal' }}>
                            {item.unit}
                          </div>
                        </div>
                      </Form.Item>
                    </Col>
                    <Col xs={8} sm={3}>
                      <Form.Item
                        {...restField}
                        name={[name, 'quantityToReceive']}
                        label="Cantidad a Recibir"
                        rules={[
                          {
                            validator: (_, value) => {
                              if (value && value > quantityPending) {
                                return Promise.reject('No puede ser mayor a lo pendiente');
                              }
                              return Promise.resolve();
                            }
                          }
                        ]}
                      >
                        <InputNumber
                          min={0}
                          max={quantityPending}
                          style={{ width: '100%' }}
                          placeholder="0"
                        />
                      </Form.Item>
                      <Form.Item
                        dependencies={[[name, 'quantityToReceive']]}
                        noStyle
                      >
                        {({ getFieldValue }) => {
                          const quantityToReceive = getFieldValue(['items', index, 'quantityToReceive']);
                          const equivalenceText = getEquivalenceText(item.productId, quantityToReceive || 0, item.unit);
                          return equivalenceText ? (
                            <div style={{
                              backgroundColor: '#e6f7ff',
                              border: '1px solid #91d5ff',
                              borderRadius: '4px',
                              padding: '6px 8px',
                              fontSize: '11px',
                              fontWeight: 'bold',
                              color: '#0958d9',
                              textAlign: 'center',
                              marginTop: '4px'
                            }}>
                              {equivalenceText}
                            </div>
                          ) : null;
                        }}
                      </Form.Item>
                    </Col>
                    <Col xs={12} sm={3}>
                      <Form.Item
                        {...restField}
                        name={[name, 'condition']}
                        label="Estado"
                        rules={[{ required: true, message: 'Estado requerido' }]}
                      >
                        <Select>
                          <Option value="good">Bueno</Option>
                          <Option value="damaged">Dañado</Option>
                          <Option value="expired">Vencido</Option>
                        </Select>
                      </Form.Item>
                    </Col>
                    <Col xs={12} sm={3}>
                      <Form.Item
                        dependencies={[[name, 'quantityToReceive']]}
                        {...restField}
                        name={[name, 'expirationDate']}
                        label="Fecha de Vencimiento"
                        rules={[
                          {
                            validator: (_, value) => {
                              const quantityToReceive = batchForm.getFieldValue(['items', name, 'quantityToReceive']);

                              // Solo para COMPRAS: vencimiento obligatorio y futuro
                              // (producto nuevo del proveedor no debe llegar ya vencido)
                              if (quantityToReceive > 0 && selectedReception?.sourceType === 'purchase') {
                                if (!value) {
                                  return Promise.reject('Fecha de vencimiento requerida');
                                }
                                if (value.isBefore(dayjs(), 'day')) {
                                  return Promise.reject('La fecha debe ser mayor o igual a hoy');
                                }
                              }

                              // Para REMANENTES/SALIDAS: el vencimiento puede ser cualquier fecha
                              // (un remanente que vuelve de finca puede estar ya vencido y aún así debe registrarse)
                              return Promise.resolve();
                            }
                          }
                        ]}
                      >
                        <DatePicker
                          style={{ width: '100%' }}
                          format="DD/MM/YYYY"
                          placeholder="Vencimiento"
                          disabled={selectedReception?.sourceType !== 'purchase'}
                          disabledDate={(current) => {
                            // Solo bloquear fechas pasadas en COMPRAS; remanentes permiten cualquier fecha
                            if (selectedReception?.sourceType !== 'purchase') return false;
                            return current && current.isBefore(dayjs().startOf('day'));
                          }}
                        />
                      </Form.Item>
                    </Col>
                    <Col xs={24} sm={3}>
                      <Form.Item
                        {...restField}
                        name={[name, 'observations']}
                        label="Observaciones"
                      >
                        <TextArea
                          rows={2}
                          placeholder="Observaciones..."
                        />
                      </Form.Item>
                    </Col>
                  </Row>
                </Card>
              );
            })}
          </>
        )}
      </Form.List>

      <Form.Item
        name="observations"
        label="Observaciones Generales"
      >
        <TextArea
          rows={3}
          placeholder="Observaciones sobre esta recepción parcial..."
        />
      </Form.Item>

      <div style={{ textAlign: 'right', marginTop: 24 }}>
        <Space>
          <Button onClick={() => setIsNewBatchModalVisible(false)}>
            Cancelar
          </Button>
          <Button
            type="primary"
            htmlType="submit"
            loading={addBatchMutation.isPending}
            disabled={addBatchMutation.isPending}
          >
            Confirmar Recepción Parcial
          </Button>
        </Space>
      </div>
    </Form>
  );

  return (
    <div style={{ overflow: 'hidden' }}>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Recepción Unificada</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Gestión de recepciones de compras y salidas con seguimiento parcial
          </p>
        </div>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={24} md={24}>
              <div style={{ display: 'flex', gap: 8, marginBottom: 16, flexWrap: 'wrap' }}>
                <Button
                  type={viewMode === 'available' ? 'primary' : 'default'}
                  onClick={() => setViewMode('available')}
                  icon={<InboxOutlined />}
                >
                  Disponibles para Recibir ({availableSources.length})
                </Button>
                <Button
                  type={viewMode === 'receptions' ? 'primary' : 'default'}
                  onClick={() => setViewMode('receptions')}
                  icon={<CheckCircleOutlined />}
                >
                  Recepciones ({receptions.length})
                </Button>
              </div>
            </Col>
            <Col xs={24} sm={8} md={6}>
              <Search
                placeholder={viewMode === 'available' ? 'Buscar por documento, producto o finca...' : 'Buscar por N° recepción, producto, código o finca...'}
                allowClear
                value={searchText}
                onChange={(e) => setSearchText(e.target.value)}
              />
            </Col>
            {viewMode === 'receptions' && (
              <Col xs={24} sm={8} md={6}>
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
                  <Option value="cancelled">Cancelada</Option>
                </Select>
              </Col>
            )}
            {viewMode === 'receptions' && (
              <Col xs={24} sm={8} md={6}>
                <DatePicker.RangePicker
                  style={{ width: '100%' }}
                  format="DD/MM/YYYY"
                  placeholder={['Fecha desde', 'Fecha hasta']}
                  value={dateRange as any}
                  onChange={(v) => setDateRange(v as any)}
                />
              </Col>
            )}
            <Col xs={24} sm={8} md={6}>
              <Select
                placeholder="Tipo de fuente"
                allowClear
                style={{ width: '100%' }}
                value={sourceTypeFilter}
                onChange={setSourceTypeFilter}
              >
                <Option value="purchase">Compras</Option>
                <Option value="output">Salidas</Option>
              </Select>
            </Col>
          </Row>
        </div>

        {viewMode === 'available' ? (
          <ResponsiveTable
            mobileColumns={availableSourcesMobileColumns}
            desktopColumns={availableSourcesColumns}
            dataSource={filteredAvailableSources}
            rowKey="id"
            loading={sourcesLoading || createReceptionMutation.isPending}
            entityName="fuentes disponibles"
            pagination={{
              total: filteredAvailableSources.length,
              pageSize: 10,
            }}
          />
        ) : (
          <ResponsiveTable
            mobileColumns={mobileColumns}
            desktopColumns={desktopColumns}
            dataSource={filteredReceptions}
            rowKey="id"
            loading={receptionsLoading || addBatchMutation.isPending}
            expandedRowRender={expandedRowRender}
            entityName="recepciones"
            pagination={{
              total: filteredReceptions.length,
              pageSize: 10,
            }}
          />
        )}
      </Card>

      {/* Modal principal de visualización */}
      {isMobile ? (
        <Drawer
          title={
            selectedReception
              ? `Recepción ${selectedReception.receptionNumber} - ${
                  selectedReception.sourceType === 'purchase'
                    ? `Compra ${selectedReception.sourceDetails?.orderNumber || ''}`
                    : `Salida ${selectedReception.sourceDetails?.outputNumber || ''}`
                }`
              : 'Recepción'
          }
          placement="bottom"
          height="90%"
          open={isModalVisible}
          onClose={() => {
            setIsModalVisible(false);
            setSelectedReception(null);
          }}
          styles={{
            body: { padding: 16 }
          }}
        >
          {renderReceptionDetails()}
        </Drawer>
      ) : (
        <Modal
          title={
            selectedReception
              ? `Recepción ${selectedReception.receptionNumber} - ${
                  selectedReception.sourceType === 'purchase'
                    ? `Compra ${selectedReception.sourceDetails?.orderNumber || ''}`
                    : `Salida ${selectedReception.sourceDetails?.outputNumber || ''}`
                }`
              : 'Recepción'
          }
          open={isModalVisible}
          onCancel={() => {
            setIsModalVisible(false);
            setSelectedReception(null);
          }}
          footer={null}
          width={1200}
        >
          {renderReceptionDetails()}
        </Modal>
      )}

      {/* Modal para nueva recepción parcial */}
      <Modal
        title={
          selectedSource
            ? `Primera Recepción - ${selectedSource.source_type === 'purchase' ? 'Compra' : 'Salida'} ${selectedSource.document_number}`
            : `Nueva Recepción Parcial - Batch #${(selectedReception?.batches?.length || 0) + 1}`
        }
        open={isNewBatchModalVisible}
        onCancel={() => {
          setIsNewBatchModalVisible(false);
          if (selectedSource) {
            sourceReceptionForm.resetFields();
            setSelectedSource(null);
          } else {
            batchForm.resetFields();
          }
        }}
        footer={null}
        width={1400}
      >
        {selectedSource ? renderSourceReceptionForm() : renderNewBatchForm()}
      </Modal>

      {/* Modal para ver fuente disponible (solo informativo) */}
      <Modal
        title={`Información de ${selectedSource ? getSourceTypeText(selectedSource.source_type) : ''} - ${selectedSource?.document_number || ''}`}
        open={isSourceDetailsModalVisible}
        onCancel={() => {
          setIsSourceDetailsModalVisible(false);
          setSelectedSource(null);
        }}
        footer={null}
        width={1200}
      >
        {sourceDetailsLoading && (
          <div style={{ textAlign: 'center', padding: '40px' }}>
            <p>Cargando productos...</p>
          </div>
        )}
        {!sourceDetailsLoading && !selectedSourceDetailsData && selectedSource && (
          <Alert
            message="Error al cargar"
            description="No se pudieron cargar los detalles de la fuente."
            type="error"
            showIcon
          />
        )}
        {selectedSourceDetailsData && selectedSource && !sourceDetailsLoading && (
          <div>
            <Alert
              message="Información del Documento"
              description={
                <Descriptions size="small" column={2}>
                  <Descriptions.Item label="Documento">{selectedSource.document_number}</Descriptions.Item>
                  <Descriptions.Item label="Fecha">{dayjs(selectedSource.date).format('DD/MM/YYYY')}</Descriptions.Item>
                  {selectedSource.source_type === 'purchase' ? (
                    <Descriptions.Item label="Proveedor">{selectedSource.supplier_name}</Descriptions.Item>
                  ) : (
                    <Descriptions.Item label="Origen">{selectedSource.origin_location?.name}</Descriptions.Item>
                  )}
                  <Descriptions.Item label="Destino">{selectedSource.destination_location?.name}</Descriptions.Item>
                </Descriptions>
              }
              type="info"
              style={{ marginBottom: 24 }}
            />

            <Divider>Productos Esperados</Divider>

            {(() => {
              const sourceDetails = selectedSourceDetailsData.data;
              const items = selectedSource.source_type === 'purchase'
                ? sourceDetails.items || []
                : sourceDetails.products || [];

              if (items.length === 0) {
                return (
                  <Alert
                    message="No hay productos"
                    description="Esta fuente no tiene productos asociados."
                    type="warning"
                    showIcon
                  />
                );
              }

              // Calculate received quantities per SOURCE ITEM (keyed by source item ID, not product_id)
              // This correctly handles duplicate products (same product_id+brand_id, different batches)
              const receivedBySourceItem = new Map<string, number>();
              if (selectedSource.reception?.batches && Array.isArray(selectedSource.reception.batches)) {
                // Build lookup: reception_item_id → source_item_id
                const receptionItemToSourceItem = new Map<string, string>();
                if (selectedSource.reception?.items) {
                  selectedSource.reception.items.forEach((ri: any) => {
                    if (ri.id && ri.sourceItemId) {
                      receptionItemToSourceItem.set(ri.id, ri.sourceItemId);
                    }
                  });
                }

                selectedSource.reception.batches.forEach((batch: any) => {
                  if (batch.items && Array.isArray(batch.items)) {
                    batch.items.forEach((batchItem: any) => {
                      const receptionItemId = batchItem.reception_item_id;
                      const sourceItemId = receptionItemId
                        ? receptionItemToSourceItem.get(receptionItemId)
                        : null;
                      // Use sourceItemId as key (unique per source item), fallback to product_id
                      const key = sourceItemId || batchItem.product_id;
                      const currentReceived = receivedBySourceItem.get(key) || 0;
                      const qtyReceived = parseFloat(batchItem.quantity_received);
                      if (!isNaN(qtyReceived)) {
                        receivedBySourceItem.set(key, currentReceived + qtyReceived);
                      }
                    });
                  }
                });
              }

              const totalItems = items.length;
              const totalQuantity = items.reduce((sum: number, item: any) => {
                // For purchases, use base units. For outputs, use 'quantityDelivered'
                const qty = selectedSource.source_type === 'purchase'
                  ? (parseFloat(item.quantityInBaseUnits) || parseFloat(item.quantity))
                  : (parseFloat(item.quantityDelivered) || parseFloat(item.quantity_delivered));
                return sum + (isNaN(qty) ? 0 : qty);
              }, 0);
              const totalReceived = Array.from(receivedBySourceItem.values()).reduce((sum, qty) => sum + qty, 0);

              return (
                <>
                  <Alert
                    message={
                      <div>
                        <strong>Resumen:</strong> {totalItems} productos diferentes • Cantidad total esperada: {totalQuantity} unidades • Recibido: {totalReceived} unidades
                        {selectedSource.reception && (
                          <Tag color={selectedSource.reception.status === 'completed' ? 'success' : selectedSource.reception.status === 'partial' ? 'processing' : 'default'} style={{ marginLeft: 8 }}>
                            {selectedSource.reception.status === 'pending' ? 'Pendiente' : selectedSource.reception.status === 'partial' ? 'Parcial' : 'Completada'}
                          </Tag>
                        )}
                      </div>
                    }
                    type="info"
                    style={{ marginBottom: 16 }}
                  />

                  {items.map((item: any, index: number) => {
                    // Map fields based on source type
                    const productName = selectedSource.source_type === 'purchase'
                      ? item.productName
                      : item.product?.name;

                    const brandName = selectedSource.source_type === 'purchase'
                      ? item.brandName
                      : item.brand?.name;

                    const productId = item.productId || item.product_id;
                    // For purchases, use base units. For outputs, use 'quantityDelivered'
                    const quantityExpected = selectedSource.source_type === 'purchase'
                      ? (parseFloat(item.quantityInBaseUnits) || parseFloat(item.quantity) || 0)
                      : (parseFloat(item.quantityDelivered) || parseFloat(item.quantity_delivered) || 0);
                    const quantityReceived = receivedBySourceItem.get(item.id) || 0;
                    const progressPercent = quantityExpected > 0 ? Math.round((quantityReceived / quantityExpected) * 100) : 0;

                    // Build unit display - quantities are in base units
                    let unitDisplay = '';
                    if (selectedSource.source_type === 'purchase') {
                      unitDisplay = item.packagingUnitBaseUnit || item.unit;
                    } else {
                      unitDisplay = item.unit;
                    }

                    const quantityPending = Math.max(0, quantityExpected - quantityReceived);

                    return (
                      <Card key={index} size="small" style={{ marginBottom: 12 }}>
                        <Row gutter={16} align="middle">
                          <Col xs={24} sm={8}>
                            <div>
                              <strong>{productName || 'N/A'}</strong>
                              <div style={{ color: '#666', fontSize: 12 }}>
                                {brandName && `Marca: ${brandName}`}
                              </div>
                            </div>
                          </Col>
                          <Col xs={8} sm={3}>
                            <div style={{ textAlign: 'center' }}>
                              <div style={{ fontSize: 12, color: '#666' }}>Esperado</div>
                              <strong style={{ fontSize: 16 }}>{formatQuantity(quantityExpected)}</strong>
                              <div style={{ fontSize: 11, color: '#999' }}>{unitDisplay}</div>
                            </div>
                          </Col>
                          <Col xs={8} sm={3}>
                            <div style={{ textAlign: 'center' }}>
                              <div style={{ fontSize: 12, color: '#666' }}>Recibido</div>
                              <strong style={{ fontSize: 16, color: quantityReceived > 0 ? '#52c41a' : '#999' }}>
                                {formatQuantity(quantityReceived)}
                              </strong>
                              <div style={{ fontSize: 11, color: '#999' }}>{unitDisplay}</div>
                            </div>
                          </Col>
                          <Col xs={8} sm={3}>
                            <div style={{ textAlign: 'center' }}>
                              <div style={{ fontSize: 12, color: '#666' }}>Pendiente</div>
                              <strong style={{ fontSize: 16, color: quantityPending > 0 ? '#ff9800' : '#999' }}>
                                {formatQuantity(quantityPending)}
                              </strong>
                              <div style={{ fontSize: 11, color: '#999' }}>{unitDisplay}</div>
                            </div>
                          </Col>
                          <Col xs={24} sm={7}>
                            <div style={{ padding: '8px 0' }}>
                              <Progress
                                percent={progressPercent}
                                size="small"
                                status={progressPercent === 100 ? 'success' : progressPercent > 0 ? 'active' : 'normal'}
                              />
                              <div style={{ fontSize: 11, color: '#999', textAlign: 'center', marginTop: 4 }}>
                                {progressPercent === 100 ? 'Completo' : progressPercent > 0 ? 'Parcial' : 'Pendiente'}
                              </div>
                            </div>
                          </Col>
                        </Row>
                      </Card>
                    );
                  })}

                  {/* Timeline de recepciones */}
                  {selectedSource.reception?.batches && selectedSource.reception.batches.length > 0 && (
                    <>
                      <Divider>Historial de Recepciones</Divider>
                      <Timeline
                        mode="left"
                        items={selectedSource.reception.batches.map((batch: any, idx: number) => ({
                          color: 'green',
                          children: (
                            <div>
                              <div style={{ marginBottom: 8 }}>
                                <strong>Recepción #{batch.batch_number}</strong>
                                <Tag color="blue" style={{ marginLeft: 8 }}>
                                  {dayjs(batch.reception_date).format('DD/MM/YYYY HH:mm')}
                                </Tag>
                              </div>
                              <div style={{ fontSize: 12, color: '#666', marginBottom: 4 }}>
                                Recibido por: <strong>{batch.received_by}</strong>
                              </div>
                              {batch.items && batch.items.length > 0 && (
                                <div style={{ marginTop: 8 }}>
                                  <div style={{ fontSize: 12, fontWeight: 500, marginBottom: 4 }}>Productos recibidos:</div>
                                  {batch.items.map((batchItem: any, itemIdx: number) => {
                                    // Use reception_item_id to find exact source item for duplicates
                                    const receptionItem = batchItem.reception_item_id
                                      ? selectedSource.reception?.items?.find((ri: any) => ri.id === batchItem.reception_item_id)
                                      : null;
                                    const sourceItemId = receptionItem?.sourceItemId;
                                    const itemDetails = sourceItemId
                                      ? items.find((i: any) => i.id === sourceItemId)
                                      : items.find((i: any) => (i.productId || i.product_id) === batchItem.product_id);
                                    const productName = selectedSource.source_type === 'purchase'
                                      ? itemDetails?.productName
                                      : itemDetails?.product?.name;

                                    return (
                                      <div key={itemIdx} style={{ fontSize: 12, color: '#666', marginLeft: 12 }}>
                                        • {productName || 'Producto'}: <strong>{formatQuantity(batchItem.quantity_received)}</strong> unidades
                                        <Tag color={batchItem.condition === 'good' ? 'success' : batchItem.condition === 'damaged' ? 'warning' : 'error'} style={{ marginLeft: 4 }}>
                                          {batchItem.condition === 'good' ? 'Bueno' : batchItem.condition === 'damaged' ? 'Dañado' : 'Vencido'}
                                        </Tag>
                                      </div>
                                    );
                                  })}
                                </div>
                              )}
                              {batch.observations && (
                                <div style={{ fontSize: 12, color: '#999', marginTop: 4, fontStyle: 'italic' }}>
                                  Observaciones: {batch.observations}
                                </div>
                              )}
                            </div>
                          ),
                        }))}
                      />
                    </>
                  )}

                  {(() => {
                    // Check if there are any items with pending quantities
                    const hasPendingItems = items.some((item: any) => {
                      const productId = item.productId || item.product_id;
                      // For purchases, use base units. For outputs, use 'quantityDelivered'
                      const quantityExpected = selectedSource.source_type === 'purchase'
                        ? (parseFloat(item.quantityInBaseUnits) || parseFloat(item.quantity) || 0)
                        : (parseFloat(item.quantityDelivered) || parseFloat(item.quantity_delivered) || 0);
                      const quantityReceived = receivedBySourceItem.get(item.id) || 0;
                      const quantityPending = quantityExpected - quantityReceived;
                      return quantityPending > 0;
                    });

                    if (!hasPendingItems) {
                      return (
                        <div style={{ textAlign: 'center', marginTop: 24 }}>
                          <Alert
                            message="Recepción Completada"
                            description="Todos los productos de esta orden han sido completamente recepcionados."
                            type="success"
                            showIcon
                          />
                        </div>
                      );
                    }

                    return (
                      <div style={{ textAlign: 'center', marginTop: 24 }}>
                        <Space wrap>
                          <Button
                            type="primary"
                            icon={selectedSource.reception ? <PlusOutlined /> : <PlusOutlined />}
                            size="large"
                            onClick={handleOpenReceptionFromSource}
                          >
                            {selectedSource.reception ? 'Nueva Recepción Parcial' : 'Crear Recepción'}
                          </Button>
                          {selectedSource.reception?.id && (
                            <Popconfirm
                              title={finalizeTitle}
                              description={finalizeDescription}
                              okText="Sí, finalizar y liberar"
                              cancelText="Cancelar"
                              okButtonProps={{ danger: true }}
                              onConfirm={() => doFinalize(selectedSource.reception.id)}
                            >
                              <Button danger size="large" loading={finalizeMutation.isPending}>
                                Finalizar recepción
                              </Button>
                            </Popconfirm>
                          )}
                        </Space>
                        <div style={{ marginTop: 8, fontSize: 12, color: '#666' }}>
                          {selectedSource.reception
                            ? 'Agregar un nuevo lote de recepción a esta orden'
                            : 'Se abrirá el formulario para recepcionar productos'
                          }
                          {selectedSource.reception?.id && (
                            <span> · "Finalizar recepción" cierra con lo recibido y libera el remanente pendiente.</span>
                          )}
                        </div>
                      </div>
                    );
                  })()}
                </>
              );
            })()}
          </div>
        )}
      </Modal>
    </div>
  );
};

export default ReceptionPage;