/**
 * Utility functions for formatting numbers, currencies, and quantities
 * These functions are purely visual and DO NOT affect backend data
 */

/**
 * Format a number with thousands separators
 * @param value - The number to format
 * @param decimals - Number of decimal places (default: 2)
 * @returns Formatted string (e.g., 1,234.56)
 */
export const formatNumber = (value: number | string | null | undefined, decimals: number = 2): string => {
  if (value === null || value === undefined || value === '') {
    return '0.00';
  }

  const numValue = typeof value === 'string' ? parseFloat(value) : value;

  if (isNaN(numValue)) {
    return '0.00';
  }

  return new Intl.NumberFormat('es-CO', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(numValue);
};

/**
 * Format a number as currency (Colombian Peso)
 * @param value - The number to format
 * @returns Formatted currency string (e.g., $1,234.56)
 */
export const formatCurrency = (value: number | string | null | undefined): string => {
  if (value === null || value === undefined || value === '') {
    return '$0.00';
  }

  const numValue = typeof value === 'string' ? parseFloat(value) : value;

  if (isNaN(numValue)) {
    return '$0.00';
  }

  return new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numValue);
};

/**
 * Format a quantity (no currency symbol)
 * @param value - The number to format
 * @param decimals - Number of decimal places (default: 2)
 * @returns Formatted quantity string (e.g., 1,234.56)
 */
export const formatQuantity = (value: number | string | null | undefined, decimals: number = 2): string => {
  return formatNumber(value, decimals);
};

/**
 * Format a percentage
 * @param value - The number to format (e.g., 85.5 for 85.5%)
 * @param decimals - Number of decimal places (default: 2)
 * @returns Formatted percentage string (e.g., 85.50%)
 */
export const formatPercentage = (value: number | string | null | undefined, decimals: number = 2): string => {
  if (value === null || value === undefined || value === '') {
    return '0.00%';
  }

  const numValue = typeof value === 'string' ? parseFloat(value) : value;

  if (isNaN(numValue)) {
    return '0.00%';
  }

  return `${formatNumber(numValue, decimals)}%`;
};

/**
 * Parse a formatted number string back to a number (for form inputs)
 * @param value - The formatted string (e.g., "1,234.56")
 * @returns Parsed number
 */
export const parseFormattedNumber = (value: string): number => {
  if (!value) return 0;

  // Remove thousands separators and parse
  const cleanValue = value.replace(/,/g, '');
  const parsed = parseFloat(cleanValue);

  return isNaN(parsed) ? 0 : parsed;
};
