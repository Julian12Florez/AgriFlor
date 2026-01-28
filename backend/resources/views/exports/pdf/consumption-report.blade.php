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
            font-size: 9px;
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
            width: 33.33%;
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
            padding: 8px 4px;
            text-align: left;
            font-size: 8px;
            font-weight: bold;
        }
        table tbody td {
            padding: 5px 4px;
            border-bottom: 1px solid #ddd;
            font-size: 8px;
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
        @if($filters['start_date'] && $filters['end_date'])
            <p>• Período: {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}</p>
        @endif
        @if($filters['product_id'])
            <p>• Producto filtrado</p>
        @endif
        @if($filters['location_id'])
            <p>• Finca filtrada</p>
        @endif
    </div>

    <!-- Statistics -->
    <div class="stats">
        <div class="stat-item">
            <div class="stat-label">Total Salidas</div>
            <div class="stat-value">{{ $summary['outputs_count'] }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Total Aplicaciones</div>
            <div class="stat-value">{{ $summary['total_consumptions'] }}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Cantidad Consumida</div>
            <div class="stat-value">{{ number_format($summary['total_quantity_consumed'], 2, ',', '.') }}</div>
        </div>
    </div>

    <!-- Table -->
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>N° Salida</th>
                <th>Producto</th>
                <th>Código</th>
                <th>Marca</th>
                <th style="text-align: right;">Cantidad</th>
                <th>Unidad</th>
                <th style="text-align: right;">Cantidad Total</th>
                <th>Finca Destino</th>
            </tr>
        </thead>
        <tbody>
            @foreach($consumptions as $item)
            @php
                $hasPackaging = isset($item->base_quantity) && $item->base_quantity > 1;
                $fullUnitName = $item->unit ?? 'unidades';
                if ($hasPackaging) {
                    $fullUnitName = $item->unit . ' de ' . $item->base_quantity . ' ' . $item->base_unit;
                }

                $totalBaseQuantity = '-';
                if ($hasPackaging && isset($item->total_base_quantity)) {
                    $totalBaseQuantity = number_format($item->total_base_quantity, 0, ',', '.') . ' ' . $item->base_unit;
                }
            @endphp
            <tr>
                <td>{{ \Carbon\Carbon::parse($item->output_date)->format('d/m/Y') }}</td>
                <td>{{ $item->output_number }}</td>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->product_code }}</td>
                <td>{{ $item->brand_name }}</td>
                <td style="text-align: right;">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                <td>{{ $fullUnitName }}</td>
                <td style="text-align: right;">{{ $totalBaseQuantity }}</td>
                <td>{{ $item->destination_location_name ?? 'N/A' }}</td>
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
