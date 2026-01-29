<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #2E7D32;
            padding-bottom: 10px;
        }
        .header h1 {
            color: #2E7D32;
            font-size: 18px;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 10px;
            color: #666;
        }
        .info-section {
            margin-bottom: 15px;
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
        }
        .info-section p {
            margin-bottom: 3px;
            font-size: 9px;
        }
        .info-section strong {
            color: #2E7D32;
        }
        .stats {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .stat-item {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 10px;
            background-color: #e8f5e9;
            border-right: 2px solid #fff;
        }
        .stat-item:last-child {
            border-right: none;
        }
        .stat-label {
            font-size: 8px;
            color: #666;
            margin-bottom: 3px;
        }
        .stat-value {
            font-size: 14px;
            font-weight: bold;
            color: #2E7D32;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table thead {
            background-color: #2E7D32;
            color: #fff;
        }
        table thead th {
            padding: 8px 5px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
        }
        table tbody td {
            padding: 6px 5px;
            border-bottom: 1px solid #ddd;
            font-size: 9px;
        }
        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8px;
            color: #999;
            padding: 10px 0;
            border-top: 1px solid #ddd;
        }
        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
        }
        .status-good { background-color: #4caf50; color: white; }
        .status-low { background-color: #ff9800; color: white; }
        .status-out { background-color: #f44336; color: white; }
        .status-near-expiry { background-color: #ff5722; color: white; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Generado el {{ $date }} por {{ $user }}</p>
    </div>

    <!-- Info Section -->
    <div class="info-section">
        <p><strong>Filtros aplicados:</strong></p>
        @if($filters['location_id'] ?? null)
            <p>&bull; Ubicación filtrada</p>
        @endif
        @if($filters['status'] ?? null)
            <p>&bull; Estado: {{ $filters['status'] }}</p>
        @endif
        @if($filters['search'] ?? null)
            <p>&bull; Búsqueda: {{ $filters['search'] }}</p>
        @endif
    </div>

    <!-- Statistics -->
    <div class="stats">
        <div class="stat-item">
            <div class="stat-label">Total Productos</div>
            <div class="stat-value">{{ $stats['total_products'] }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Valor Total</div>
            <div class="stat-value">${{ number_format($stats['total_value'], 0, ',', '.') }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Stock Bajo</div>
            <div class="stat-value" style="color: {{ $stats['low_stock'] > 0 ? '#ff9800' : '#2E7D32' }};">{{ $stats['low_stock'] }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Agotados</div>
            <div class="stat-value" style="color: {{ $stats['out_of_stock'] > 0 ? '#f44336' : '#2E7D32' }};">{{ $stats['out_of_stock'] }}</div>
        </div>
    </div>

    <!-- Table -->
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Código</th>
                <th>Categoría</th>
                <th style="text-align: right;">Stock (Base)</th>
                <th style="text-align: right;">Valor Total</th>
                <th style="text-align: center;">Ubic.</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            @php
                $statusClass = match($item['status'] ?? 'good') {
                    'good' => 'status-good',
                    'low' => 'status-low',
                    'out_of_stock' => 'status-out',
                    'near_expiry' => 'status-near-expiry',
                    'expired' => 'status-out',
                    default => 'status-good'
                };
                $statusLabel = match($item['status'] ?? 'good') {
                    'good' => 'OK',
                    'low' => 'Bajo',
                    'out_of_stock' => 'Agotado',
                    'near_expiry' => 'Vencer',
                    'expired' => 'Vencido',
                    default => 'OK'
                };
            @endphp
            <tr>
                <td>{{ $item['product_name'] }}</td>
                <td>{{ $item['product_code'] ?? 'N/A' }}</td>
                <td>{{ ucfirst($item['category'] ?? 'N/A') }}</td>
                <td style="text-align: right; font-weight: bold;">{{ number_format($item['total_quantity_base'], 0, ',', '.') }} {{ $item['base_unit'] }}</td>
                <td style="text-align: right;">${{ number_format($item['total_value'], 0, ',', '.') }}</td>
                <td style="text-align: center;">{{ $item['locations_count'] }}</td>
                <td>
                    <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>AgriFlor - Sistema de Gestión de Inventarios | Página {PAGENO} de {nbpg}</p>
    </div>
</body>
</html>
