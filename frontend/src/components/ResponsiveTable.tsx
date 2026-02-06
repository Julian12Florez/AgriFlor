import React, { useState, useEffect } from 'react';
import { Table, Button } from 'antd';
import { DownOutlined, RightOutlined } from '@ant-design/icons';
import type { ColumnsType, TableProps } from 'antd/es/table';

interface ResponsiveTableProps<T> extends Omit<TableProps<T>, 'columns'> {
  mobileColumns: ColumnsType<T>;
  desktopColumns: ColumnsType<T>;
  expandedRowRender?: (record: T) => React.ReactNode;
  entityName?: string;
}

function ResponsiveTable<T extends Record<string, any>>({
  mobileColumns,
  desktopColumns,
  expandedRowRender,
  entityName = 'elementos',
  ...tableProps
}: ResponsiveTableProps<T>) {
  const [isMobile, setIsMobile] = useState(false);

  useEffect(() => {
    const checkScreenSize = () => {
      setIsMobile(window.innerWidth < 768);
    };

    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);
    return () => window.removeEventListener('resize', checkScreenSize);
  }, []);

  const columns = isMobile ? mobileColumns : desktopColumns;

  const expandableConfig = expandedRowRender ? {
    expandedRowRender,
    expandIcon: ({ expanded, onExpand, record }: any) =>
      expanded ? (
        <DownOutlined
          onClick={(e: any) => onExpand(record, e)}
          style={{ color: '#1890ff', cursor: 'pointer' }}
        />
      ) : (
        <RightOutlined
          onClick={(e: any) => onExpand(record, e)}
          style={{ color: '#1890ff', cursor: 'pointer' }}
        />
      ),
    rowExpandable: () => true,
  } : undefined;

  const paginationConfig = {
    showSizeChanger: true,
    showQuickJumper: !isMobile,
    showTotal: (total: number, range: [number, number]) =>
      `${range[0]}-${range[1]} de ${total} ${entityName}`,
    responsive: true,
    simple: isMobile,
    ...tableProps.pagination,
  };

  return (
    <Table
      {...tableProps}
      columns={columns}
      expandable={expandableConfig}
      scroll={{ x: 'max-content' }}
      pagination={paginationConfig}
    />
  );
}

export default ResponsiveTable;