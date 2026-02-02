<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Liquidación</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { color: #2E7D32; margin: 0; font-size: 18px; }
        .header p { margin: 2px 0; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th { background-color: #2E7D32; color: white; padding: 6px 4px; text-align: left; font-size: 10px; }
        td { padding: 4px; border-bottom: 1px solid #ddd; font-size: 10px; }
        .worker-header { background-color: #E8F5E9; font-weight: bold; }
        .subtotal { background-color: #F1F8E9; font-weight: bold; }
        .total { background-color: #2E7D32; color: white; font-weight: bold; font-size: 12px; }
        .amount { text-align: right; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>AGRIFLOR - Reporte de Liquidación</h1>
        <p>Período: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</p>
        <p>Generado: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    @foreach($grouped as $group)
    <table>
        <tr class="worker-header">
            <td colspan="6">
                {{ $group['worker']->worker_code }} - {{ $group['worker']->full_name }}
                (Doc: {{ $group['worker']->document_id }})
            </td>
        </tr>
        <tr>
            <th>Fecha</th>
            <th>Código Tarea</th>
            <th>Tarea</th>
            <th class="amount">Bruto</th>
            <th class="amount">Deducciones</th>
            <th class="amount">Neto</th>
        </tr>
        @foreach($group['assignments'] as $a)
        <tr>
            <td>{{ $a->date->format('d/m/Y') }}</td>
            <td>{{ $a->task_code }}</td>
            <td>{{ $a->task->name }}</td>
            <td class="amount">${{ number_format($a->gross_amount, 0, ',', '.') }}</td>
            <td class="amount">${{ number_format($a->total_deductions, 0, ',', '.') }}</td>
            <td class="amount">${{ number_format($a->net_amount, 0, ',', '.') }}</td>
        </tr>
        @endforeach
        <tr class="subtotal">
            <td colspan="3">Subtotal - {{ $group['subtotals']['days_worked'] }} días</td>
            <td class="amount">${{ number_format($group['subtotals']['gross_amount'], 0, ',', '.') }}</td>
            <td class="amount">${{ number_format($group['subtotals']['total_deductions'], 0, ',', '.') }}</td>
            <td class="amount">${{ number_format($group['subtotals']['net_amount'], 0, ',', '.') }}</td>
        </tr>
    </table>
    <br>
    @endforeach

    <table>
        <tr class="total">
            <td colspan="3">TOTALES GENERALES ({{ $totals['total_assignments'] }} asignaciones)</td>
            <td class="amount">${{ number_format($totals['gross_amount'], 0, ',', '.') }}</td>
            <td class="amount">${{ number_format($totals['total_deductions'], 0, ',', '.') }}</td>
            <td class="amount">${{ number_format($totals['net_amount'], 0, ',', '.') }}</td>
        </tr>
    </table>
</body>
</html>
