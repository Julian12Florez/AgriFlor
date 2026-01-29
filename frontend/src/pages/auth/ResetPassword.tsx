import React, { useState } from 'react';
import { Form, Input, Button, Typography, message, Result } from 'antd';
import { LockOutlined, CheckCircleOutlined } from '@ant-design/icons';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { authApi } from '../../services/api';
import LandingSection from '../../components/auth/LandingSection';
import './Login.css';

const { Text } = Typography;

interface ResetFormValues {
  password: string;
  password_confirmation: string;
}

const containerVariants = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: { staggerChildren: 0.08, delayChildren: 0.2 },
  },
};

const itemVariants = {
  hidden: { opacity: 0, y: 16 },
  visible: { opacity: 1, y: 0, transition: { duration: 0.4, ease: 'easeOut' as const } },
};

const ResetPassword: React.FC = () => {
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [form] = Form.useForm();

  const token = searchParams.get('token');
  const email = searchParams.get('email');

  const handleReset = async (values: ResetFormValues) => {
    if (!token || !email) {
      message.error('Enlace de recuperacion invalido');
      return;
    }

    setLoading(true);
    try {
      const response = await authApi.resetPassword({
        token,
        email,
        password: values.password,
        password_confirmation: values.password_confirmation,
      });

      if (response.success) {
        setSuccess(true);
      } else {
        message.error(response.message || 'Error al restablecer la contrasena');
      }
    } catch (error: any) {
      message.error(error.message || 'Error al restablecer la contrasena');
    } finally {
      setLoading(false);
    }
  };

  if (!token || !email) {
    return (
      <div className="login-page">
        <div className="login-side">
          <div className="login-form-wrapper">
            <Result
              status="error"
              title="Enlace invalido"
              subTitle="El enlace de recuperacion es invalido o ha expirado. Solicite uno nuevo desde el login."
              extra={
                <Button type="primary" onClick={() => navigate('/login')} className="login-cta-btn">
                  Ir al Login
                </Button>
              }
            />
          </div>
        </div>
        <LandingSection />
      </div>
    );
  }

  if (success) {
    return (
      <div className="login-page">
        <div className="login-side">
          <motion.div
            className="login-form-wrapper"
            variants={containerVariants}
            initial="hidden"
            animate="visible"
          >
            <motion.div variants={itemVariants} style={{ textAlign: 'center' }}>
              <CheckCircleOutlined style={{ fontSize: 64, color: '#4CAF50', marginBottom: 24 }} />
              <h2 style={{ color: '#2C3E50', marginBottom: 8 }}>Contrasena restablecida</h2>
              <Text style={{ color: '#718096', display: 'block', marginBottom: 32 }}>
                Su contrasena ha sido actualizada exitosamente. Ya puede iniciar sesion con su nueva contrasena.
              </Text>
              <Button
                type="primary"
                size="large"
                block
                className="login-cta-btn"
                onClick={() => navigate('/login')}
              >
                Ir a Iniciar Sesion
              </Button>
            </motion.div>
          </motion.div>
        </div>
        <LandingSection />
      </div>
    );
  }

  return (
    <div className="login-page">
      <div className="login-side">
        <motion.div
          className="login-form-wrapper"
          variants={containerVariants}
          initial="hidden"
          animate="visible"
        >
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
            <h2 className="login-title">Nueva Contrasena</h2>
            <Text className="login-subtitle">Ingrese su nueva contrasena</Text>
          </motion.div>

          {/* Form */}
          <Form
            form={form}
            name="reset-password"
            className="login-form"
            onFinish={handleReset}
            layout="vertical"
            requiredMark={false}
          >
            <motion.div variants={itemVariants}>
              <Form.Item
                name="password"
                label="Nueva Contrasena"
                rules={[
                  { required: true, message: 'Por favor ingrese su nueva contrasena' },
                  { min: 8, message: 'Minimo 8 caracteres' },
                ]}
              >
                <Input.Password
                  prefix={<LockOutlined style={{ color: '#a0aec0' }} />}
                  placeholder="Minimo 8 caracteres"
                  size="large"
                  autoComplete="new-password"
                />
              </Form.Item>
            </motion.div>

            <motion.div variants={itemVariants}>
              <Form.Item
                name="password_confirmation"
                label="Confirmar Contrasena"
                dependencies={['password']}
                rules={[
                  { required: true, message: 'Por favor confirme su contrasena' },
                  ({ getFieldValue }) => ({
                    validator(_, value) {
                      if (!value || getFieldValue('password') === value) {
                        return Promise.resolve();
                      }
                      return Promise.reject(new Error('Las contrasenas no coinciden'));
                    },
                  }),
                ]}
              >
                <Input.Password
                  prefix={<LockOutlined style={{ color: '#a0aec0' }} />}
                  placeholder="Repita la contrasena"
                  size="large"
                  autoComplete="new-password"
                />
              </Form.Item>
            </motion.div>

            <motion.div variants={itemVariants}>
              <Form.Item style={{ marginBottom: '16px' }}>
                <Button
                  type="primary"
                  htmlType="submit"
                  size="large"
                  loading={loading}
                  block
                  className="login-cta-btn"
                >
                  Restablecer Contrasena
                </Button>
              </Form.Item>
            </motion.div>

            <motion.div variants={itemVariants} style={{ textAlign: 'center' }}>
              <a
                className="login-forgot-link"
                href="#"
                onClick={(e) => { e.preventDefault(); navigate('/login'); }}
              >
                Volver al inicio de sesion
              </a>
            </motion.div>
          </Form>

          {/* Footer */}
          <motion.div variants={itemVariants} className="login-footer">
            <Text className="login-footer-text">
              &copy; {new Date().getFullYear()} AgriFlor - Todos los derechos reservados
            </Text>
          </motion.div>
        </motion.div>
      </div>
      <LandingSection />
    </div>
  );
};

export default ResetPassword;
