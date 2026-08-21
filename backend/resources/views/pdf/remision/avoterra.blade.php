{{--
    REMISION DE DESPACHO - Plantilla "AVOTERRA" (moderna y compacta)

    Comparte la ESTRUCTURA que el cliente aprobo en la plantilla AGRILOGISTIC
    (banda de encabezado a todo el ancho, franja de datos de la empresa,
    secciones tituladas, tabla con encabezado solido y filas alternadas, firmas
    en recuadro), pero con identidad propia para que siga siendo reconocible de
    un vistazo:

      - Banda BICOLOR: grafito #0F172A a la izquierda, verde #22C55E a la
        derecha, con un degradado escalonado de tres tramos debajo.
      - Recuadro del documento en negativo (fondo grafito sobre el verde), no
        en blanco.
      - Titulos de seccion como barra tenue con filete verde a la izquierda.
      - Esquinas suavizadas (border-radius) y tipografia sans en todo el
        documento.

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
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5px;
            line-height: 1.45;
            color: #1E293B;
            margin: 1.25cm 1.2cm 1.9cm 1.2cm;
        }

        /* ---------- Banda de encabezado (bicolor) ---------- */
        .band { width: 100%; border-collapse: collapse; }
        .band td { vertical-align: middle; border: none; }

        .band-left {
            background: #0F172A;
            padding: 14px 16px;
            color: #FFFFFF;
        }

        .brand-name {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 0.4px;
            color: #FFFFFF;
        }

        .brand-tag {
            font-size: 8px;
            letter-spacing: 2.4px;
            color: #4ADE80;
            padding-top: 3px;
        }

        .band-right {
            background: #22C55E;
            padding: 10px 12px;
            width: 40%;
        }

        /* Recuadro del documento en negativo: grafito sobre el verde. */
        .doc-box {
            background: #0F172A;
            border-radius: 4px;
            padding: 8px 11px;
            color: #FFFFFF;
        }

        .doc-title {
            font-size: 12px;
            font-weight: bold;
            color: #FFFFFF;
            letter-spacing: 0.6px;
            border-bottom: 1px solid #334155;
            padding-bottom: 4px;
            margin-bottom: 5px;
        }

        .doc-num {
            font-size: 17px;
            font-weight: bold;
            color: #4ADE80;
        }

        .doc-num-label {
            font-size: 7.5px;
            color: #94A3B8;
            letter-spacing: 1.4px;
        }

        /* Degradado escalonado grafito -> verde bajo la banda. */
        .gradient { width: 100%; border-collapse: collapse; }
        .gradient td { height: 6px; border: none; font-size: 0; line-height: 0; }
        .g1 { background: #0F172A; width: 25%; }
        .g2 { background: #14532D; width: 25%; }
        .g3 { background: #16A34A; width: 25%; }
        .g4 { background: #22C55E; width: 25%; }

        /* ---------- Franja de datos de la empresa ---------- */
        .company-strip {
            background: #F1F5F9;
            border-bottom: 1px solid #CBD5E1;
            padding: 7px 12px;
            font-size: 8.6px;
            color: #334155;
        }

        .company-strip strong { color: #0F172A; }
        .company-strip .sep { color: #22C55E; padding: 0 5px; }

        /* ---------- Titulos de seccion: barra tenue con filete verde ---------- */
        .section-head {
            background: #F1F5F9;
            border-left: 4px solid #22C55E;
            font-size: 8.4px;
            font-weight: bold;
            color: #0F172A;
            letter-spacing: 1.8px;
            padding: 4px 9px;
            margin: 15px 0 8px 0;
        }

        /* ---------- Bloque de datos del despacho ---------- */
        .info { width: 100%; border-collapse: collapse; }
        .info td {
            border: 1px solid #E2E8F0;
            padding: 6px 9px;
            vertical-align: top;
        }
        .info .lbl {
            background: #F8FAFC;
            width: 15%;
            font-size: 7.8px;
            letter-spacing: 0.8px;
            color: #64748B;
            font-weight: bold;
        }
        .info .val { width: 35%; font-size: 10px; color: #1E293B; }
        .info .val strong { color: #0F172A; }

        .route {
            background: #0F172A;
            color: #FFFFFF;
            padding: 8px 12px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 3px;
        }
        .route .rlabel { font-size: 7.5px; color: #4ADE80; letter-spacing: 1.4px; font-weight: normal; }
        .route .arrow { color: #22C55E; padding: 0 8px; }

        /* ---------- Tabla de productos ---------- */
        table.items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.items thead th {
            background: #0F172A;
            color: #FFFFFF;
            font-size: 7.8px;
            letter-spacing: 0.9px;
            font-weight: bold;
            text-align: left;
            padding: 7px 6px;
            border-right: 1px solid #1E293B;
            border-bottom: 3px solid #22C55E;
        }

        table.items tbody td {
            padding: 6px;
            font-size: 9px;
            border-bottom: 1px solid #E2E8F0;
            vertical-align: top;
            word-wrap: break-word;
        }

        .zebra { background: #F8FAFC; }
        .c { text-align: center; }
        .r { text-align: right; }
        .qty { font-weight: bold; color: #15803D; }
        .unit { color: #334155; }
        .muted { color: #94A3B8; }
        .code { font-size: 8.4px; color: #475569; }

        .items-foot {
            background: #22C55E;
            color: #0F172A;
            padding: 5px 8px;
            font-size: 8.6px;
            font-weight: bold;
            letter-spacing: 0.8px;
        }

        /* ---------- Lotes / observaciones ---------- */
        .chip {
            display: inline-block;
            border: 1px solid #22C55E;
            border-radius: 8px;
            background: #F0FDF4;
            color: #15803D;
            padding: 3px 9px;
            margin: 0 4px 4px 0;
            font-size: 8.6px;
        }

        .note {
            border: 1px solid #E2E8F0;
            border-left: 5px solid #22C55E;
            border-radius: 3px;
            background: #F8FAFC;
            padding: 8px 11px;
            font-size: 9px;
            color: #334155;
        }

        /* ---------- Firmas ---------- */
        .sign-wrap {
            border: 1px solid #0F172A;
            border-radius: 4px;
            padding: 12px 14px 10px 14px;
            margin-top: 18px;
            page-break-inside: avoid;
        }
        .sign-wrap .sw-title {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1.8px;
            color: #0F172A;
            border-bottom: 2px solid #22C55E;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        table.signs { width: 100%; border-collapse: collapse; }
        table.signs td {
            width: 50%;
            vertical-align: top;
            padding: 0 14px;
            border: none;
        }
        table.signs td.divider { width: 1px; padding: 0; border-left: 1px solid #E2E8F0; }
        .sign-role {
            font-size: 9.5px;
            font-weight: bold;
            color: #FFFFFF;
            background: #0F172A;
            border-radius: 3px;
            padding: 3px 9px;
            margin-bottom: 34px;
        }
        .sign-line {
            border-bottom: 1px solid #475569;
            height: 16px;
        }
        .sign-cap {
            font-size: 7.4px;
            letter-spacing: 0.9px;
            color: #64748B;
            padding: 2px 0 12px 0;
        }
        .sign-prefill { font-size: 9px; color: #1E293B; }

        /* ---------- Pie ---------- */
        .footer {
            position: fixed;
            left: 1.2cm;
            right: 1.2cm;
            bottom: 0.75cm;
            border-top: 2px solid #22C55E;
            padding-top: 4px;
            font-size: 7.6px;
            color: #64748B;
        }
        .footer .fr { float: right; }
        /* DomPDF resuelve counter(page); counter(pages) no esta soportado (devuelve 0). */
        .pagenum:after { content: counter(page); }
    </style>
</head>
<body>

    {{-- =============== PIE FIJO =============== --}}
    <div class="footer">
        <span class="fr">Pagina <span class="pagenum"></span></span>
        {{ $company['name'] ?? '' }} &nbsp;&middot;&nbsp; Impreso el {{ now()->format('d/m/Y \a \l\a\s H:i') }}
        &nbsp;&middot;&nbsp; Documento de despacho, no constituye factura de venta.
    </div>

    {{-- =============== ENCABEZADO =============== --}}
    <table class="band">
        <tr>
            <td class="band-left">
                @if(!empty($company['logoDataUri']))
                    <img src="{{ $company['logoDataUri'] }}" style="height: 42px;" alt="">
                    <div class="brand-tag">{{ mb_strtoupper($company['name'] ?? '') }}</div>
                @else
                    <div class="brand-name">{{ mb_strtoupper($company['name'] ?? '') }}</div>
                    <div class="brand-tag">DESPACHO CONTROLADO DE MERCANCIA</div>
                @endif
            </td>
            <td class="band-right">
                <div class="doc-box">
                    <div class="doc-title">REMISION DE DESPACHO</div>
                    <div class="doc-num-label">DOCUMENTO No.</div>
                    <div class="doc-num">{{ $doc['numero'] ?? 'S/N' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="gradient">
        <tr>
            <td class="g1">&nbsp;</td>
            <td class="g2">&nbsp;</td>
            <td class="g3">&nbsp;</td>
            <td class="g4">&nbsp;</td>
        </tr>
    </table>

    <div class="company-strip">
        @if($has($company, 'nit'))<strong>NIT</strong> {{ $company['nit'] }}@endif
        @if($has($company, 'address'))<span class="sep">/</span>{{ $company['address'] }}@endif
        @if($has($company, 'city'))<span class="sep">/</span>{{ $company['city'] }}@endif
        @if($has($company, 'phone'))<span class="sep">/</span>Tel. {{ $company['phone'] }}@endif
        @if($has($company, 'email'))<span class="sep">/</span>{{ $company['email'] }}@endif
        @if($has($company, 'legalRep') || $has($company, 'taxRegime') || $has($company, 'ciiu'))
            <br>
            @if($has($company, 'legalRep'))<strong>Rep. Legal</strong> {{ $company['legalRep'] }}@endif
            @if($has($company, 'taxRegime'))@if($has($company, 'legalRep'))<span class="sep">/</span>@endif<strong>Regimen</strong> {{ $company['taxRegime'] }}@endif
            @if($has($company, 'ciiu'))@if($has($company, 'legalRep') || $has($company, 'taxRegime'))<span class="sep">/</span>@endif<strong>CIIU</strong> {{ $company['ciiu'] }}@endif
        @endif
    </div>

    {{-- =============== DATOS DEL DESPACHO =============== --}}
    <div class="section-head">DATOS DEL DESPACHO</div>

    <table class="info">
        <tr>
            <td class="lbl">FECHA</td>
            <td class="val"><strong>{{ $fmtFecha($doc['fecha'] ?? null) }}</strong></td>
            <td class="lbl">TIPO DE SALIDA</td>
            <td class="val">{{ $doc['tipo'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">RESPONSABLE</td>
            <td class="val" colspan="3">{{ $doc['responsable'] ?? '-' }}</td>
        </tr>
    </table>

    <table class="band" style="margin-top: 8px;">
        <tr>
            <td class="route">
                <span class="rlabel">ORIGEN</span>&nbsp;&nbsp;{{ $doc['origen'] ?? '-' }}
                <span class="arrow">&#8594;</span>
                <span class="rlabel">DESTINO</span>&nbsp;&nbsp;{{ $doc['destino'] ?? '-' }}
            </td>
        </tr>
    </table>

    {{-- =============== PRODUCTOS =============== --}}
    <div class="section-head">PRODUCTOS DESPACHADOS</div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 13%;">CODIGO</th>
                <th style="width: 29%;">PRODUCTO</th>
                <th style="width: 15%;">MARCA</th>
                <th style="width: 10%;" class="r">CANT.</th>
                {{-- 14%: la unidad se imprime con nombre completo ("Kilogramo", "Centimetro"). --}}
                <th style="width: 14%;">UNIDAD</th>
                <th style="width: 14%;">LOTE</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $i => $item)
                <tr class="{{ $i % 2 === 1 ? 'zebra' : '' }}">
                    <td class="c muted">{{ $i + 1 }}</td>
                    <td class="code">{{ $item['codigo'] ?? '-' }}</td>
                    <td>{{ $item['producto'] ?? '-' }}</td>
                    <td>{{ $item['marca'] ?? '-' }}</td>
                    <td class="r qty">{{ $fmtCant($item['cantidad'] ?? null) }}</td>
                    <td class="unit">{{ $item['unidad'] ?? '-' }}</td>
                    <td class="code">{{ $item['lote'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="c muted" style="padding: 16px;">
                        No hay productos registrados en este despacho.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="items-foot">
        TOTAL DE ITEMS DESPACHADOS: {{ count($items) }}
    </div>

    {{-- =============== LOTES DE CULTIVO =============== --}}
    @if(count($lotes) > 0)
        <div class="section-head">LOTES DE CULTIVO</div>
        <div>
            @foreach($lotes as $lote)
                <span class="chip">{{ $lote }}</span>
            @endforeach
        </div>
    @endif

    {{-- =============== OBSERVACIONES =============== --}}
    @if($has($doc, 'observaciones'))
        <div class="section-head">OBSERVACIONES</div>
        <div class="note">{{ $doc['observaciones'] }}</div>
    @endif

    {{-- =============== FIRMAS =============== --}}
    <div class="sign-wrap">
        <div class="sw-title">CONSTANCIA DE ENTREGA Y RECIBO</div>
        <table class="signs">
            <tr>
                <td>
                    <div class="sign-role">ENTREGA</div>
                    <div class="sign-line"></div>
                    <div class="sign-cap">FIRMA</div>
                    <div class="sign-line">@if($has($firmas, 'entrega'))<span class="sign-prefill">{{ $firmas['entrega'] }}</span>@endif</div>
                    <div class="sign-cap">NOMBRE COMPLETO</div>
                    <div class="sign-line"></div>
                    <div class="sign-cap">CEDULA</div>
                </td>
                <td class="divider"></td>
                <td>
                    <div class="sign-role">RECIBE</div>
                    <div class="sign-line"></div>
                    <div class="sign-cap">FIRMA</div>
                    <div class="sign-line">@if($has($firmas, 'recibe'))<span class="sign-prefill">{{ $firmas['recibe'] }}</span>@endif</div>
                    <div class="sign-cap">NOMBRE COMPLETO</div>
                    <div class="sign-line"></div>
                    <div class="sign-cap">CEDULA</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
