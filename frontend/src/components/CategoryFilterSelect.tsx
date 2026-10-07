import React from 'react';
import { Select } from 'antd';
import { useQuery } from '@tanstack/react-query';
import { categoriesApi } from '../services/api';

interface Props {
  value?: string;
  onChange: (categoryId: string | undefined) => void;
  style?: React.CSSProperties;
}

/**
 * Filtro "Categoría" de los informes de inventario (Fertilizante, Insecticida,
 * Fungicida...). Devuelve el ID de la categoría: cada informe lo manda al
 * backend como `category_id`, que es quien filtra — así la pantalla, los totales
 * y las descargas a Excel/PDF dicen lo mismo.
 *
 * Trae TODAS las categorías, también las inactivas: un producto viejo puede
 * seguir en una y el usuario necesita poder filtrarla.
 */
const CategoryFilterSelect: React.FC<Props> = ({ value, onChange, style }) => {
  const { data, isLoading } = useQuery({
    queryKey: ['categories', 'filtro-informes'],
    queryFn: () => categoriesApi.list({ per_page: 9999 }),
    staleTime: 5 * 60 * 1000,
  });

  const opciones = ((data?.data as any[]) || [])
    .map((c) => ({ value: c.id as string, label: c.name as string }))
    .sort((a, b) => a.label.localeCompare(b.label, 'es'));

  return (
    <Select
      allowClear
      showSearch
      optionFilterProp="label"
      placeholder="Todas las categorías"
      loading={isLoading}
      value={value}
      onChange={(v) => onChange(v || undefined)}
      options={opciones}
      style={{ width: '100%', ...style }}
    />
  );
};

export default CategoryFilterSelect;
