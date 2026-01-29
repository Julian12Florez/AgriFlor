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
            font-size: 8px;
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
            padding: 6px 3px;
            text-align: left;
            font-size: 7px;
            font-weight: bold;
        }
        table tbody td {
            padding: 4px 3px;
            border-bottom: 1px solid #ddd;
            font-size: 7px;
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
        .type-badge {
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
        }
        .type-entry { background-color: #4caf50; color: white; }
        .type-exit { background-color: #f44336; color: white; }
        .type-transfer { background-color: #2196f3; color: white; }
        .type-application { background-color: #ff9800; color: white; }
        .type-adjustment { background-color: #9c27b0; color: white; }
        .text-green { color: #4caf50; }
        .text-red { color: #f44336; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Generado el {{ $date }} por {{ $user }}</p>
    </div>

    <!-- Product Info -->
    <div class="info-section">
        <p><strong>Producto:</strong> {{ $product->name }} ({{ $product->base_unit }})</p>
        @if($product->product_code)
            <p><strong>Código:</strong> {{ $product->product_code }}</p>
        @endif
        <p><strong>Categoría:</strong> {{ ucfirst($product->category ?? 'N/A') }}</p>
        @if($filters['start_date'] && $filters['end_date'])
            <p><strong>Período:</strong> {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}</p>
        @endif
        @if($filters['location_id'])
            <p><strong>Ubicación:</strong> Filtrada</p>
        @endif
    </div>

    <!-- Statistics -->
    <div class="stats">
        <div class="stat-item">
            <div class="stat-label">Total Movimientos</div>
            <div class="stat-value">{{ $summary['total_movements'] }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Total Entradas ({{ $product->base_unit }})</div>
            <div class="stat-value" style="color: #4caf50;">{{ number_format($summary['total_entries'], 0, ',', '.') }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Total Salidas ({{ $product->base_unit }})</div>
            <div class="stat-value" style="color: #f44336;">{{ number_format($summary['total_exits'], 0, ',', '.') }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Stock Actual ({{ $product->base_unit }})</div>
            <div class="stat-value">{{ number_format($summary['current_stock'], 0, ',', '.') }}</div>
        </div>
    </div>

    <!-- Table -->
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Marca</th>
                <th>Ubicación</th>
                <th style="text-align: right;">Entrada ({{ $product->base_unit }})</th>
                <th style="text-align: right;">Salida ({{ $product->base_unit }})</th>
                <th style="text-align: right;">Saldo ({{ $product->base_unit }})</th>
                <th>Detalle Empaque</th>
                <th>Responsable</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movements as $mov)
            @php
                $typeClass = match($mov['type']) {
                    'entry' => 'type-entry',
                    'exit' => 'type-exit',
                    'transfer' => 'type-transfer',
                    'application' => 'type-application',
                    'adjustment' => 'type-adjustment',
                    default => 'type-entry'
                };
                $typeLabel = match($mov['type']) {
                    'entry' => 'ENT',
                    'exit' => 'SAL',
                    'transfer' => 'TRF',
                    'application' => 'APL',
                    'adjustment' => 'AJU',
                    default => $mov['type']
                };

                $packagingDetail = '';
                if ($mov['original_unit'] !== $product->base_unit) {
                    $packagingDetail = $mov['original_quantity'] . ' ' . $mov['original_unit'];
                }
            @endphp
            <tr>
                <td>{{ \Carbon\Carbon::parse($mov['date'])->format('d/m/Y H:i') }}</td>
                <td>
                    <span class="type-badge {{ $typeClass }}">{{ $typeLabel }}</span>
                </td>
                <td>{{ $mov['brand_name'] }}</td>
                <td>{{ $mov['location_name'] }}</td>
                <td style="text-align: right;">
                    @if($mov['quantity_in'] > 0)
                        <span class="text-green">+{{ number_format($mov['quantity_in'], 0, ',', '.') }}</span>
                    @endif
                </td>
                <td style="text-align: right;">
                    @if($mov['quantity_out'] > 0)
                        <span class="text-red">-{{ number_format($mov['quantity_out'], 0, ',', '.') }}</span>
                    @endif
                </td>
                <td style="text-align: right; font-weight: bold;">{{ number_format($mov['balance'], 0, ',', '.') }}</td>
                <td>{{ $packagingDetail }}</td>
                <td>{{ $mov['responsible_user'] }}</td>
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
