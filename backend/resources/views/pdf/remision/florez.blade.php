{{--
    REMISION DE DESPACHO - Plantilla "FLOREZ" (clasico tradicional)
    Identidad: tipografia serif, tinta #3E2723 y ocre #8D6E63, encabezado centrado
    con doble filete, tabla con todos los bordes y encabezado en fondo tenue,
    firmas centradas al pie con lineas de puntos largas.
    Motor: DomPDF (sin flexbox, sin grid, sin fuentes externas).
--}}
@php
    $company = $company ?? [];
    $doc     = $doc ?? [];
    $items   = $items ?? [];
    $lotes   = $lotes ?? [];
    $firmas  = $firmas ?? [];

    // Devuelve '-' (no cadena vacia) para que el marcador no dependa de ?: en la vista:
    // en PHP la cadena "0" es falsy y ?: la convertiria en el placeholder.
    $fmtFecha = static function ($v) {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('d/m/Y');
        }
        return ($v === null || $v === '') ? '-' : (string) $v;
    };

    $fmtCant = static function ($v) {
        if ($v === null || $v === '') {
            return '-';
        }
        if (! is_numeric($v)) {
            return (string) $v;
        }
        $f = (float) $v;
        return (floor($f) == $f)
            ? number_format($f, 0, ',', '.')
            : number_format($f, 2, ',', '.');
    };

    $has = static function ($arr, $key) {
        return isset($arr[$key]) && trim((string) $arr[$key]) !== '';
    };

    $contacto = [];
    if ($has($company, 'address')) { $contacto[] = $company['address']; }
    if ($has($company, 'city'))    { $contacto[] = $company['city']; }
    if ($has($company, 'phone'))   { $contacto[] = 'Tel. ' . $company['phone']; }
    if ($has($company, 'email'))   { $contacto[] = $company['email']; }

    $fiscales = [];
    if ($has($company, 'legalRep'))  { $fiscales[] = 'Representante legal: ' . $company['legalRep']; }
    if ($has($company, 'taxRegime')) { $fiscales[] = 'Regimen: ' . $company['taxRegime']; }
    if ($has($company, 'ciiu'))      { $fiscales[] = 'Actividad CIIU: ' . $company['ciiu']; }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Remision de Despacho {{ $doc['numero'] ?? '' }}</title>
    <style>
        @page { size: letter portrait; margin: 0; }

        * { margin: 0; padding: 0; }

        body {
            font-family: "DejaVu Serif", serif;
            font-size: 9px;
            line-height: 1.5;
            color: #3E2723;
            margin: 1.3cm 1.5cm 2.1cm 1.5cm;
        }

        .center { text-align: center; }
        .r { text-align: right; }

        /* ---------- Encabezado con orla ---------- */
        .crest {
            border-top: 4px double #3E2723;
            border-bottom: 1px solid #8D6E63;
            padding: 14px 10px 6px 10px;
            text-align: center;
        }

        .crest-name {
            font-size: 19px;
            font-weight: bold;
            letter-spacing: 2.5px;
            color: #3E2723;
            line-height: 1.25;
        }

        .crest-rule {
            font-size: 10px;
            color: #8D6E63;
            padding: 3px 0 5px 0;
        }

        .crest-meta {
            font-size: 8px;
            color: #5D4037;
            line-height: 1.6;
        }

        .crest-fiscal {
            font-size: 7.4px;
            color: #8D6E63;
            font-style: italic;
            padding-top: 3px;
        }

        .crest-bottom {
            border-top: 1px solid #8D6E63;
            border-bottom: 4px double #3E2723;
            height: 4px;
            font-size: 1px;
            line-height: 4px;
            margin-bottom: 16px;
        }

        /* ---------- Titulo del documento ---------- */
        .doc-title {
            font-size: 15.5px;
            font-weight: bold;
            letter-spacing: 4.5px;
            color: #3E2723;
            text-align: center;
        }

        .doc-orn {
            text-align: center;
            font-size: 12px;
            color: #8D6E63;
            padding: 1px 0 8px 0;
        }

        .doc-number-box {
            display: inline-block;
            border: 1.5px solid #8D6E63;
            background: #FBF7F2;
            padding: 5px 20px;
            font-size: 13px;
            font-weight: bold;
            color: #6D4C41;
            letter-spacing: 1.2px;
        }

        .doc-number-cap {
            font-size: 7px;
            letter-spacing: 2.4px;
            color: #8D6E63;
            font-style: italic;
        }

        /* ---------- Datos del despacho ---------- */
        table.datos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }
        table.datos td {
            border: 1px solid #8D6E63;
            padding: 6px 9px;
            vertical-align: top;
        }
        table.datos td.lbl {
            width: 17%;
            background: #F5EFE7;
            font-size: 7.8px;
            letter-spacing: 1.2px;
            color: #6D4C41;
            font-weight: bold;
        }
        table.datos td.val { width: 33%; font-size: 10px; }
        table.datos td.val-strong { font-weight: bold; }

        /* ---------- Tabla de productos con todos los bordes ---------- */
        .caption {
            text-align: center;
            font-size: 9.5px;
            font-weight: bold;
            letter-spacing: 3px;
            color: #3E2723;
            margin: 20px 0 7px 0;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1.5px solid #5D4037;
        }

        table.items thead th {
            background: #F0E6D8;
            border: 1px solid #8D6E63;
            color: #4E342E;
            font-size: 7.6px;
            letter-spacing: 1.1px;
            font-weight: bold;
            padding: 7px 5px;
            text-align: center;
        }

        table.items tbody td {
            border: 1px solid #B79E8B;
            padding: 5px 6px;
            font-size: 8.8px;
            vertical-align: top;
            word-wrap: break-word;
        }

        table.items tbody td.num { text-align: center; color: #8D6E63; font-size: 8px; }
        table.items tbody td.qty { text-align: right; font-weight: bold; }
        table.items tbody td.ctr { text-align: center; }

        table.items tfoot td {
            border: 1px solid #8D6E63;
            background: #F5EFE7;
            padding: 6px;
            font-size: 8.4px;
            font-weight: bold;
            color: #4E342E;
        }

        /* ---------- Lotes y observaciones ---------- */
        .frame {
            border: 1px solid #8D6E63;
            padding: 9px 12px;
            margin-top: 14px;
            background: #FDFAF6;
        }
        .frame-title {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 2.2px;
            color: #6D4C41;
            border-bottom: 1px solid #D7C4B3;
            padding-bottom: 4px;
            margin-bottom: 5px;
        }
        .frame-body { font-size: 9px; color: #4E342E; }
        .frame-italic { font-style: italic; }

        /* ---------- Firmas centradas con lineas de puntos ---------- */
        .sign-block { page-break-inside: avoid; }
        .signs-heading {
            text-align: center;
            font-size: 8px;
            letter-spacing: 3px;
            color: #6D4C41;
            font-style: italic;
            margin: 30px 0 4px 0;
        }
        .signs-rule {
            border-top: 1px solid #8D6E63;
            width: 45%;
            margin: 0 auto 26px auto;
            height: 1px;
            font-size: 1px;
            line-height: 1px;
        }

        table.signs { width: 100%; border-collapse: collapse; }
        table.signs td {
            width: 50%;
            border: none;
            padding: 0 22px;
            text-align: center;
            vertical-align: top;
        }
        .sign-role {
            font-size: 9.5px;
            font-weight: bold;
            letter-spacing: 3.4px;
            color: #3E2723;
            padding-bottom: 26px;
        }
        .dotline {
            border-bottom: 1.2px dotted #5D4037;
            height: 17px;
        }
        .dotline-fill {
            font-size: 9px;
            color: #3E2723;
        }
        .sign-cap {
            font-size: 7.2px;
            letter-spacing: 1.6px;
            color: #8D6E63;
            font-style: italic;
            padding: 2px 0 16px 0;
        }

        /* ---------- Pie ---------- */
        .footer {
            position: fixed;
            left: 1.5cm;
            right: 1.5cm;
            bottom: 0.85cm;
            border-top: 3px double #8D6E63;
            padding-top: 5px;
            text-align: center;
            font-size: 7.2px;
            font-style: italic;
            color: #8D6E63;
        }
        /* DomPDF resuelve counter(page); counter(pages) no esta soportado (devuelve 0). */
        .pagenum:after { content: counter(page); }
    </style>
</head>
<body>

    {{-- =============== PIE FIJO =============== --}}
    <div class="footer">
        {{ $company['name'] ?? '' }} &nbsp;&mdash;&nbsp; Impreso el {{ now()->format('d/m/Y') }} a las {{ now()->format('H:i') }}
        &nbsp;&mdash;&nbsp; Pagina <span class="pagenum"></span>
        <br>
        Documento de despacho de mercancia. No es factura de venta ni documento equivalente.
    </div>

    {{-- =============== ENCABEZADO CON ORLA =============== --}}
    <div class="crest">
        @if(!empty($company['logoDataUri']))
            <img src="{{ $company['logoDataUri'] }}" style="height: 46px;" alt=""><br>
        @endif
        <div class="crest-name">{{ mb_strtoupper($company['name'] ?? '') }}</div>
        {{-- Ornamento: filete + rombo (U+25CA), presente en DejaVu Serif --}}
        <div class="crest-rule">&#8212;&#8212;&#8212;&#8212;&nbsp;&#9674;&nbsp;&#8212;&#8212;&#8212;&#8212;</div>
        <div class="crest-meta">
            @if($has($company, 'nit'))
                NIT {{ $company['nit'] }}@if(count($contacto) > 0)<br>@endif
            @endif
            @if(count($contacto) > 0)
                {{ implode('  ·  ', $contacto) }}
            @endif
        </div>
        @if(count($fiscales) > 0)
            <div class="crest-fiscal">{{ implode('  ·  ', $fiscales) }}</div>
        @endif
    </div>
    <div class="crest-bottom">&nbsp;</div>

    {{-- =============== TITULO =============== --}}
    <div class="doc-title">REMISION DE DESPACHO</div>
    {{-- Ornamento: asterismo (U+2042), presente en DejaVu Serif --}}
    <div class="doc-orn">&#8258;</div>
    <div class="center">
        <div class="doc-number-box">No. {{ $doc['numero'] ?? 'S/N' }}</div>
        <div class="doc-number-cap">NUMERO CONSECUTIVO DEL DOCUMENTO</div>
    </div>

    {{-- =============== DATOS DEL DESPACHO =============== --}}
    <table class="datos">
        <tr>
            <td class="lbl">FECHA</td>
            <td class="val val-strong">{{ $fmtFecha($doc['fecha'] ?? null) }}</td>
            <td class="lbl">TIPO DE SALIDA</td>
            <td class="val">{{ $doc['tipo'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">ORIGEN</td>
            <td class="val val-strong">{{ $doc['origen'] ?? '-' }}</td>
            <td class="lbl">DESTINO</td>
            <td class="val val-strong">{{ $doc['destino'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">RESPONSABLE</td>
            <td class="val" colspan="3">{{ $doc['responsable'] ?? '-' }}</td>
        </tr>
    </table>

    {{-- =============== PRODUCTOS =============== --}}
    <div class="caption">RELACION DE PRODUCTOS DESPACHADOS</div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 13%;">CODIGO</th>
                <th style="width: 32%;">PRODUCTO</th>
                <th style="width: 15%;">MARCA</th>
                <th style="width: 10%;">CANTIDAD</th>
                <th style="width: 11%;">UNIDAD</th>
                <th style="width: 14%;">LOTE</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $i => $item)
                <tr>
                    <td class="num">{{ $i + 1 }}</td>
                    <td>{{ $item['codigo'] ?? '-' }}</td>
                    <td>{{ $item['producto'] ?? '-' }}</td>
                    <td>{{ $item['marca'] ?? '-' }}</td>
                    <td class="qty">{{ $fmtCant($item['cantidad'] ?? null) }}</td>
                    <td class="ctr">{{ $item['unidad'] ?? '-' }}</td>
                    <td class="ctr">{{ $item['lote'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="ctr" style="padding: 14px;">
                        <span class="frame-italic">No se registran productos en este despacho.</span>
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" class="r">TOTAL DE ITEMS RELACIONADOS: {{ count($items) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- =============== LOTES DE CULTIVO =============== --}}
    @if(count($lotes) > 0)
        <div class="frame">
            <div class="frame-title">LOTES DE CULTIVO</div>
            <div class="frame-body frame-italic">{{ implode('  ·  ', $lotes) }}</div>
        </div>
    @endif

    {{-- =============== OBSERVACIONES =============== --}}
    @if($has($doc, 'observaciones'))
        <div class="frame">
            <div class="frame-title">OBSERVACIONES</div>
            <div class="frame-body">{{ $doc['observaciones'] }}</div>
        </div>
    @endif

    {{-- =============== FIRMAS =============== --}}
    <div class="sign-block">
    <div class="signs-heading">En constancia firman</div>
    <div class="signs-rule">&nbsp;</div>

    <table class="signs">
        <tr>
            <td>
                <div class="sign-role">ENTREGA</div>
                <div class="dotline"></div>
                <div class="sign-cap">Firma</div>
                <div class="dotline">@if($has($firmas, 'entrega'))<span class="dotline-fill">{{ $firmas['entrega'] }}</span>@endif</div>
                <div class="sign-cap">Nombre completo</div>
                <div class="dotline"></div>
                <div class="sign-cap">Cedula de ciudadania</div>
            </td>
            <td>
                <div class="sign-role">RECIBE</div>
                <div class="dotline"></div>
                <div class="sign-cap">Firma</div>
                <div class="dotline">@if($has($firmas, 'recibe'))<span class="dotline-fill">{{ $firmas['recibe'] }}</span>@endif</div>
                <div class="sign-cap">Nombre completo</div>
                <div class="dotline"></div>
                <div class="sign-cap">Cedula de ciudadania</div>
            </td>
        </tr>
    </table>
    </div>

</body>
</html>
