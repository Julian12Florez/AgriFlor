import type { ThemeConfig } from 'antd';

export const antdTheme: ThemeConfig = {
  token: {
    colorPrimary: '#4CAF50',
    colorSuccess: '#2E7D32',
    colorWarning: '#FFD54F',
    colorError: '#f5222d',
    colorBgLayout: '#F5F5F5',
    borderRadius: 12,
    fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, sans-serif',
    fontSize: 14,
    controlHeight: 40,
  },
  components: {
    Button: {
      borderRadius: 12,
      controlHeight: 40,
    },
    Card: {
      borderRadius: 16,
    },
    Table: {
      borderRadius: 12,
    },
    Layout: {
      siderBg: '#2E7D32',
      headerBg: '#4CAF50',
    },
  },
};