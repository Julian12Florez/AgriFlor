import React, { useMemo, useRef, useState } from 'react';
import { Alert, Button, Card, Checkbox, Col, Form, Input, Modal, Popconfirm, Row, Space, Table, Tag, Tooltip, Typography, message } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, EyeOutlined, SafetyOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { rolesApi, handleApiError } from '../../services/api';
import type { PermissionAction, PermissionItem, PermissionModule, Profile, ProfilePayload } from '../../types/index';

const { Text } = Typography;

/** Columnas fijas de la tabla de permisos. Lo demás va en "Otras acciones". */
const COLUMNAS: { action: PermissionAction; titulo: string }[] = [
  { action: 'view', titulo: 'Ver' },
  { action: 'create', titulo: 'Crear' },
  { action: 'edit', titulo: 'Editar' },
  { action: 'delete', titulo: 'Eliminar' },
];

/** Una fila de la tabla: un grupo ("Productos", "Compras"...) con sus permisos por acción. */
interface FilaGrupo {
  grupo: string;
  porAccion: Partial<Record<PermissionAction, PermissionItem>>;
  especiales: PermissionItem[];
}

/**
 * Arma las filas de un módulo. El permiso de menú no va en la tabla: es la
 * casilla "Aparece en el menú" de la cabecera del módulo.
 */
const filasDe = (modulo: PermissionModule): FilaGrupo[] => {
  const filas: FilaGrupo[] = [];
  modulo.permissions.filter((p) => !p.isMenu).forEach((permiso) => {
    let fila = filas.find((f) => f.grupo === permiso.group);
    if (!fila) {
      fila = { grupo: permiso.group, porAccion: {}, especiales: [] };
      filas.push(fila);
    }
    if (permiso.action === 'special') {
      fila.especiales.push(permiso);
    } else {
      fila.porAccion[permiso.action] = permiso;
    }
  });
  return filas;
};

/**
 * Administración → Perfiles.
 *
 * Aquí se decide qué ve y qué puede hacer cada perfil. Lo que se guarda es lo
 * que obedece todo el sistema: el menú sale de la casilla "Aparece en el menú"
 * de cada sección y el backend rechaza cualquier acción que no esté marcada.
 */
