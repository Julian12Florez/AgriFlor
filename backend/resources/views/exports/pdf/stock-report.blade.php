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
        .page-break {
            page-break-after: always;
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
        @if($filters['product_id'])
            <p>• Producto filtrado</p>
        @endif
        @if($filters['location_id'])
            <p>• Ubicación filtrada</p>
        @endif
        <p>• Agrupado por: {{ $group_by === 'product' ? 'Producto' : 'Ubicación' }}</p>
    </div>

    <!-- Statistics -->
    <div class="stats">
        <div class="stat-item">
            <div class="stat-label">Total Registros</div>
            <div class="stat-value">{{ $stats['total_items'] }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Cantidad Total</div>
            <div class="stat-value">{{ number_format($stats['total_quantity'], 0, ',', '.') }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Valor Total</div>
            <div class="stat-value">${{ number_format($stats['total_value'], 0, ',', '.') }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Alertas</div>
            <div class="stat-value">{{ $stats['low_stock'] + $stats['expired'] }}</div>
        </div>
    </div>

    <!-- Table -->
    @if($group_by === 'product')
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Código</th>
                    <th>Marca</th>
                    <th style="text-align: right;">Cantidad</th>
                    <th>Unidad</th>
                    <th style="text-align: right;">Cantidad Total</th>
                    <th style="text-align: right;">Valor</th>
                    <th style="text-align: center;">Ubic.</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $item)
                @php
                    $hasPackaging = isset($item['base_quantity']) && $item['base_quantity'] > 1;
                    $fullUnitName = $item['unit'];
                    if ($hasPackaging) {
                        $fullUnitName = $item['unit'] . ' de ' . $item['base_quantity'] . ' ' . $item['base_unit'];
                    }

                    $totalBaseQuantity = '-';
                    if ($hasPackaging && isset($item['total_base_quantity'])) {
                        $totalBaseQuantity = number_format($item['total_base_quantity'], 0, ',', '.') . ' ' . $item['base_unit'];
                    }
                @endphp
                <tr>
                    <td>{{ $item['product_name'] }}</td>
                    <td>{{ $item['product_code'] }}</td>
                    <td>{{ $item['brand_name'] }}</td>
                    <td style="text-align: right;">{{ number_format($item['total_quantity'], 0, ',', '.') }}</td>
                    <td>{{ $fullUnitName }}</td>
                    <td style="text-align: right;">{{ $totalBaseQuantity }}</td>
                    <td style="text-align: right;">${{ number_format($item['total_value'], 0, ',', '.') }}</td>
                    <td style="text-align: center;">{{ count($item['locations']) }}</td>
                    <td>
                        @php
                            $statusClass = $item['status'] === 'good' ? 'status-good' : ($item['status'] === 'low' ? 'status-low' : 'status-out');
                            $statusLabel = $item['status'] === 'good' ? 'OK' : ($item['status'] === 'low' ? 'Bajo' : 'Agotado');
                        @endphp
                        <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <table>
            <thead>
                <tr>
                    <th>Ubicación</th>
                    <th>Tipo</th>
                    <th style="text-align: center;">Productos</th>
                    <th style="text-align: right;">Cantidad</th>
                    <th style="text-align: right;">Valor Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $item)
                <tr>
                    <td>{{ $item['location_name'] }}</td>
                    <td>{{ $item['location_type'] === 'warehouse' ? 'Bodega' : 'Finca' }}</td>
                    <td style="text-align: center;">{{ $item['total_items'] }}</td>
                    <td style="text-align: right;">{{ number_format($item['total_quantity'], 0, ',', '.') }}</td>
                    <td style="text-align: right;">${{ number_format($item['total_value'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>AgriFlor - Sistema de Gestión de Inventarios | Página {PAGENO} de {nbpg}</p>
    </div>
</body>
</html>
