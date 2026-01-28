import React, { useState } from 'react';
import { Form, Input, Button, Card, message, Typography, Divider } from 'antd';
import { UserOutlined, LockOutlined, LoginOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { authApi, setAuthToken } from '../../services/api';

const { Title, Text } = Typography;

interface LoginForm {
  email: string;
  password: string;
}

const Login: React.FC = () => {
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();
  const [form] = Form.useForm();
  const queryClient = useQueryClient();

  const handleLogin = async (values: LoginForm) => {
    setLoading(true);
    try {
      const response = await authApi.login(values.email, values.password);

      if (response.success && response.data) {
        // Store token
        setAuthToken(response.data.token);

        // Store user info
        localStorage.setItem('user', JSON.stringify(response.data.user));

        // Invalidate auth-me query to force refetch with new token
        // This ensures fresh permission data is loaded
        await queryClient.invalidateQueries({ queryKey: ['auth-me'] });

        message.success('¡Inicio de sesión exitoso!');

        // Redirect to dashboard
        navigate('/');
      } else {
        message.error(response.message || 'Error al iniciar sesión');
      }
    } catch (error: any) {
      message.error(error.message || 'Error de conexión con el servidor');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        padding: '20px',
      }}
    >
      <Card
        style={{
          width: '100%',
          maxWidth: '450px',
          borderRadius: '16px',
          boxShadow: '0 20px 60px rgba(0,0,0,0.3)',
        }}
        bodyStyle={{ padding: '40px' }}
      >
        <div style={{ textAlign: 'center', marginBottom: '32px' }}>
          <div
            style={{
              width: '80px',
              height: '80px',
              margin: '0 auto 20px',
              background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
              borderRadius: '50%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: '40px',
              color: 'white',
            }}
          >
            🌾
          </div>
          <Title level={2} style={{ margin: 0, color: '#2E7D32' }}>
            AgriFlor
          </Title>
          <Text type="secondary">Sistema de Gestión Agrícola</Text>
        </div>

        <Form
          form={form}
          name="login"
          onFinish={handleLogin}
          layout="vertical"
          requiredMark={false}
          initialValues={{
            email: '',
            password: '',
          }}
        >
          <Form.Item
            name="email"
            label="Correo Electrónico"
            rules={[
              { required: true, message: 'Por favor ingrese su correo' },
              { type: 'email', message: 'Ingrese un correo válido' },
            ]}
          >
            <Input
              prefix={<UserOutlined style={{ color: '#999' }} />}
              placeholder="correo@ejemplo.com"
              size="large"
              autoComplete="email"
            />
          </Form.Item>

          <Form.Item
            name="password"
            label="Contraseña"
            rules={[{ required: true, message: 'Por favor ingrese su contraseña' }]}
          >
            <Input.Password
              prefix={<LockOutlined style={{ color: '#999' }} />}
              placeholder="Contraseña"
              size="large"
              autoComplete="current-password"
            />
          </Form.Item>

          <Form.Item style={{ marginBottom: '12px' }}>
            <Button
              type="primary"
              htmlType="submit"
              icon={<LoginOutlined />}
              size="large"
              loading={loading}
              block
              style={{
                height: '48px',
                fontSize: '16px',
                fontWeight: 500,
                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                border: 'none',
              }}
            >
              Iniciar Sesión
            </Button>
          </Form.Item>

          <Divider style={{ margin: '24px 0' }}>o</Divider>

          <div style={{ textAlign: 'center' }}>
            <Text type="secondary" style={{ fontSize: '13px' }}>
              ¿Olvidaste tu contraseña?{' '}
              <a
                href="#"
                onClick={(e) => {
                  e.preventDefault();
                  message.info('Funcionalidad de recuperación de contraseña próximamente');
                }}
                style={{ color: '#667eea', fontWeight: 500 }}
              >
                Recuperar
              </a>
            </Text>
          </div>
        </Form>

        <div
          style={{
            marginTop: '32px',
            padding: '16px',
            background: '#f0f5ff',
            borderRadius: '8px',
            textAlign: 'center',
          }}
        >
          <Text type="secondary" style={{ fontSize: '12px', display: 'block' }}>
            <strong>Usuario de prueba:</strong>
          </Text>
          <Text code style={{ fontSize: '12px' }}>
            admin@agriflor.com
          </Text>
          <Text type="secondary" style={{ fontSize: '12px', display: 'block', marginTop: '4px' }}>
            <strong>Contraseña:</strong>
          </Text>
          <Text code style={{ fontSize: '12px' }}>
            admin123
          </Text>
        </div>
      </Card>

      <div
        style={{
          position: 'fixed',
          bottom: '20px',
          width: '100%',
          textAlign: 'center',
        }}
      >
        <Text style={{ color: 'white', fontSize: '12px' }}>
          © 2025 AgriFlor - Todos los derechos reservados
        </Text>
      </div>
    </div>
  );
};

export default Login;
