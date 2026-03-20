import { useEffect } from 'react';
import { App, message as staticMessage } from 'antd';
import { setGlobalMessage } from '../services/api';

const AntdAppProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { message } = App.useApp();

  useEffect(() => {
    setGlobalMessage(message);

    // Override static message methods to use the context-aware instance.
    // This ensures all existing code using `message.success(...)` from antd
    // works correctly without needing to refactor every component.
    staticMessage.success = message.success;
    staticMessage.error = message.error;
    staticMessage.warning = message.warning;
    staticMessage.info = message.info;
    staticMessage.loading = message.loading;
  }, [message]);

  return <>{children}</>;
};

export default AntdAppProvider;
