import React, { useState } from 'react';
import { Form, Input, Button, Checkbox, Typography, message } from 'antd';
import { UserOutlined, LockOutlined, LoginOutlined, MailOutlined, ArrowLeftOutlined, CheckCircleOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import { authApi, setAuthToken, handleApiError } from '../../services/api';

const { Text } = Typography;

type ViewMode = 'login' | 'forgot' | 'confirmation';

interface LoginFormValues {
  email: string;
  password: string;
  remember?: boolean;
}

const staggerContainer = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: { staggerChildren: 0.08, delayChildren: 0.2 },
  },
  exit: { opacity: 0, x: -20, transition: { duration: 0.2, ease: 'easeIn' } },
};

const itemVariants = {
  hidden: { opacity: 0, y: 16 },
  visible: { opacity: 1, y: 0, transition: { duration: 0.4, ease: 'easeOut' as const } },
};

const LoginForm: React.FC = () => {
  const [loading, setLoading] = useState(false);
  const [forgotLoading, setForgotLoading] = useState(false);
  const [view, setView] = useState<ViewMode>('login');
  const [sentEmail, setSentEmail] = useState('');
  const navigate = useNavigate();
  const [form] = Form.useForm();
  const [forgotForm] = Form.useForm();
  const queryClient = useQueryClient();

  const handleLogin = async (values: LoginFormValues) => {
    setLoading(true);
    try {
      const response = await authApi.login(values.email, values.password);

      if (response.success && response.data) {
        setAuthToken(response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
        await queryClient.invalidateQueries({ queryKey: ['auth-me'] });
        message.success('¡Inicio de sesión exitoso!');
        navigate('/');
      } else {
        message.error(response.message || 'Error al iniciar sesión');
      }
    } catch (error: any) {
      handleApiError(error, 'Error de conexión con el servidor', form);
    } finally {
      setLoading(false);
    }
  };

  const handleForgotPassword = async (values: { email: string }) => {
    setForgotLoading(true);
    try {
      const response = await authApi.forgotPassword(values.email);
      if (response.success) {
        setSentEmail(values.email);
        setView('confirmation');
      }
    } catch (error: any) {
      // Even on error, show confirmation to not reveal if email exists
      setSentEmail(values.email);
      setView('confirmation');
    } finally {
      setForgotLoading(false);
    }
  };

  const goToForgot = (e: React.MouseEvent) => {
    e.preventDefault();
    forgotForm.resetFields();
    setView('forgot');
  };

  const goToLogin = (e: React.MouseEvent) => {
    e.preventDefault();
    setView('login');
  };

  const maskEmail = (email: string) => {
    const [user, domain] = email.split('@');
    if (!domain) return email;
    const masked = user.length > 2
      ? user.slice(0, 2) + '***'
      : user[0] + '***';
    return `${masked}@${domain}`;
  };

  // =====================
  // LOGIN VIEW
  // =====================
  const renderLogin = () => (
    <motion.div key="login" variants={staggerContainer} initial="hidden" animate="visible" exit="exit">
      {/* Logo */}
      <motion.div className="login-logo" variants={itemVariants}>
        <motion.div
          className="login-logo-icon"
          initial={{ scale: 0.5, rotate: -10 }}
          animate={{ scale: 1, rotate: 0 }}
          transition={{ type: 'spring', stiffness: 200, damping: 15, delay: 0.1 }}
        >
          <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <ellipse cx="32" cy="36" rx="18" ry="24" fill="#A8C97F" />
            <ellipse cx="32" cy="36" rx="12" ry="16" fill="#88A84E" />
            <ellipse cx="32" cy="38" rx="6" ry="8" fill="#6B8E23" />
            <path d="M32 12C32 12 28 4 32 2C36 4 32 12 32 12Z" fill="#4CAF50" />
            <path d="M32 12C32 12 38 6 40 8C38 12 32 12 32 12Z" fill="#2E7D32" opacity="0.8" />
          </svg>
        </motion.div>
        <h2 className="login-title">AgriFlor</h2>
        <Text className="login-subtitle">Sistema de Gestión Agrícola</Text>
      </motion.div>

      {/* Form */}
      <Form
        form={form}
        name="login"
        className="login-form"
        onFinish={handleLogin}
        layout="vertical"
        requiredMark={false}
        initialValues={{ email: '', password: '', remember: false }}
      >
        <motion.div variants={itemVariants}>
          <Form.Item
            name="email"
            label="Correo Electrónico"
            validateTrigger={['onChange', 'onBlur']}
            hasFeedback
            rules={[
              { required: true, message: 'Por favor ingrese su correo' },
              { type: 'email', message: 'Ingrese un correo válido' },
            ]}
          >
            <Input
              prefix={<UserOutlined style={{ color: '#a0aec0' }} />}
              placeholder="correo@ejemplo.com"
              size="large"
              autoComplete="email"
              aria-label="Correo electrónico"
            />
          </Form.Item>
        </motion.div>

        <motion.div variants={itemVariants}>
          <Form.Item
            name="password"
            label="Contraseña"
            validateTrigger={['onChange', 'onBlur']}
            hasFeedback
            rules={[
              { required: true, message: 'Por favor ingrese su contraseña' },
              { min: 6, message: 'Mínimo 6 caracteres' },
            ]}
          >
            <Input.Password
              prefix={<LockOutlined style={{ color: '#a0aec0' }} />}
              placeholder="Contraseña"
              size="large"
              autoComplete="current-password"
              aria-label="Contraseña"
            />
          </Form.Item>
        </motion.div>

        <motion.div variants={itemVariants}>
          <Form.Item style={{ marginBottom: '20px' }}>
            <div className="login-remember-row">
              <Form.Item name="remember" valuePropName="checked" noStyle>
                <Checkbox>Recordarme</Checkbox>
              </Form.Item>
              <a
                className="login-forgot-link"
                href="#"
                onClick={goToForgot}
              >
                ¿Olvidé mi contraseña?
              </a>
            </div>
          </Form.Item>
        </motion.div>

        <motion.div variants={itemVariants}>
          <Form.Item style={{ marginBottom: '16px' }}>
            <Button
              type="primary"
              htmlType="submit"
              icon={<LoginOutlined />}
              size="large"
              loading={loading}
              block
              className="login-cta-btn"
            >
              Iniciar Sesión
            </Button>
          </Form.Item>
        </motion.div>
      </Form>

      {/* Dev-only test credentials */}
      {import.meta.env.DEV && (
        <motion.div variants={itemVariants} className="login-test-credentials">
          <Text type="secondary" style={{ fontSize: '12px', display: 'block' }}>
            <strong>Demo:</strong> admin@agriflor.com / admin123
          </Text>
        </motion.div>
      )}

      <motion.div variants={itemVariants} className="login-footer">
        <Text className="login-footer-text">
          &copy; {new Date().getFullYear()} AgriFlor - Todos los derechos reservados
        </Text>
      </motion.div>
    </motion.div>
  );

  // =====================
  // FORGOT PASSWORD VIEW
  // =====================
  const renderForgot = () => (
    <motion.div key="forgot" variants={staggerContainer} initial="hidden" animate="visible" exit="exit">
      <motion.div className="login-logo" variants={itemVariants}>
        <motion.div
          className="login-logo-icon"
          initial={{ scale: 0.8 }}
          animate={{ scale: 1 }}
          transition={{ type: 'spring', stiffness: 200, damping: 15 }}
        >
          <MailOutlined style={{ fontSize: 32, color: 'white' }} />
        </motion.div>
        <h2 className="login-title">Recuperar Contraseña</h2>
        <Text className="login-subtitle">
          Ingrese su correo y le enviaremos instrucciones para restablecer su contraseña
        </Text>
      </motion.div>

      <Form
        form={forgotForm}
        name="forgot-password"
        className="login-form"
        onFinish={handleForgotPassword}
        layout="vertical"
        requiredMark={false}
      >
        <motion.div variants={itemVariants}>
          <Form.Item
            name="email"
            label="Correo Electrónico"
            rules={[
              { required: true, message: 'Por favor ingrese su correo' },
              { type: 'email', message: 'Ingrese un correo válido' },
            ]}
          >
            <Input
              prefix={<MailOutlined style={{ color: '#a0aec0' }} />}
              placeholder="correo@ejemplo.com"
              size="large"
              autoComplete="email"
              aria-label="Correo electrónico"
            />
          </Form.Item>
        </motion.div>

        <motion.div variants={itemVariants}>
          <Form.Item style={{ marginBottom: '16px' }}>
            <Button
              type="primary"
              htmlType="submit"
              size="large"
              loading={forgotLoading}
              block
              className="login-cta-btn"
            >
              Enviar Instrucciones
            </Button>
          </Form.Item>
        </motion.div>

        <motion.div variants={itemVariants} style={{ textAlign: 'center' }}>
          <a
            className="login-forgot-link"
            href="#"
            onClick={goToLogin}
            style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}
          >
            <ArrowLeftOutlined /> Volver al inicio de sesión
          </a>
        </motion.div>
      </Form>

      <motion.div variants={itemVariants} className="login-footer">
        <Text className="login-footer-text">
          &copy; {new Date().getFullYear()} AgriFlor - Todos los derechos reservados
        </Text>
      </motion.div>
    </motion.div>
  );

  // =====================
  // CONFIRMATION VIEW
  // =====================
  const renderConfirmation = () => (
    <motion.div key="confirmation" variants={staggerContainer} initial="hidden" animate="visible" exit="exit">
      <motion.div variants={itemVariants} style={{ textAlign: 'center', paddingTop: 20 }}>
        <motion.div
          initial={{ scale: 0 }}
          animate={{ scale: 1 }}
          transition={{ type: 'spring', stiffness: 200, damping: 15, delay: 0.3 }}
        >
          <CheckCircleOutlined style={{ fontSize: 64, color: '#4CAF50', marginBottom: 24 }} />
        </motion.div>
      </motion.div>

      <motion.div variants={itemVariants} style={{ textAlign: 'center' }}>
        <h2 style={{ color: '#2C3E50', fontSize: 22, marginBottom: 12 }}>
          Revise su correo electrónico
        </h2>
        <Text style={{ color: '#718096', fontSize: 14, display: 'block', marginBottom: 8, lineHeight: 1.6 }}>
          Si el correo está registrado, hemos enviado instrucciones para restablecer su contraseña a:
        </Text>
        <Text strong style={{ color: '#2C3E50', fontSize: 15, display: 'block', marginBottom: 32 }}>
          {maskEmail(sentEmail)}
        </Text>

        <Text style={{ color: '#a0aec0', fontSize: 12, display: 'block', marginBottom: 24 }}>
          El enlace expirará en 60 minutos.
          Revise también su bandeja de spam.
        </Text>
      </motion.div>

      <motion.div variants={itemVariants} style={{ textAlign: 'center' }}>
        <a
          className="login-forgot-link"
          href="#"
          onClick={goToLogin}
          style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}
        >
          <ArrowLeftOutlined /> Volver al inicio de sesión
        </a>
      </motion.div>

      <motion.div variants={itemVariants} className="login-footer">
        <Text className="login-footer-text">
          &copy; {new Date().getFullYear()} AgriFlor - Todos los derechos reservados
        </Text>
      </motion.div>
    </motion.div>
  );

  return (
    <div className="login-side">
      <div className="login-form-wrapper">
        <AnimatePresence mode="wait">
          {view === 'login' && renderLogin()}
          {view === 'forgot' && renderForgot()}
          {view === 'confirmation' && renderConfirmation()}
        </AnimatePresence>
      </div>
    </div>
  );
};

export default LoginForm;
