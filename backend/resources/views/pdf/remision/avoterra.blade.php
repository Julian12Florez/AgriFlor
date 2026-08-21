{{--
    REMISION DE DESPACHO - Plantilla "AVOTERRA" (moderno y aireado)
    Identidad: base grafito #0F172A con acento verde #22C55E, encabezado asimetrico
    (columna de color a la izquierda + tarjetas de datos a la derecha), tabla sin
    bordes verticales, cifras en tipografia monoespaciada grande, firmas en tarjetas.
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

    $fiscales = [];
    if ($has($company, 'legalRep'))  { $fiscales[] = 'Rep. legal: ' . $company['legalRep']; }
    if ($has($company, 'taxRegime')) { $fiscales[] = 'Regimen: ' . $company['taxRegime']; }
    if ($has($company, 'ciiu'))      { $fiscales[] = 'CIIU: ' . $company['ciiu']; }
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
            font-size: 9px;
            line-height: 1.55;
            color: #0F172A;
            margin: 1.15cm 1.15cm 1.9cm 1.15cm;
        }

        /* ---------- Encabezado asimetrico ---------- */
        table.hero { width: 100%; border-collapse: collapse; }
        table.hero td { border: none; vertical-align: top; }

        .hero-dark {
            width: 33%;
            background: #0F172A;
            padding: 20px 16px 22px 16px;
            color: #FFFFFF;
        }

        .hero-accent {
            width: 34px;
            height: 4px;
            background: #22C55E;
            font-size: 1px;
            line-height: 4px;
            margin-bottom: 14px;
        }

        .hero-brand {
            font-size: 21px;
            font-weight: bold;
            color: #FFFFFF;
            line-height: 1.18;
            letter-spacing: -0.2px;
        }

        .hero-kicker {
            font-size: 6.8px;
            letter-spacing: 3px;
            color: #22C55E;
            padding-top: 12px;
        }

        .hero-meta {
            font-size: 7.4px;
            color: #94A3B8;
            line-height: 1.7;
            padding-top: 10px;
        }

        .hero-right { padding: 4px 0 0 20px; }

        .eyebrow {
            font-size: 6.8px;
            letter-spacing: 3.4px;
            color: #64748B;
        }

        .hero-title {
            font-size: 27px;
            font-weight: bold;
            color: #0F172A;
            line-height: 1.05;
            letter-spacing: -0.8px;
            padding: 2px 0 1px 0;
        }

        .hero-title-sub {
            font-size: 12.5px;
            letter-spacing: 5.5px;
            color: #22C55E;
            padding-bottom: 14px;
        }

        /* ---------- Tarjetas ---------- */
        table.cards { width: 100%; border-collapse: collapse; }
        table.cards td { border: none; vertical-align: top; }
        table.cards td.gap { width: 10px; }

        .card {
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 9px 11px 10px 11px;
            background: #FFFFFF;
        }
        .card-dark { background: #0F172A; border-color: #0F172A; }
        .card-lbl {
            font-size: 6.6px;
            letter-spacing: 2.2px;
            color: #94A3B8;
            padding-bottom: 3px;
        }
        .card-val {
            font-size: 11.5px;
            color: #0F172A;
            line-height: 1.3;
        }
        .card-dark .card-val { color: #FFFFFF; }
        .card-val-mono {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 13px;
            font-weight: bold;
            color: #22C55E;
            letter-spacing: -0.4px;
        }

        /* ---------- Ruta ---------- */
        .route {
            margin: 16px 0 4px 0;
            border-top: 1px solid #E2E8F0;
            border-bottom: 1px solid #E2E8F0;
            padding: 13px 0 14px 0;
        }
        table.route-t { width: 100%; border-collapse: collapse; }
        table.route-t td { border: none; vertical-align: middle; }
        .route-lbl { font-size: 6.6px; letter-spacing: 2.6px; color: #94A3B8; }
        .route-val { font-size: 14px; color: #0F172A; line-height: 1.25; }
        .route-arrow {
            width: 60px;
            text-align: center;
            font-size: 21px;
            color: #22C55E;
        }

        /* ---------- Bloques ---------- */
        .block-lbl {
            font-size: 6.8px;
            letter-spacing: 3.2px;
            color: #64748B;
            margin: 22px 0 9px 0;
        }

        /* ---------- Tabla sin bordes verticales ---------- */
        table.items { width: 100%; border-collapse: collapse; table-layout: fixed; }

        table.items thead th {
            font-size: 6.6px;
            letter-spacing: 2px;
            color: #64748B;
            font-weight: normal;
            text-align: left;
            padding: 0 8px 8px 8px;
            border-bottom: 1.5px solid #0F172A;
        }

        table.items tbody td {
            padding: 11px 8px;
            border-bottom: 1px solid #EEF2F6;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .t-idx { font-family: "DejaVu Sans Mono", monospace; font-size: 8px; color: #CBD5E1; }
        .t-code { font-family: "DejaVu Sans Mono", monospace; font-size: 8.2px; color: #64748B; }
        .t-prod { font-size: 10px; color: #0F172A; line-height: 1.3; }
        .t-brand { font-size: 8.4px; color: #64748B; }
        .t-qty {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 14px;
            font-weight: bold;
            color: #0F172A;
            text-align: right;
            letter-spacing: -0.5px;
        }
        .t-unit { font-size: 7.4px; letter-spacing: 0.5px; color: #16A34A; }
        .t-lote { font-family: "DejaVu Sans Mono", monospace; font-size: 8.2px; color: #64748B; }

        .items-total {
            padding: 12px 8px 0 8px;
            border-top: 1.5px solid #0F172A;
            font-size: 7px;
            letter-spacing: 2.4px;
            color: #64748B;
        }
        .items-total .big {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 13px;
            font-weight: bold;
            color: #0F172A;
            letter-spacing: 0;
        }

        /* ---------- Lotes / observaciones ---------- */
        .pill {
            display: inline-block;
            background: #ECFDF5;
            color: #15803D;
            border-radius: 9px;
            padding: 4px 11px;
            margin: 0 5px 5px 0;
            font-size: 8.4px;
        }

        .obs {
            background: #F8FAFC;
            border-radius: 4px;
            border-left: 3px solid #22C55E;
            padding: 11px 14px;
            font-size: 9px;
            color: #334155;
            line-height: 1.65;
        }

        /* ---------- Firmas como tarjetas sueltas ---------- */
        .sign-block { page-break-inside: avoid; }
        .sign-card {
            border: 1px solid #E2E8F0;
            border-top: 3px solid #22C55E;
            border-radius: 4px;
            padding: 13px 15px 15px 15px;
        }
        .sign-role {
            font-size: 7px;
            letter-spacing: 3.2px;
            color: #22C55E;
            padding-bottom: 2px;
        }
        .sign-space { height: 26px; font-size: 1px; line-height: 26px; }
        .sign-who { font-size: 10px; color: #0F172A; }
        .sign-line { border-bottom: 1px solid #CBD5E1; height: 16px; }
        .sign-cap { font-size: 6.6px; letter-spacing: 2px; color: #94A3B8; padding: 3px 0 11px 0; }

        /* ---------- Pie ---------- */
        .footer {
            position: fixed;
            left: 1.15cm;
            right: 1.15cm;
            bottom: 0.8cm;
            font-size: 7px;
            letter-spacing: 0.8px;
            color: #94A3B8;
            border-top: 1px solid #E2E8F0;
            padding-top: 6px;
        }
        .footer .fr { float: right; font-family: "DejaVu Sans Mono", monospace; color: #64748B; }
        .footer .dot { color: #22C55E; }
        /* DomPDF resuelve counter(page); counter(pages) no esta soportado (devuelve 0). */
        .pagenum:after { content: "PAG. " counter(page); }
    </style>
</head>
<body>

    {{-- =============== PIE FIJO =============== --}}
    <div class="footer">
        <span class="fr"><span class="pagenum"></span></span>
        <span class="dot">&#9679;</span>
        {{ $company['name'] ?? '' }} &nbsp; / &nbsp; impreso {{ now()->format('d/m/Y H:i') }}
        &nbsp; / &nbsp; documento de despacho sin valor comercial
    </div>

    {{-- =============== ENCABEZADO ASIMETRICO =============== --}}
    <table class="hero">
        <tr>
            <td class="hero-dark">
                <div class="hero-accent">&nbsp;</div>
                @if(!empty($company['logoDataUri']))
                    <img src="{{ $company['logoDataUri'] }}" style="height: 44px;" alt="">
                    <div class="hero-kicker">{{ mb_strtoupper($company['name'] ?? '') }}</div>
                @else
                    <div class="hero-brand">{{ $company['name'] ?? '' }}</div>
                    <div class="hero-kicker">DESPACHO CONTROLADO</div>
                @endif
                <div class="hero-meta">
                    @if($has($company, 'nit'))NIT {{ $company['nit'] }}<br>@endif
                    @if($has($company, 'address')){{ $company['address'] }}<br>@endif
                    @if($has($company, 'city')){{ $company['city'] }}<br>@endif
                    @if($has($company, 'phone')){{ $company['phone'] }}<br>@endif
                    @if($has($company, 'email')){{ $company['email'] }}@endif
                </div>
            </td>
            <td class="hero-right">
                <div class="eyebrow">REMISION</div>
                <div class="hero-title">DESPACHO</div>
                <div class="hero-title-sub">DE MERCANCIA</div>

                <table class="cards">
                    <tr>
                        <td style="width: 46%;">
                            <div class="card card-dark">
                                <div class="card-lbl">DOCUMENTO No.</div>
                                <div class="card-val card-val-mono">{{ $doc['numero'] ?? 'S/N' }}</div>
                            </div>
                        </td>
                        <td class="gap"></td>
                        <td>
                            <div class="card">
                                <div class="card-lbl">FECHA</div>
                                <div class="card-val">{{ $fmtFecha($doc['fecha'] ?? null) }}</div>
                            </div>
                        </td>
                    </tr>
                </table>

                <table class="cards" style="margin-top: 9px;">
                    <tr>
                        <td style="width: 46%;">
                            <div class="card">
                                <div class="card-lbl">TIPO DE SALIDA</div>
                                <div class="card-val" style="font-size: 9.5px;">{{ $doc['tipo'] ?? '-' }}</div>
                            </div>
                        </td>
                        <td class="gap"></td>
                        <td>
                            <div class="card">
                                <div class="card-lbl">RESPONSABLE</div>
                                <div class="card-val" style="font-size: 9.5px;">{{ $doc['responsable'] ?? '-' }}</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if(count($fiscales) > 0)
        <div style="font-size: 7px; color: #94A3B8; letter-spacing: 1.2px; padding-top: 7px;">
            {{ implode('   /   ', $fiscales) }}
        </div>
    @endif

    {{-- =============== RUTA =============== --}}
    <div class="route">
        <table class="route-t">
            <tr>
                <td style="width: 44%;">
                    <div class="route-lbl">ORIGEN</div>
                    <div class="route-val">{{ $doc['origen'] ?? '-' }}</div>
                </td>
                <td class="route-arrow">&#8594;</td>
                <td>
                    <div class="route-lbl">DESTINO</div>
                    <div class="route-val">{{ $doc['destino'] ?? '-' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- =============== PRODUCTOS =============== --}}
    <div class="block-lbl">DETALLE DE PRODUCTOS</div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%;">&nbsp;</th>
                <th style="width: 12%;">CODIGO</th>
                <th style="width: 31%;">PRODUCTO</th>
                <th style="width: 14%;">MARCA</th>
                <th style="width: 12%; text-align: right;">CANTIDAD</th>
                <th style="width: 14%;">UNIDAD</th>
                <th style="width: 13%;">LOTE</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $i => $item)
                <tr>
                    <td class="t-idx">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="t-code">{{ $item['codigo'] ?? '-' }}</td>
                    <td class="t-prod">{{ $item['producto'] ?? '-' }}</td>
                    <td class="t-brand">{{ $item['marca'] ?? '-' }}</td>
                    <td class="t-qty">{{ $fmtCant($item['cantidad'] ?? null) }}</td>
                    <td class="t-unit">{{ mb_strtoupper($item['unidad'] ?? '-') }}</td>
                    <td class="t-lote">{{ $item['lote'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="padding: 22px 8px; color: #94A3B8; font-size: 9px;">
                        Sin productos registrados en este despacho.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="items-total">
        ITEMS &nbsp; <span class="big">{{ str_pad((string) count($items), 2, '0', STR_PAD_LEFT) }}</span>
    </div>

    {{-- =============== LOTES DE CULTIVO =============== --}}
    @if(count($lotes) > 0)
        <div class="block-lbl">LOTES DE CULTIVO</div>
        <div>
            @foreach($lotes as $lote)
                <span class="pill">{{ $lote }}</span>
            @endforeach
        </div>
    @endif

    {{-- =============== OBSERVACIONES =============== --}}
    @if($has($doc, 'observaciones'))
        <div class="block-lbl">OBSERVACIONES</div>
        <div class="obs">{{ $doc['observaciones'] }}</div>
    @endif

    {{-- =============== FIRMAS (dos tarjetas sueltas) =============== --}}
    <div class="sign-block">
    <div class="block-lbl">FIRMAS</div>
    <table class="cards">
        <tr>
            <td style="width: 48%;">
                <div class="sign-card">
                    <div class="sign-role">ENTREGA</div>
                    <div class="sign-space">&nbsp;</div>
                    <div class="sign-line"></div>
                    <div class="sign-cap">FIRMA</div>
                    <div class="sign-line">@if($has($firmas, 'entrega'))<span class="sign-who">{{ $firmas['entrega'] }}</span>@endif</div>
                    <div class="sign-cap">NOMBRE</div>
                    <div class="sign-line"></div>
                    <div class="sign-cap">CEDULA</div>
                </div>
            </td>
            <td class="gap" style="width: 4%;"></td>
            <td>
                <div class="sign-card">
                    <div class="sign-role">RECIBE</div>
                    <div class="sign-space">&nbsp;</div>
                    <div class="sign-line"></div>
                    <div class="sign-cap">FIRMA</div>
                    <div class="sign-line">@if($has($firmas, 'recibe'))<span class="sign-who">{{ $firmas['recibe'] }}</span>@endif</div>
                    <div class="sign-cap">NOMBRE</div>
                    <div class="sign-line"></div>
                    <div class="sign-cap">CEDULA</div>
                </div>
            </td>
        </tr>
    </table>
    </div>

</body>
</html>
