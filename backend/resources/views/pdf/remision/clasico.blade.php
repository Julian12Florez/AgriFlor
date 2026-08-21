{{--
    REMISION DE DESPACHO - Plantilla "CLASICO" (respaldo neutro)
    Identidad: blanco y negro, sin color ni florituras. Se usa cuando la empresa
    emisora no tiene una plantilla propia asignada.
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
            color: #000000;
            margin: 1.4cm 1.4cm 1.9cm 1.4cm;
        }

        table { border-collapse: collapse; }
        .r { text-align: right; }
        .c { text-align: center; }

        table.head { width: 100%; }
        table.head td { border: none; vertical-align: top; }

        .co-name { font-size: 15px; font-weight: bold; }
        .co-meta { font-size: 8.5px; line-height: 1.55; padding-top: 3px; }

        .doc-box { border: 1px solid #000000; padding: 7px 10px; }
        .doc-title { font-size: 11px; font-weight: bold; letter-spacing: 0.6px; }
        .doc-num { font-size: 15px; font-weight: bold; padding-top: 2px; }
        .doc-num-cap { font-size: 7px; }

        .hr { border-bottom: 1px solid #000000; height: 1px; font-size: 1px; line-height: 1px; margin: 10px 0 0 0; }

        .sec {
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 1px;
            border-bottom: 1px solid #000000;
            padding-bottom: 2px;
            margin: 15px 0 7px 0;
        }

        table.datos { width: 100%; }
        table.datos td { border: 1px solid #000000; padding: 5px 8px; vertical-align: top; }
        table.datos td.lbl { width: 17%; font-size: 7.8px; font-weight: bold; }
        table.datos td.val { width: 33%; }

        table.items { width: 100%; table-layout: fixed; }
        table.items th {
            border: 1px solid #000000;
            padding: 6px 5px;
            font-size: 7.6px;
            font-weight: bold;
            text-align: left;
        }
        table.items td {
            border: 1px solid #000000;
            padding: 5px;
            font-size: 8.8px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .box { border: 1px solid #000000; padding: 8px 10px; margin-top: 10px; font-size: 9px; }

        .sign-block { page-break-inside: avoid; }
        table.signs { width: 100%; margin-top: 34px; }
        table.signs td { border: none; width: 50%; padding: 0 20px; vertical-align: top; }
        .sign-role { font-size: 9px; font-weight: bold; letter-spacing: 1.4px; padding-bottom: 30px; }
        .sign-line { border-bottom: 1px solid #000000; height: 16px; }
        .sign-cap { font-size: 7px; padding: 2px 0 14px 0; }
        .sign-fill { font-size: 9px; }

        .footer {
            position: fixed;
            left: 1.4cm;
            right: 1.4cm;
            bottom: 0.8cm;
            border-top: 1px solid #000000;
            padding-top: 4px;
            font-size: 7.4px;
        }
        .footer .fr { float: right; }
        /* DomPDF resuelve counter(page); counter(pages) no esta soportado (devuelve 0). */
        .pagenum:after { content: counter(page); }
    </style>
</head>
<body>

    <div class="footer">
        <span class="fr">Pagina <span class="pagenum"></span></span>
        Impreso el {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; Documento de despacho, no es factura de venta.
    </div>

    <table class="head">
        <tr>
            <td style="width: 60%;">
                @if(!empty($company['logoDataUri']))
                    <img src="{{ $company['logoDataUri'] }}" style="height: 40px;" alt=""><br>
                @endif
                <div class="co-name">{{ $company['name'] ?? '' }}</div>
                <div class="co-meta">
                    @if($has($company, 'nit'))NIT: {{ $company['nit'] }}<br>@endif
                    @if($has($company, 'address')){{ $company['address'] }}<br>@endif
                    @if($has($company, 'city')){{ $company['city'] }}<br>@endif
                    @if($has($company, 'phone'))Tel: {{ $company['phone'] }}<br>@endif
                    @if($has($company, 'email'))Email: {{ $company['email'] }}<br>@endif
                    @if($has($company, 'legalRep'))Rep. legal: {{ $company['legalRep'] }}<br>@endif
                    @if($has($company, 'taxRegime'))Regimen: {{ $company['taxRegime'] }}<br>@endif
                    @if($has($company, 'ciiu'))CIIU: {{ $company['ciiu'] }}@endif
                </div>
            </td>
            <td style="width: 40%;">
                <div class="doc-box">
                    <div class="doc-title">REMISION DE DESPACHO</div>
                    <div class="doc-num-cap">No. de documento</div>
                    <div class="doc-num">{{ $doc['numero'] ?? 'S/N' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="hr">&nbsp;</div>

    <div class="sec">DATOS DEL DESPACHO</div>
    <table class="datos">
        <tr>
            <td class="lbl">FECHA</td>
            <td class="val">{{ $fmtFecha($doc['fecha'] ?? null) }}</td>
            <td class="lbl">TIPO DE SALIDA</td>
            <td class="val">{{ $doc['tipo'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">ORIGEN</td>
            <td class="val">{{ $doc['origen'] ?? '-' }}</td>
            <td class="lbl">DESTINO</td>
            <td class="val">{{ $doc['destino'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">RESPONSABLE</td>
            <td class="val" colspan="3">{{ $doc['responsable'] ?? '-' }}</td>
        </tr>
    </table>

    <div class="sec">PRODUCTOS DESPACHADOS</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 13%;">Codigo</th>
                <th style="width: 32%;">Producto</th>
                <th style="width: 15%;">Marca</th>
                <th style="width: 10%;" class="r">Cantidad</th>
                <th style="width: 11%;">Unidad</th>
                <th style="width: 14%;">Lote</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $i => $item)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $item['codigo'] ?? '-' }}</td>
                    <td>{{ $item['producto'] ?? '-' }}</td>
                    <td>{{ $item['marca'] ?? '-' }}</td>
                    <td class="r">{{ $fmtCant($item['cantidad'] ?? null) }}</td>
                    <td>{{ $item['unidad'] ?? '-' }}</td>
                    <td>{{ $item['lote'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="c" style="padding: 12px;">Sin productos registrados.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" class="r" style="font-weight: bold; font-size: 8.4px;">
                    Total de items: {{ count($items) }}
                </td>
            </tr>
        </tfoot>
    </table>

    @if(count($lotes) > 0)
        <div class="box"><strong>Lotes de cultivo:</strong> {{ implode(', ', $lotes) }}</div>
    @endif

    @if($has($doc, 'observaciones'))
        <div class="box"><strong>Observaciones:</strong> {{ $doc['observaciones'] }}</div>
    @endif

    <div class="sign-block">
    <table class="signs">
        <tr>
            <td>
                <div class="sign-role">ENTREGA</div>
                <div class="sign-line"></div>
                <div class="sign-cap">Firma</div>
                <div class="sign-line">@if($has($firmas, 'entrega'))<span class="sign-fill">{{ $firmas['entrega'] }}</span>@endif</div>
                <div class="sign-cap">Nombre</div>
                <div class="sign-line"></div>
                <div class="sign-cap">Cedula</div>
            </td>
            <td>
                <div class="sign-role">RECIBE</div>
                <div class="sign-line"></div>
                <div class="sign-cap">Firma</div>
                <div class="sign-line">@if($has($firmas, 'recibe'))<span class="sign-fill">{{ $firmas['recibe'] }}</span>@endif</div>
                <div class="sign-cap">Nombre</div>
                <div class="sign-line"></div>
                <div class="sign-cap">Cedula</div>
            </td>
        </tr>
    </table>
    </div>

</body>
</html>
