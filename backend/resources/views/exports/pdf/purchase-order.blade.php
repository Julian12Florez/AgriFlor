<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra - {{ $purchase->order_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: white;
        }

        .container {
            max-width: 100%;
            padding: 20px;
        }

        /* Header with table layout for DomPDF compatibility */
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #2E7D32;
            padding-bottom: 20px;
        }

        .header-table {
            width: 100%;
            border: none;
        }

        .header-table td {
            border: none;
            vertical-align: top;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2E7D32;
            margin-bottom: 5px;
        }

        .company-details {
            color: #666;
            line-height: 1.6;
        }

        .order-info {
            text-align: right;
            background: #f5f5f5;
            padding: 15px;
        }

        .order-title {
            font-size: 18px;
            font-weight: bold;
            color: #2E7D32;
            margin-bottom: 10px;
        }

        .order-details {
            line-height: 1.8;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #2E7D32;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .supplier-section {
            margin: 20px 0;
            background: #f9f9f9;
            padding: 15px;
        }

        .supplier-table {
            width: 100%;
            border: none;
        }

        .supplier-table td {
            border: none;
            vertical-align: top;
            width: 50%;
            padding: 0 10px 0 0;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .products-table th {
            background: #2E7D32;
            color: white;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            padding: 8px 5px;
            text-align: left;
        }

        .products-table td {
            border: 1px solid #ddd;
            padding: 8px 5px;
            font-size: 10px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totals-container {
            margin: 20px 0;
        }

        .totals-table {
            width: 280px;
            border-collapse: collapse;
            float: right;
        }

        .totals-table td {
            padding: 6px 10px;
            border: 1px solid #ddd;
        }

        .totals-table .label {
            background: #f5f5f5;
            font-weight: bold;
            text-align: right;
        }

        .totals-table .total-row td {
            background: #2E7D32;
            color: white;
            font-weight: bold;
            font-size: 13px;
        }

        .observations-section {
            margin: 15px 0;
            background: #fff9c4;
            border: 1px solid #f7dc6f;
            padding: 12px;
            clear: both;
        }

        .terms-section {
            margin: 20px 0;
            background: #f9f9f9;
            padding: 15px;
        }

        .terms-list {
            list-style: disc;
            padding-left: 20px;
        }

        .terms-list li {
            margin: 5px 0;
        }

        .signatures-table {
            width: 100%;
            margin-top: 60px;
            border: none;
        }

        .signatures-table td {
            text-align: center;
            width: 50%;
            border: none;
            padding-top: 40px;
            border-top: 1px solid #333;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            color: #666;
            font-size: 10px;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        .equivalence-text {
            color: #0958d9;
            font-weight: bold;
            font-size: 9px;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }

        .status-ordered, .status-pending {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-in_transit {
            background: #fff3e0;
            color: #e65100;
        }

        .status-received {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <table class="header-table">
                <tr>
                    <td style="width: 55%;">
                        @if($company['logoDataUri'])
                            <img src="{{ $company['logoDataUri'] }}" alt=""
                                 style="max-height: 70px; max-width: 200px; margin-bottom: 6px;">
                        @endif
                        <div class="company-name">{{ $company['name'] }}</div>
                        <div class="company-details">
                            @if($company['nit'])
                                NIT: {{ $company['nit'] }}<br>
                            @endif
                            @if($company['address'])
                                {{ $company['address'] }}<br>
                            @endif
                            @if($company['city'])
                                {{ $company['city'] }}<br>
                            @endif
                            @if($company['phone'])
                                Tel: {{ $company['phone'] }}<br>
                            @endif
                            @if($company['email'])
                                Email: {{ $company['email'] }}
                            @endif
                        </div>
                    </td>
                    <td style="width: 45%;">
                        <div class="order-info">
                            <div class="order-title">ORDEN DE COMPRA</div>
                            <div class="order-details">
                                <strong>No:</strong> {{ $purchase->order_number }}<br>
                                <strong>Fecha:</strong> {{ $purchase->purchase_date->format('d/m/Y') }}<br>
                                @if($purchase->expected_delivery)
                                    <strong>Entrega:</strong> {{ $purchase->expected_delivery->format('d/m/Y') }}<br>
                                @endif
                                <strong>Estado:</strong>
                                <span class="status-badge status-{{ $purchase->status }}">
                                    @switch($purchase->status)
                                        @case('ordered')
                                        @case('pending')
                                            Disponible para Recepcion
                                            @break
                                        @case('in_transit')
                                            Recepcion Iniciada
                                            @break
                                        @case('received')
                                            Recibido
                                            @break
                                        @case('cancelled')
                                            Cancelado
                                            @break
                                        @default
                                            {{ ucfirst($purchase->status) }}
                                    @endswitch
                                </span>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Datos del Proveedor -->
        <div class="supplier-section">
            <div class="section-title">DATOS DEL PROVEEDOR</div>
            <table class="supplier-table">
                <tr>
                    <td>
                        <strong>Razon Social:</strong><br>
                        {{ $purchase->supplier->name }}<br><br>
                        <strong>NIT:</strong><br>
                        {{ $purchase->supplier->nit }}<br><br>
                        <strong>Direccion:</strong><br>
                        {{ $purchase->supplier->address ?? 'No disponible' }}<br>
                        {{ $purchase->supplier->city ?? '' }}
                    </td>
                    <td>
                        @php
                            $primaryContact = $purchase->supplier->relationLoaded('contacts')
                                ? $purchase->supplier->contacts->first()
                                : null;
                        @endphp
                        <strong>Contacto:</strong><br>
                        {{ $primaryContact?->name ?? 'No disponible' }}
                        @if($primaryContact?->position)
                            ({{ $primaryContact->position }})
                        @endif
                        <br><br>
                        <strong>Telefono:</strong><br>
                        {{ $primaryContact?->phone ?? $purchase->supplier->phone ?? 'No disponible' }}<br><br>
                        <strong>Email:</strong><br>
                        {{ $primaryContact?->email ?? $purchase->supplier->email ?? 'No disponible' }}
                    </td>
                </tr>
            </table>
            @if($purchase->supplier->payment_terms)
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                    <strong>Terminos de Pago:</strong> {{ $purchase->supplier->payment_terms }}
                </div>
            @endif
        </div>

        <!-- Informacion de Entrega -->
        <div class="supplier-section">
            <div class="section-title">INFORMACION DE ENTREGA</div>
            <table class="supplier-table">
                <tr>
                    <td>
                        <strong>Entregar en:</strong><br>
                        {{ $purchase->destinationLocation->name ?? 'No definida' }}
                        @if($purchase->destinationLocation?->municipality)
                            - {{ $purchase->destinationLocation->municipality }}
                        @endif
                    </td>
                    <td>
                        <strong>Fecha de Entrega:</strong><br>
                        @if($purchase->expected_delivery)
                            {{ $purchase->expected_delivery->format('d/m/Y') }}
                        @else
                            Por coordinar
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <!-- Datos de Facturacion (ENVIAR A) -->
        <div class="supplier-section">
            <div class="section-title">ENVIAR FACTURA A</div>
            <table class="supplier-table">
                <tr>
                    <td>
                        {{-- La factura se emite a nombre de la MISMA empresa que
                             firma la orden; antes era un dato fijo de config. --}}
                        <strong>Empresa:</strong><br>
                        {{ $company['name'] }}<br><br>
                        <strong>NIT:</strong><br>
                        {{ $company['nit'] }}<br><br>
                        <strong>Direccion:</strong><br>
                        {{ $company['address'] }}<br>
                        {{ $company['city'] }}
                    </td>
                    <td>
                        <strong>E-mail:</strong><br>
                        {{ $company['email'] }}<br><br>
                        <strong>Telefono:</strong><br>
                        {{ $company['phone'] }}
                    </td>
                </tr>
            </table>
        </div>

        <!-- Productos Solicitados -->
        <div style="margin: 20px 0;">
            <div class="section-title">PRODUCTOS SOLICITADOS</div>
            <table class="products-table">
                <thead>
                    <tr>
                        <th style="width: 4%">#</th>
                        <th style="width: 20%">Producto</th>
                        <th style="width: 10%">Marca</th>
                        <th style="width: 7%">Cant.</th>
                        <th style="width: 10%">Unid. Empaque</th>
                        <th style="width: 15%">Equivalencia Total</th>
                        <th style="width: 9%">Precio Unit.</th>
                        <th style="width: 5%">IVA</th>
                        <th style="width: 10%">Total</th>
                        @if($purchase->purchaseItems->contains(fn($item) => $item->expiration_date))
                            <th style="width: 10%">Vencimiento</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchase->purchaseItems as $index => $item)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ $item->product->name }}</td>
                            <td>{{ $item->brand->name }}</td>
                            <td class="text-right">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                            <td class="text-center">{{ $item->packagingUnit->name ?? $item->product->base_unit ?? 'N/A' }}</td>
                            <td class="text-center">
                                @if($item->packagingUnit && $item->quantity_in_base_units)
                                    <span class="equivalence-text">
                                        {{ number_format($item->quantity, 0, ',', '.') }} {{ $item->packagingUnit->name }}
                                        &times; {{ $item->packagingUnit->base_quantity }} {{ $item->packagingUnit->base_unit }}
                                        = {{ number_format($item->quantity_in_base_units, 0, ',', '.') }} {{ $item->packagingUnit->base_unit }}
                                    </span>
                                @else
                                    <span class="equivalence-text">
                                        {{ number_format($item->quantity, 0, ',', '.') }} {{ $item->product->base_unit ?? 'und' }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right">${{ number_format($item->unit_price, 0, ',', '.') }}</td>
                            <td class="text-center">{{ $item->iva_percentage ?? 0 }}%</td>
                            <td class="text-right">${{ number_format($item->total ?? $item->subtotal, 0, ',', '.') }}</td>
                            @if($purchase->purchaseItems->contains(fn($i) => $i->expiration_date))
                                <td class="text-center">
                                    {{ $item->expiration_date ? $item->expiration_date->format('d/m/Y') : 'N/A' }}
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Totales -->
        <div class="totals-container clearfix">
            <table class="totals-table">
                <tr>
                    <td class="label">Subtotal:</td>
                    <td class="text-right">${{ number_format($purchase->subtotal, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="label">
                        IVA{{ $singleIvaPercentage !== null ? " ({$singleIvaPercentage}%)" : ' (Mixto)' }}:
                    </td>
                    <td class="text-right">${{ number_format($purchase->tax, 0, ',', '.') }}</td>
                </tr>
                <tr class="total-row">
                    <td class="label" style="background: #2E7D32; color: white;">TOTAL:</td>
                    <td class="text-right">${{ number_format($purchase->total, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        <!-- Observaciones -->
        @if($purchase->observations)
            <div class="observations-section" style="clear: both;">
                <div class="section-title">OBSERVACIONES</div>
                <p>{{ $purchase->observations }}</p>
            </div>
        @endif

        <!-- Terminos y Condiciones -->
        <div class="terms-section" style="clear: both;">
            <div class="section-title">TERMINOS Y CONDICIONES</div>
            <ul class="terms-list">
                <li>Los productos deben cumplir con las especificaciones tecnicas solicitadas.</li>
                <li>La entrega debe realizarse en la fecha acordada.</li>
                <li>Los productos deben estar en perfecto estado y con fecha de vencimiento vigente.</li>
                <li>Se requiere factura legal con todos los datos fiscales correctos.</li>
                <li>Cualquier dano o defecto debe ser reportado inmediatamente.</li>
                <li>El pago se realizara segun los terminos acordados previa entrega conforme.</li>
            </ul>
        </div>

        <!-- Firmas -->
        <table class="signatures-table">
            <tr>
                <td>
                    <strong>Solicitado por</strong><br>
                    {{ $purchase->creator?->name ?? '' }}<br>
                    Depto. de Compras
                </td>
                <td>
                    <strong>Aprobado por</strong><br>
                    _________________<br>
                    Gerencia
                </td>
            </tr>
        </table>

        <!-- Footer -->
        <div class="footer">
            <p>Documento generado automaticamente por {{ $company['name'] }} el {{ now()->format('d/m/Y H:i') }}</p>
            @if($company['phone'] || $company['email'])
                <p>Para consultas:
                    @if($company['phone']) {{ $company['phone'] }} @endif
                    @if($company['phone'] && $company['email']) - @endif
                    @if($company['email']) {{ $company['email'] }} @endif
                </p>
            @endif
        </div>
    </div>
</body>
</html>
