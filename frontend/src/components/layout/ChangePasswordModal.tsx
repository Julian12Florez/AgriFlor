import React, { useState } from 'react';
import { Modal, Form, Input, message } from 'antd';
import { LockOutlined } from '@ant-design/icons';
import { authApi, handleApiError } from '../../services/api';

interface ChangePasswordModalProps {
  open: boolean;
  onClose: () => void;
}

const ChangePasswordModal: React.FC<ChangePasswordModalProps> = ({ open, onClose }) => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();
      setLoading(true);

      const response = await authApi.changePassword({
        current_password: values.current_password,
        password: values.password,
        password_confirmation: values.password_confirmation,
      });

      if (response.success) {
        message.success('Contraseña actualizada exitosamente');
        form.resetFields();
        onClose();
      } else {
        message.error(response.message || 'Error al cambiar la contraseña');
      }
    } catch (error: any) {
      handleApiError(error, 'Error al cambiar la contraseña', form);
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = () => {
    form.resetFields();
    onClose();
  };

  return (
    <Modal
      title="Cambiar Contraseña"
      open={open}
      onOk={handleSubmit}
      onCancel={handleCancel}
      okText="Cambiar Contraseña"
      cancelText="Cancelar"
      confirmLoading={loading}
      destroyOnClose
    >
      <Form
        form={form}
        layout="vertical"
        style={{ marginTop: 16 }}
      >
        <Form.Item
          name="current_password"
          label="Contraseña Actual"
          rules={[{ required: true, message: 'Ingrese su contraseña actual' }]}
        >
          <Input.Password
            prefix={<LockOutlined />}
            placeholder="Contraseña actual"
          />
        </Form.Item>

        <Form.Item
          name="password"
          label="Nueva Contraseña"
          rules={[
            { required: true, message: 'Ingrese la nueva contraseña' },
            { min: 8, message: 'Mínimo 8 caracteres' },
          ]}
        >
          <Input.Password
            prefix={<LockOutlined />}
            placeholder="Mínimo 8 caracteres"
          />
        </Form.Item>

        <Form.Item
          name="password_confirmation"
          label="Confirmar Nueva Contraseña"
          dependencies={['password']}
          rules={[
            { required: true, message: 'Confirme la nueva contraseña' },
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
            placeholder="Repita la nueva contraseña"
          />
        </Form.Item>
      </Form>
    </Modal>
  );
};

export default ChangePasswordModal;
