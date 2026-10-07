import React, { useMemo, useState, useRef } from 'react';
import { Table, Button, Input, Space, Card, Tag, Popconfirm, message, Modal, Form, Row, Col, Select, Avatar, Checkbox } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, UserOutlined, MailOutlined, PhoneOutlined, LockOutlined } from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { usersApi, rolesApi, handleApiError } from '../../services/api';

interface User {
  id: string;
  email: string;
  name: string;
  /** Nombre técnico del perfil (los perfiles se administran en Administración → Perfiles). */
  role: string;
  status: 'active' | 'inactive';
  createdAt: string;
  updatedAt: string;
}

/** Color de la etiqueta de los perfiles de fábrica; uno nuevo usa el color por defecto. */
const ROLE_COLORS: Record<string, string> = {
  admin: 'red',
  agronomist: 'green',
  warehouse: 'blue',
  supervisor: 'orange',
  farm: 'purple',
  purchasing: 'cyan',
  financiero: 'gold',
};

const { Search } = Input;
const { Option } = Select;

const Users: React.FC = () => {
  const queryClient = useQueryClient();
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [searchText, setSearchText] = useState('');
  const [roleFilter, setRoleFilter] = useState<string | undefined>();
  const [statusFilter, setStatusFilter] = useState<string | undefined>();
  const [showPasswordFields, setShowPasswordFields] = useState(false);
  const [form] = Form.useForm();

  // Ref to prevent double submissions (synchronous check)
  const isSubmittingRef = useRef(false);

  // Fetch users from API
  const { data: usersData, isLoading: usersLoading } = useQuery({
    queryKey: ['users', searchText, roleFilter, statusFilter],
    queryFn: () => usersApi.list({
      search: searchText || undefined,
      role: roleFilter || undefined,
      status: statusFilter || undefined
    }),
  });

  const users = usersData?.data || [];

  // Perfiles disponibles: salen de Administración → Perfiles, no de una lista fija.
  const { data: rolesData, isLoading: rolesLoading } = useQuery({
    queryKey: ['roles-options'],
    queryFn: () => rolesApi.options(),
  });
  const roles = useMemo(() => rolesData?.data || [], [rolesData]);

  // Perfil elegido en el formulario, para mostrar qué menú le da al usuario.
  const selectedRoleName = Form.useWatch('role', form);
  const selectedRole = roles.find((r) => r.name === selectedRoleName);

  // Create user mutation
  const createUserMutation = useMutation({
    mutationFn: (data: any) => usersApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      // La pantalla de Perfiles muestra cuántos usuarios tiene cada perfil.
      queryClient.invalidateQueries({ queryKey: ['roles'] });
      setIsModalVisible(false);
      form.resetFields();
      message.success('Usuario creado exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al crear usuario', form);
    },
  });

  // Update user mutation
  const updateUserMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => usersApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      // La pantalla de Perfiles muestra cuántos usuarios tiene cada perfil.
      queryClient.invalidateQueries({ queryKey: ['roles'] });
      setIsModalVisible(false);
      form.resetFields();
      setEditingUser(null);
      message.success('Usuario actualizado exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al actualizar usuario', form);
    },
  });

  // Delete user mutation
  const deleteUserMutation = useMutation({
    mutationFn: (id: string) => usersApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      // La pantalla de Perfiles muestra cuántos usuarios tiene cada perfil.
      queryClient.invalidateQueries({ queryKey: ['roles'] });
      message.success('Usuario eliminado exitosamente');
    },
    onError: (error: any) => {
      handleApiError(error, 'Error al eliminar usuario');
    },
  });

  const getRoleColor = (role: string) => ROLE_COLORS[role] || 'geekblue';

  const getRoleText = (role: string) => roles.find((r) => r.name === role)?.displayName || role;

  const handleEdit = (record: User) => {
    setEditingUser(record);
    setShowPasswordFields(false);
    form.setFieldsValue({
      name: record.name,
      email: record.email,
      role: record.role,
      status: record.status
    });
    setIsModalVisible(true);
  };

  const handleDelete = (id: string) => {
    deleteUserMutation.mutate(id);
  };

  const handleSave = (values: any) => {
    // Prevent multiple submissions using ref (synchronous check)
    if (isSubmittingRef.current) {
      return;
    }
    isSubmittingRef.current = true;

    // Format data for backend API (snake_case)
    const userData: Record<string, any> = {
      email: values.email,
      name: values.name,
      role: values.role,
      status: values.status || 'active',
    };

    // Include password fields when creating or when editing with password change
    if (!editingUser || (editingUser && showPasswordFields && values.password)) {
      userData.password = values.password;
      userData.password_confirmation = values.password_confirmation;
    }

    const mutationOptions = {
      onSettled: () => {
        isSubmittingRef.current = false;
      }
    };

    if (editingUser) {
      updateUserMutation.mutate({ id: editingUser.id, data: userData }, mutationOptions);
    } else {
      createUserMutation.mutate(userData, mutationOptions);
    }
  };

  // Backend handles filtering, so no need to filter locally
  const filteredUsers = users;

  const columns: ColumnsType<User> = [
    {
      title: 'Usuario',
      key: 'user',
      render: (_, record) => (
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <Avatar
            size={40}
            icon={<UserOutlined />}
            style={{ backgroundColor: getRoleColor(record.role) }}
          />
          <div>
            <div style={{ fontWeight: 500, fontSize: 14 }}>{record.name}</div>
            <div style={{ color: '#666', fontSize: 12 }}>
              <MailOutlined style={{ marginRight: 4 }} />
              {record.email}
            </div>
          </div>
        </div>
      ),
    },
    {
      title: 'Rol',
      dataIndex: 'role',
      key: 'role',
      render: (role: string) => (
        <Tag color={getRoleColor(role)}>
          {getRoleText(role)}
        </Tag>
      ),
      filters: roles.map((r) => ({ text: r.displayName, value: r.name })),
      onFilter: (value, record) => record.role === value,
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
      filters: [
        { text: 'Activo', value: 'active' },
        { text: 'Inactivo', value: 'inactive' },
      ],
      onFilter: (value, record) => record.status === value,
    },
    {
      title: 'Fecha de Creación',
      dataIndex: 'createdAt',
      key: 'createdAt',
      render: (date: Date) => new Date(date).toLocaleDateString('es-CO'),
      sorter: (a, b) => new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime(),
    },
    {
      title: 'Última Actualización',
      dataIndex: 'updatedAt',
      key: 'updatedAt',
      render: (date: Date) => new Date(date).toLocaleDateString('es-CO'),
      sorter: (a, b) => new Date(a.updatedAt).getTime() - new Date(b.updatedAt).getTime(),
    },
    {
      title: 'Acciones',
      key: 'actions',
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
            title="¿Está seguro de eliminar este usuario?"
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

  return (
    <div>
      <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h1 style={{ margin: 0, color: '#2E7D32' }}>Gestión de Usuarios</h1>
          <p style={{ color: '#666', margin: 0 }}>
            Administra los usuarios del sistema AgriFlor
          </p>
        </div>
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => {
            setEditingUser(null);
            setShowPasswordFields(false);
            form.resetFields();
            setIsModalVisible(true);
          }}
        >
          Nuevo Usuario
        </Button>
      </div>

      <Card>
        <div style={{ marginBottom: 16 }}>
          <Space>
            <Search
              placeholder="Buscar usuarios..."
              allowClear
              style={{ width: 300 }}
              value={searchText}
              onChange={(e) => setSearchText(e.target.value)}
            />
            <Select
              placeholder="Filtrar por rol"
              allowClear
              style={{ width: 200 }}
              value={roleFilter}
              onChange={setRoleFilter}
              loading={rolesLoading}
            >
              {roles.map((r) => (
                <Option key={r.name} value={r.name}>{r.displayName}</Option>
              ))}
            </Select>
            <Select
              placeholder="Filtrar por estado"
              allowClear
              style={{ width: 150 }}
              value={statusFilter}
              onChange={setStatusFilter}
            >
              <Option value="active">Activo</Option>
              <Option value="inactive">Inactivo</Option>
            </Select>
          </Space>
        </div>

        <Table
          columns={columns}
          dataSource={filteredUsers}
          rowKey="id"
          loading={usersLoading || createUserMutation.isPending || updateUserMutation.isPending || deleteUserMutation.isPending}
          scroll={{ x: 'max-content' }}
          pagination={{
            total: filteredUsers.length,
            pageSize: 10,
            showSizeChanger: true,
            showQuickJumper: true,
            showTotal: (total, range) =>
              `${range[0]}-${range[1]} de ${total} usuarios`,
          }}
        />
      </Card>

      <Modal
        title={editingUser ? 'Editar Usuario' : 'Nuevo Usuario'}
        open={isModalVisible}
        onCancel={() => {
          setIsModalVisible(false);
          form.resetFields();
          setEditingUser(null);
          setShowPasswordFields(false);
        }}
        footer={null}
        width={600}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSave}
        >
          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                name="name"
                label="Nombre Completo"
                rules={[
                  { required: true, message: 'El nombre es requerido' },
                  { min: 3, message: 'El nombre debe tener al menos 3 caracteres' }
                ]}
              >
                <Input placeholder="Ingrese el nombre completo" />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={24}>
              <Form.Item
                name="email"
                label="Correo Electrónico"
                rules={[
                  { required: true, message: 'El email es requerido' },
                  { type: 'email', message: 'Ingrese un email válido' }
                ]}
              >
                <Input
                  prefix={<MailOutlined />}
                  placeholder="usuario@agriflor.com"
                />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="role"
                label="Rol"
                rules={[{ required: true, message: 'El rol es requerido' }]}
              >
                <Select placeholder="Seleccione el rol" loading={rolesLoading}>
                  {roles.map((r) => (
                    <Option key={r.name} value={r.name}>
                      <Tag color={getRoleColor(r.name)} style={{ margin: 0 }}>{r.displayName}</Tag>
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="status"
                label="Estado"
                rules={[{ required: true, message: 'El estado es requerido' }]}
              >
                <Select placeholder="Seleccione el estado">
                  <Option value="active">Activo</Option>
                  <Option value="inactive">Inactivo</Option>
                </Select>
              </Form.Item>
            </Col>
          </Row>

          {/* Qué menú le da ese perfil a la persona (se configura en Administración → Perfiles) */}
          {selectedRole && (
            <div style={{ marginTop: -8, marginBottom: 16, fontSize: 12, color: '#666' }} data-testid="menu-del-perfil">
              <span style={{ marginRight: 6 }}>Con este perfil verá en el menú:</span>
              {selectedRole.hasFullAccess ? (
                <Tag color="red">Todo el sistema</Tag>
              ) : selectedRole.menu.length === 0 ? (
                <span>ninguna sección configurable</span>
              ) : (
                selectedRole.menu.map((seccion) => <Tag key={seccion}>{seccion}</Tag>)
              )}
            </div>
          )}

          {/* Password fields - required on create, optional on edit */}
          {editingUser && (
            <Row gutter={16}>
              <Col span={24}>
                <Checkbox
                  checked={showPasswordFields}
                  onChange={(e) => {
                    setShowPasswordFields(e.target.checked);
                    if (!e.target.checked) {
                      form.setFieldsValue({ password: undefined, password_confirmation: undefined });
                    }
                  }}
                  style={{ marginBottom: 16 }}
                >
                  <LockOutlined style={{ marginRight: 4 }} />
                  Cambiar contraseña
                </Checkbox>
              </Col>
            </Row>
          )}

          {(!editingUser || showPasswordFields) && (
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  name="password"
                  label="Contraseña"
                  rules={[
                    { required: !editingUser, message: 'La contraseña es requerida' },
                    { min: 8, message: 'Mínimo 8 caracteres' }
                  ]}
                >
                  <Input.Password
                    prefix={<LockOutlined />}
                    placeholder="Mínimo 8 caracteres"
                  />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item
                  name="password_confirmation"
                  label="Confirmar Contraseña"
                  dependencies={['password']}
                  rules={[
                    { required: !editingUser, message: 'Confirme la contraseña' },
                    ({ getFieldValue }) => ({
                      validator(_, value) {
                        if (!value || getFieldValue('password') === value) {
                          return Promise.resolve();
                        }
                        return Promise.reject(new Error('Las contraseñas no coinciden'));
                      },
                    }),
                  ]}
                >
                  <Input.Password
                    prefix={<LockOutlined />}
                    placeholder="Repita la contraseña"
                  />
                </Form.Item>
              </Col>
            </Row>
          )}

          <div style={{ textAlign: 'right', marginTop: 24 }}>
            <Space>
              <Button onClick={() => {
                setIsModalVisible(false);
                form.resetFields();
                setEditingUser(null);
              }}>
                Cancelar
              </Button>
              <Button
                type="primary"
                htmlType="submit"
                loading={createUserMutation.isPending || updateUserMutation.isPending}
                disabled={createUserMutation.isPending || updateUserMutation.isPending}
              >
                {editingUser ? 'Actualizar' : 'Crear'}
              </Button>
            </Space>
          </div>
        </Form>
      </Modal>
    </div>
  );
};

export default Users;