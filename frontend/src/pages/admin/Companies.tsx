import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, Switch, Upload, Spin, Typography } from 'antd';
import { PlusOutlined, EditOutlined, UploadOutlined, DeleteOutlined, PictureOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import ResponsiveTable from '../../components/ResponsiveTable';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { companiesApi, handleApiError } from '../../services/api';
import type { Company, CompanyPayload } from '../../types/index';

const { Search } = Input;
const { Option } = Select;
const { Text } = Typography;

/**
 * Tope de peso del logo. Debe coincidir con `CompanyController::LOGO_MAX_KB`:
 * validarlo aquí evita subir medio mega para que el backend lo rechace después.
 */
const LOGO_MAX_KB = 512;
const LOGO_MAX_BYTES = LOGO_MAX_KB * 1024;

const ACCEPTED_LOGO_TYPES = ['image/png', 'image/jpeg'];

/**
 * Plantillas de membrete disponibles en el backend
 * (backend/resources/views/pdf/remision/*.blade.php).
 */
const TEMPLATE_OPTIONS = [
  { value: 'clasico', label: 'Clásico' },
  { value: 'agrilogistic', label: 'Agrilogistic' },
  { value: 'avoterra', label: 'Avoterra' },
  { value: 'florez', label: 'Flórez' },
];

const Companies: React.FC = () => {
  const queryClient = useQueryClient();
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingCompany, setEditingCompany] = useState<Company | null>(null);
  const [searchText, setSearchText] = useState('');
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [logoPreviewUrl, setLogoPreviewUrl] = useState<string | null>(null);
  const [isLoadingLogo, setIsLoadingLogo] = useState(false);
  const [form] = Form.useForm();

  // Evita el doble submit (chequeo síncrono, igual que el resto de pantallas).
  const isSubmittingRef = useRef(false);

  const { data: companiesData, isLoading } = useQuery({
    queryKey: ['companies', searchText, statusFilter],
    queryFn: () => companiesApi.list({
      search: searchText || undefined,
      status: statusFilter || undefined,
    }),
  });

  const companies = useMemo(() => companiesData?.data || [], [companiesData]);

  const createMutation = useMutation({
    mutationFn: (data: CompanyPayload) => companiesApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      closeModal();
      message.success('Empresa creada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al crear la empresa', form);
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: CompanyPayload }) => companiesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      closeModal();
      message.success('Empresa actualizada exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al actualizar la empresa', form);
    },
  });

  const uploadLogoMutation = useMutation({
    mutationFn: ({ id, file }: { id: string; file: File }) => companiesApi.uploadLogo(id, file),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      // Refresca la empresa en edición para que la vista previa vuelva a pedir el
      // logo nuevo (el efecto de abajo depende de hasLogo/updatedAt).
      setEditingCompany(response.data);
      message.success('Logo actualizado exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al subir el logo');
    },
  });

  const deleteLogoMutation = useMutation({
    mutationFn: (id: string) => companiesApi.deleteLogo(id),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['companies'] });
      setEditingCompany(response.data);
      message.success('Logo eliminado exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al eliminar el logo');
    },
  });

  /**
   * Vista previa del logo. El JSON nunca trae la imagen (solo `hasLogo`), así que
   * hay que pedirla aparte; y no se puede usar `<img src=".../logo">` porque el
   * endpoint exige el header Authorization. El object URL se libera siempre al
   * cambiar de empresa o cerrar el modal para no filtrar memoria.
   */
  useEffect(() => {
    if (!editingCompany?.hasLogo) {
      setLogoPreviewUrl(null);
      return;
    }

    let cancelled = false;
    let objectUrl: string | null = null;
    const companyId = editingCompany.id;

    setIsLoadingLogo(true);
    companiesApi.getLogoObjectUrl(companyId)
      .then((url) => {
        if (cancelled) {
          window.URL.revokeObjectURL(url);
          return;
        }
        objectUrl = url;
        setLogoPreviewUrl(url);
      })
      .catch(() => {
        if (!cancelled) setLogoPreviewUrl(null);
      })
      .finally(() => {
        if (!cancelled) setIsLoadingLogo(false);
      });

    return () => {
      cancelled = true;
      if (objectUrl) window.URL.revokeObjectURL(objectUrl);
    };
  }, [editingCompany?.id, editingCompany?.hasLogo, editingCompany?.updatedAt]);

  const closeModal = () => {
    setIsModalVisible(false);
    form.resetFields();
    setEditingCompany(null);
    setLogoPreviewUrl(null);
  };

  const handleCreate = () => {
    setEditingCompany(null);
    form.resetFields();
    form.setFieldsValue({ template: 'clasico', status: 'active', isDefault: false });
    setIsModalVisible(true);
  };

  const handleEdit = (record: Company) => {
    setEditingCompany(record);
    form.setFieldsValue({
      name: record.name,
      nit: record.nit,
      address: record.address,
      city: record.city,
      phone: record.phone,
      email: record.email,
      legalRep: record.legalRep,
      taxRegime: record.taxRegime,
      ciiu: record.ciiu,
      template: record.template || 'clasico',
      isDefault: record.isDefault,
      status: record.status,
    });
    setIsModalVisible(true);
  };

  const handleSave = (values: any) => {
    if (isSubmittingRef.current) {
      return;
    }
    isSubmittingRef.current = true;

    // El backend valida en snake_case (StoreCompanyRequest/UpdateCompanyRequest).
    const payload: CompanyPayload = {
      name: values.name,
      nit: values.nit,
      address: values.address || null,
      city: values.city || null,
      phone: values.phone || null,
      email: values.email || null,
      legal_rep: values.legalRep || null,
      tax_regime: values.taxRegime || null,
      ciiu: values.ciiu || null,
      template: values.template || 'clasico',
      is_default: values.isDefault || false,
      status: values.status || 'active',
    };

    const mutationOptions = {
      onSettled: () => {
        isSubmittingRef.current = false;
      },
    };

    if (editingCompany) {
      updateMutation.mutate({ id: editingCompany.id, data: payload }, mutationOptions);
    } else {
      createMutation.mutate(payload, mutationOptions);
    }
  };

  /**
   * Validación del logo ANTES de subirlo: tipo (PNG/JPG) y peso (512 KB).
   * Devuelve `Upload.LIST_IGNORE`/`false` para que antd nunca haga la petición
   * automática: la subida la dispara la mutación con el token de sesión.
   */
  const beforeLogoUpload = (file: File) => {
    if (!ACCEPTED_LOGO_TYPES.includes(file.type)) {
      message.error('El logo debe ser una imagen PNG o JPG');
      return Upload.LIST_IGNORE;
    }

    if (file.size > LOGO_MAX_BYTES) {
      const sizeKb = Math.round(file.size / 1024);
      message.error(`El logo no puede exceder ${LOGO_MAX_KB} KB (el archivo pesa ${sizeKb} KB)`);
      return Upload.LIST_IGNORE;
    }

    if (editingCompany) {
      uploadLogoMutation.mutate({ id: editingCompany.id, file });
    }

    return false;
  };

  const mobileColumns: ColumnsType<Company> = [
    {
      title: 'Empresa',
      key: 'company',
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: 14, marginBottom: 4 }}>
            {record.name}
            {record.isDefault && <Tag color="green" style={{ marginLeft: 8 }}>Por defecto</Tag>}
          </div>
          <div style={{ color: '#666', fontSize: 12 }}>NIT: {record.nit}</div>
          <div style={{ color: '#999', fontSize: 12 }}>{record.city || 'Sin ciudad'}</div>
          <div style={{ marginTop: 4 }}>
            <Tag color={record.status === 'active' ? 'success' : 'default'}>
              {record.status === 'active' ? 'Activa' : 'Inactiva'}
            </Tag>
            <Tag color={record.hasLogo ? 'blue' : 'default'} icon={<PictureOutlined />}>
              {record.hasLogo ? 'Con logo' : 'Sin logo'}
            </Tag>
          </div>
        </div>
      ),
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 70,
      fixed: 'right' as const,
      render: (_, record) => (
        <Button
          type="link"
          icon={<EditOutlined />}
          onClick={() => handleEdit(record)}
          size="small"
        />
      ),
    },
  ];

  const desktopColumns: ColumnsType<Company> = [
    {
      title: 'Nombre',
      dataIndex: 'name',
      key: 'name',
      render: (name: string, record) => (
        <span>
          {name}
          {record.isDefault && <Tag color="green" style={{ marginLeft: 8 }}>Por defecto</Tag>}
        </span>
      ),
      sorter: (a, b) => a.name.localeCompare(b.name),
    },
    {
      title: 'NIT',
      dataIndex: 'nit',
      key: 'nit',
    },
    {
      title: 'Ciudad',
      dataIndex: 'city',
      key: 'city',
      render: (city?: string | null) => city || <Text type="secondary">-</Text>,
    },
    {
      title: 'Plantilla',
      dataIndex: 'template',
      key: 'template',
      render: (template: string) => (
        <Tag color="purple">
          {TEMPLATE_OPTIONS.find((t) => t.value === template)?.label || template || 'clasico'}
        </Tag>
      ),
    },
    {
      title: 'Logo',
      dataIndex: 'hasLogo',
      key: 'hasLogo',
      align: 'center' as const,
      render: (hasLogo: boolean) => (
        <Tag color={hasLogo ? 'blue' : 'default'} icon={<PictureOutlined />}>
          {hasLogo ? 'Sí' : 'No'}
        </Tag>
      ),
    },
    {
      title: 'Estado',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={status === 'active' ? 'success' : 'default'}>
          {status === 'active' ? 'Activa' : 'Inactiva'}
        </Tag>
      ),
    },
    {
      title: 'Acciones',
      key: 'actions',
      fixed: 'right' as const,
      width: 130,
      render: (_, record) => (
        <Button
          type="link"
          icon={<EditOutlined />}
          onClick={() => handleEdit(record)}
        >
          Editar
        </Button>
      ),
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Empresas</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Empresas emisoras de compras, salidas y remisiones
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={handleCreate}
        >
          Nueva Empresa
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} md={8}>
              <Search
                placeholder="Buscar por nombre o NIT..."
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
                <Option value="active">Activa</Option>
                <Option value="inactive">Inactiva</Option>
              </Select>
            </Col>
          </Row>
        </div>

        <ResponsiveTable
          mobileColumns={mobileColumns}
          desktopColumns={desktopColumns}
          dataSource={companies}
          rowKey="id"
          loading={isLoading}
          entityName="empresas"
          pagination={{
            total: companies.length,
            pageSize: 10,
          }}
        />
      </Card>

      <Modal
        title={editingCompany ? `Editar ${editingCompany.name}` : 'Nueva Empresa'}
        open={isModalVisible}
        onCancel={closeModal}
        footer={null}
        width={800}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSave}
        >
          <Row gutter={16}>
            <Col xs={24} sm={14}>
              <Form.Item
                name="name"
                label="Nombre"
                rules={[
                  { required: true, message: 'El nombre es requerido' },
                  { max: 150, message: 'El nombre no puede exceder 150 caracteres' },
                ]}
              >
                <Input placeholder="Ej: AGRILOGISTIC URRAO SAS" />
              </Form.Item>
            </Col>
            <Col xs={24} sm={10}>
              <Form.Item
                name="nit"
                label="NIT"
                rules={[
                  { required: true, message: 'El NIT es requerido' },
                  { max: 20, message: 'El NIT no puede exceder 20 caracteres' },
                ]}
              >
                <Input placeholder="Ej: 901441688-7" />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col xs={24} sm={14}>
              <Form.Item
                name="address"
                label="Dirección"
                rules={[{ max: 200, message: 'La dirección no puede exceder 200 caracteres' }]}
              >
                <Input placeholder="Ej: Carrera 29 # 30-42" />
              </Form.Item>
            </Col>
            <Col xs={24} sm={10}>
              <Form.Item
                name="city"
                label="Ciudad"
                rules={[{ max: 100, message: 'La ciudad no puede exceder 100 caracteres' }]}
              >
                <Input placeholder="Ej: Urrao, Antioquia" />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col xs={24} sm={12}>
              <Form.Item
                name="phone"
                label="Teléfono"
                rules={[{ max: 100, message: 'El teléfono no puede exceder 100 caracteres' }]}
              >
                <Input placeholder="Ej: 604 408 8580" />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item
                name="email"
                label="Correo electrónico"
                rules={[
                  { type: 'email', message: 'El correo electrónico no es válido' },
                  { max: 150, message: 'El correo no puede exceder 150 caracteres' },
                ]}
              >
                <Input placeholder="Ej: info@empresa.co" />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col xs={24} sm={12}>
              <Form.Item
                name="legalRep"
                label="Representante legal"
                rules={[{ max: 150, message: 'No puede exceder 150 caracteres' }]}
              >
                <Input placeholder="Nombre del representante legal" />
              </Form.Item>
            </Col>
            <Col xs={24} sm={12}>
              <Form.Item
                name="ciiu"
                label="Código CIIU"
                rules={[{ max: 50, message: 'No puede exceder 50 caracteres' }]}
              >
                <Input placeholder="Ej: 0161" />
              </Form.Item>
            </Col>
          </Row>

          <Form.Item
            name="taxRegime"
            label="Régimen tributario"
            rules={[{ max: 200, message: 'No puede exceder 200 caracteres' }]}
          >
            <Input placeholder="Ej: Responsable de IVA · Facturador electrónico" />
          </Form.Item>

          <Row gutter={16}>
            <Col xs={24} sm={8}>
              <Form.Item
                name="template"
                label="Plantilla"
                rules={[{ required: true, message: 'La plantilla es requerida' }]}
                tooltip="Diseño del membrete usado en la remisión en PDF"
              >
                <Select options={TEMPLATE_OPTIONS} />
              </Form.Item>
            </Col>
            <Col xs={24} sm={8}>
              <Form.Item
                name="status"
                label="Estado"
                rules={[{ required: true, message: 'El estado es requerido' }]}
              >
                <Select>
                  <Option value="active">Activa</Option>
                  <Option value="inactive">Inactiva</Option>
                </Select>
              </Form.Item>
            </Col>
            <Col xs={24} sm={8}>
              <Form.Item
                name="isDefault"
                label="Empresa por defecto"
                valuePropName="checked"
                tooltip="Se preselecciona al crear compras y salidas. Solo puede haber una."
              >
                <Switch />
              </Form.Item>
            </Col>
          </Row>

          {/* El logo solo se puede gestionar sobre una empresa ya creada:
              el endpoint es POST /companies/{id}/logo. */}
          {editingCompany && (
            <Card size="small" title="Logo del membrete" style={{ marginBottom: 16 }}>
              <Row gutter={[16, 16]} align="middle">
                <Col xs={24} sm={10}>
                  {isLoadingLogo ? (
                    <Spin />
                  ) : logoPreviewUrl ? (
                    <img
                      src={logoPreviewUrl}
                      alt={`Logo de ${editingCompany.name}`}
                      style={{
                        maxWidth: '100%',
                        maxHeight: 120,
                        objectFit: 'contain',
                        border: '1px solid #f0f0f0',
                        borderRadius: 4,
                        padding: 8,
                      }}
                    />
                  ) : (
                    <Text type="secondary">Sin logo cargado</Text>
                  )}
                </Col>
                <Col xs={24} sm={14}>
                  <Space direction="vertical" size="small" style={{ width: '100%' }}>
                    <Upload
                      accept="image/png,image/jpeg"
                      showUploadList={false}
                      maxCount={1}
                      beforeUpload={beforeLogoUpload}
                    >
                      <Button
                        icon={<UploadOutlined />}
                        loading={uploadLogoMutation.isPending}
                      >
                        {editingCompany.hasLogo ? 'Reemplazar logo' : 'Subir logo'}
                      </Button>
                    </Upload>
                    {editingCompany.hasLogo && (
                      <Popconfirm
                        title="¿Eliminar el logo?"
                        description="El membrete quedará solo con texto"
                        onConfirm={() => deleteLogoMutation.mutate(editingCompany.id)}
                        okText="Sí"
                        cancelText="No"
                      >
                        <Button
                          danger
                          icon={<DeleteOutlined />}
                          loading={deleteLogoMutation.isPending}
                        >
                          Eliminar logo
                        </Button>
                      </Popconfirm>
                    )}
                    <Text type="secondary" style={{ fontSize: 12 }}>
                      PNG o JPG, máximo {LOGO_MAX_KB} KB.
                    </Text>
                  </Space>
                </Col>
              </Row>
            </Card>
          )}

          <div style={{ textAlign: 'right', marginTop: 24 }}>
            <Space>
              <Button onClick={closeModal}>
                Cancelar
              </Button>
              <Button
                type="primary"
                htmlType="submit"
                loading={createMutation.isPending || updateMutation.isPending}
                disabled={createMutation.isPending || updateMutation.isPending}
              >
                {editingCompany ? 'Actualizar' : 'Crear'} Empresa
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default Companies;