const Profiles: React.FC = () => {
  const queryClient = useQueryClient();
  const [form] = Form.useForm();
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingProfile, setEditingProfile] = useState<Profile | null>(null);
  const [marcados, setMarcados] = useState<Set<string>>(new Set());

  // Evita el doble submit (chequeo síncrono, igual que el resto de pantallas).
  const isSubmittingRef = useRef(false);

  // Sin caché: es la pantalla donde se decide el acceso, siempre muestra lo
  // que hay guardado (otro administrador pudo haberlo cambiado).
  const { data: profilesData, isLoading } = useQuery({
    queryKey: ['roles'],
    queryFn: () => rolesApi.list(),
    staleTime: 0,
  });
  const { data: catalogData } = useQuery({
    queryKey: ['roles-catalog'],
    queryFn: () => rolesApi.catalog(),
    staleTime: 30 * 60 * 1000,
  });

  const profiles = useMemo(() => profilesData?.data || [], [profilesData]);
  const modulos = useMemo(() => catalogData?.data || [], [catalogData]);
  const totalPermisos = useMemo(() => modulos.reduce((n, m) => n + m.permissions.length, 0), [modulos]);

  // Solo lectura: el Administrador (acceso total) se puede ver pero no cambiar.
  const soloLectura = !!editingProfile && !editingProfile.editable;

  const refrescar = () => {
    queryClient.invalidateQueries({ queryKey: ['roles'] });
    queryClient.invalidateQueries({ queryKey: ['roles-options'] });
    queryClient.invalidateQueries({ queryKey: ['users'] });
    // Si el perfil editado es el de quien está en sesión, su menú cambia ya.
    queryClient.invalidateQueries({ queryKey: ['auth-me'] });
  };

  const cerrar = () => {
    setIsModalVisible(false);
    setEditingProfile(null);
    setMarcados(new Set());
    form.resetFields();
  };

  const createMutation = useMutation({
    mutationFn: (data: ProfilePayload) => rolesApi.create(data),
    onSuccess: () => {
      refrescar();
      cerrar();
      message.success('Perfil creado exitosamente');
    },
    onError: (error: any) => handleApiError(error, 'Error al crear el perfil', form),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: ProfilePayload }) => rolesApi.update(id, data),
    onSuccess: () => {
      refrescar();
      cerrar();
      message.success('Perfil actualizado exitosamente');
    },
    onError: (error: any) => handleApiError(error, 'Error al actualizar el perfil', form),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => rolesApi.delete(id),
    onSuccess: () => {
      refrescar();
      message.success('Perfil eliminado exitosamente');
    },
    onError: (error: any) => handleApiError(error, 'Error al eliminar el perfil'),
  });

  const abrirNuevo = () => {
    setEditingProfile(null);
    setMarcados(new Set());
    form.resetFields();
    setIsModalVisible(true);
  };

  const abrir = (profile: Profile) => {
    setEditingProfile(profile);
    setMarcados(new Set(profile.permissions));
    form.setFieldsValue({
      display_name: profile.displayName,
      description: profile.description,
      location_scoped: profile.locationScoped,
      schedule_scoped: profile.scheduleScoped,
    });
    setIsModalVisible(true);
  };

  const alternar = (nombres: string[], marcar: boolean) => {
    setMarcados((actual) => {
      const siguiente = new Set(actual);
      nombres.forEach((nombre) => (marcar ? siguiente.add(nombre) : siguiente.delete(nombre)));
      return siguiente;
    });
  };

  const handleSave = (values: any) => {
    if (isSubmittingRef.current) {
      return;
    }
    isSubmittingRef.current = true;

    const data: ProfilePayload = {
      display_name: values.display_name.trim(),
      description: values.description?.trim() || null,
      location_scoped: !!values.location_scoped,
      schedule_scoped: !!values.schedule_scoped,
      permissions: Array.from(marcados),
    };
    const opciones = { onSettled: () => { isSubmittingRef.current = false; } };

    if (editingProfile) {
      updateMutation.mutate({ id: editingProfile.id, data }, opciones);
    } else {
      createMutation.mutate(data, opciones);
    }
  };

  const casilla = (permiso: PermissionItem | undefined, conEtiqueta = false) => {
    if (!permiso) {
      return <Text type="secondary">—</Text>;
    }
    const control = (
      <Checkbox
        checked={marcados.has(permiso.name)}
        disabled={soloLectura}
        onChange={(e) => alternar([permiso.name], e.target.checked)}
        data-permiso={permiso.name}
      >
        {conEtiqueta ? permiso.label : null}
      </Checkbox>
    );
    return conEtiqueta ? control : <Tooltip title={permiso.label}>{control}</Tooltip>;
  };

  const columnasPermisos: ColumnsType<FilaGrupo> = [
    {
      title: '',
      dataIndex: 'grupo',
      key: 'grupo',
      width: 230,
      render: (grupo: string) => <Text strong>{grupo}</Text>,
    },
    ...COLUMNAS.map(({ action, titulo }) => ({
      title: titulo,
      key: action,
      width: 80,
      align: 'center' as const,
      render: (_: unknown, fila: FilaGrupo) => casilla(fila.porAccion[action]),
    })),
    {
      title: 'Otras acciones',
      key: 'especiales',
      render: (_: unknown, fila: FilaGrupo) => (
        fila.especiales.length === 0
          ? <Text type="secondary">—</Text>
          : (
            <Space direction="vertical" size={2}>
              {fila.especiales.map((permiso) => <div key={permiso.name}>{casilla(permiso, true)}</div>)}
            </Space>
          )
      ),
    },
  ];

  const tarjetaModulo = (modulo: PermissionModule) => {
    const nombres = modulo.permissions.map((p) => p.name);
    const cuantos = nombres.filter((n) => marcados.has(n)).length;
    const filas = filasDe(modulo);
    const enMenu = modulo.menuPermission ? marcados.has(modulo.menuPermission) : true;
    // Tiene acciones marcadas en una sección que no ve en su menú. Es normal
    // (el Supervisor edita ubicaciones sin ver Datos Maestros) o un descuido:
    // se informa, no se impide.
    const accionesSinMenu = !enMenu && cuantos > 0;

    return (
      <Card
        key={modulo.key}
        size="small"
        style={{ marginBottom: 12 }}
        data-modulo={modulo.key}
        title={(
          <Space size="middle" wrap>
            <Text strong style={{ fontSize: 15 }}>{modulo.label}</Text>
            {modulo.menuPermission ? (
              <Checkbox
                checked={enMenu}
                disabled={soloLectura}
                onChange={(e) => alternar([modulo.menuPermission as string], e.target.checked)}
                data-permiso={modulo.menuPermission}
              >
                Aparece en el menú
              </Checkbox>
            ) : (
              <Tag>En el menú de todos los perfiles</Tag>
            )}
          </Space>
        )}
        extra={(
          <Space size="small">
            <Text type="secondary">{cuantos} de {nombres.length}</Text>
            {!soloLectura && (
              <>
                <Button type="link" size="small" onClick={() => alternar(nombres, true)}>Marcar todo</Button>
                <Button type="link" size="small" onClick={() => alternar(nombres, false)}>Quitar todo</Button>
              </>
            )}
          </Space>
        )}
      >
        {accionesSinMenu && (
          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 8 }}
            message={`${modulo.label} no aparece en su menú: lo marcado aquí solo le sirve desde otras pantallas.`}
          />
        )}
        {modulo.key === 'admin' && (marcados.has('manage_roles') || marcados.has('manage_users')) && !soloLectura && (
          <Alert
            type="warning"
            showIcon
            style={{ marginBottom: 8 }}
            message="Quien administra perfiles puede darse cualquier permiso, y quien administra usuarios puede cambiar el perfil de otros. Délos solo a personas de confianza."
          />
        )}
        {filas.length > 0 && (
          <Table<FilaGrupo>
            size="small"
            rowKey="grupo"
            columns={columnasPermisos}
            dataSource={filas}
            pagination={false}
            scroll={{ x: 'max-content' }}
          />
        )}
      </Card>
    );
  };

  const columns: ColumnsType<Profile> = [
    {
      title: 'Perfil',
      key: 'perfil',
      width: 270,
      render: (_, record) => (
        <div>
          <div style={{ fontWeight: 500, fontSize: 14 }}>
            {record.displayName}
            {record.hasFullAccess && <Tag color="red" style={{ marginLeft: 8 }}>Acceso total</Tag>}
          </div>
          {record.description && <div style={{ color: '#666', fontSize: 12 }}>{record.description}</div>}
        </div>
      ),
    },
    {
      title: 'Ve en el menú',
      key: 'menu',
      render: (_, record) => (
        record.hasFullAccess
          ? <Tag color="red">Todo el sistema</Tag>
          : record.menu.length === 0
            ? <Text type="secondary">Ninguna sección</Text>
            : <Space size={[4, 4]} wrap>{record.menu.map((seccion) => <Tag key={seccion}>{seccion}</Tag>)}</Space>
      ),
    },
    {
      title: 'Alcance',
      key: 'alcance',
      width: 210,
      render: (_, record) => (
        <Space size={[4, 4]} wrap>
          {record.locationScoped
            ? <Tag color="orange">Solo sus fincas</Tag>
            : <Tag color="green">Todas las ubicaciones</Tag>}
          {record.scheduleScoped && <Tag color="orange">Programaciones: solo sus fincas</Tag>}
        </Space>
      ),
    },
    {
      title: 'Permisos',
      key: 'permisos',
      align: 'center',
      width: 100,
      render: (_, record) => (record.hasFullAccess ? 'Todos' : `${record.permissions.length} de ${totalPermisos}`),
    },
    {
      title: 'Usuarios',
      dataIndex: 'usersCount',
      key: 'usersCount',
      align: 'center',
      width: 100,
      sorter: (a, b) => a.usersCount - b.usersCount,
    },
    {
      title: 'Acciones',
      key: 'actions',
      width: 200,
      render: (_, record) => {
        const motivo = !record.editable
          ? 'El perfil de acceso total no se puede eliminar'
          : record.usersCount > 0
            ? `Tiene ${record.usersCount} usuario(s). Asígneles otro perfil primero`
            : '';

        return (
          <Space size="middle">
            <Button
              type="link"
              icon={record.editable ? <EditOutlined /> : <EyeOutlined />}
              onClick={() => abrir(record)}
            >
              {record.editable ? 'Editar' : 'Ver'}
            </Button>
            {motivo ? (
              <Tooltip title={motivo}>
                <Button type="link" danger icon={<DeleteOutlined />} disabled>
                  Eliminar
                </Button>
              </Tooltip>
            ) : (
              <Popconfirm
                title="¿Está seguro de eliminar este perfil?"
                description="Esta acción no se puede deshacer"
                onConfirm={() => deleteMutation.mutate(record.id)}
                okText="Sí"
                cancelText="No"
              >
                <Button type="link" danger icon={<DeleteOutlined />}>
                  Eliminar
                </Button>
              </Popconfirm>
            )}
          </Space>
        );
      },
    },
  ];

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Perfiles y Permisos</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Defina qué ve en el menú y qué puede hacer cada perfil. El perfil se le asigna a cada persona en Usuarios.
          </p>
        </div>
        <Button type="primary" icon={<PlusOutlined />} onClick={abrirNuevo}>
          Nuevo Perfil
        </Button>
      </div>

      <Card>
        <Table
          columns={columns}
          dataSource={profiles}
          rowKey="id"
          loading={isLoading || deleteMutation.isPending}
          scroll={{ x: 1050 }}
          pagination={false}
        />
      </Card>

      <Modal
        title={(
          <Space>
            <SafetyOutlined />
            {editingProfile ? (soloLectura ? `Perfil ${editingProfile.displayName}` : 'Editar Perfil') : 'Nuevo Perfil'}
          </Space>
        )}
        open={isModalVisible}
        onCancel={cerrar}
        footer={null}
        width={1000}
        style={{ top: 24 }}
        destroyOnHidden
      >
        <Form form={form} layout="vertical" onFinish={handleSave} disabled={soloLectura}>
          {soloLectura && (
            <Alert
              type="info"
              showIcon
              style={{ marginBottom: 16 }}
              message="Este perfil tiene acceso total a todo el sistema y no se puede modificar: así nadie se queda sin poder entrar a administrar."
            />
          )}

          <Row gutter={16}>
            <Col xs={24} md={10}>
              <Form.Item
                name="display_name"
                label="Nombre del perfil"
                rules={[
                  { required: true, message: 'El nombre del perfil es requerido' },
                  { min: 3, message: 'El nombre debe tener al menos 3 caracteres' },
                  { max: 60, message: 'Máximo 60 caracteres' },
                ]}
              >
                <Input placeholder="Ej: Jefe de Bodega" />
              </Form.Item>
            </Col>
            <Col xs={24} md={14}>
              <Form.Item name="description" label="Descripción" rules={[{ max: 255, message: 'Máximo 255 caracteres' }]}>
                <Input placeholder="Para qué es este perfil" />
              </Form.Item>
            </Col>
          </Row>

          {!soloLectura && (
            <Card size="small" title="Alcance" style={{ marginBottom: 12 }}>
              <Form.Item name="location_scoped" valuePropName="checked" style={{ marginBottom: 4 }}>
                <Checkbox>
                  Solo ve las fincas a su cargo en inventario, salidas, recepciones y ajustes
                </Checkbox>
              </Form.Item>
              <Form.Item name="schedule_scoped" valuePropName="checked" style={{ marginBottom: 4 }}>
                <Checkbox>
                  Solo ve y programa tareas en las fincas a su cargo
                </Checkbox>
              </Form.Item>
              <Text type="secondary" style={{ fontSize: 12 }}>
                Las fincas a cargo de cada persona se definen en Datos Maestros → Ubicaciones (campo Responsable).
                Sin marcar, el perfil ve todas las ubicaciones.
              </Text>
            </Card>
          )}

          <div style={{ maxHeight: '52vh', overflowY: 'auto', paddingRight: 4 }}>
            {modulos.map(tarjetaModulo)}
          </div>

          <div style={{ textAlign: 'right', marginTop: 16 }}>
            <Space>
              <Text type="secondary">{marcados.size} de {totalPermisos} permisos marcados</Text>
              <Button onClick={cerrar} disabled={false}>
                {soloLectura ? 'Cerrar' : 'Cancelar'}
              </Button>
              {!soloLectura && (
                <Button
                  type="primary"
                  htmlType="submit"
                  loading={createMutation.isPending || updateMutation.isPending}
                  disabled={createMutation.isPending || updateMutation.isPending}
                >
                  {editingProfile ? 'Guardar' : 'Crear'}
                </Button>
              )}
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default Profiles;
