<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gafete {{ $childCode }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            background: #ffffff;
        }

        .page {
            width: 210mm;
            height: 297mm;
            box-sizing: border-box;
            page-break-after: always;
            overflow: hidden;
            background: #ffffff;
            position: relative;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .page-one,
        .page-two {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .page-one-inner {
            width: 150mm;
            max-width: 150mm;
            margin: 0 auto;
            padding-top: 8mm;
            position: relative;
            z-index: 3;
        }

        .page-two-inner {
            width: 180mm;
            margin: 0 auto;
            padding-top: 16mm;
            position: relative;
            z-index: 3;
        }

        .content-scale {
            transform: scale(1.06);
            transform-origin: top center;
        }

        .page-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            display: block;
        }

        .logo-seal {
            width: auto;
            height: 41mm;
            max-width: 43mm;
            margin: 0 auto 7mm;
            display: block;
            box-sizing: border-box;
        }

        .logo-fallback {
            width: 20mm;
            height: 20mm;
            border: 1.2mm solid {{ $accentColor ?? '#d4af37' }};
            border-radius: 50%;
            color: {{ $accentColor ?? '#d4af37' }};
            font-size: 7mm;
            font-weight: 700;
            line-height: 20mm;
            text-align: center;
        }

        .church-name {
            margin: 0;
            color: {{ $bodyTextColor ?? '#111827' }};
            font-size: 15pt;
            font-weight: 800;
            line-height: 1.15;
            text-transform: uppercase;
        }

        .location {
            margin-top: 3mm;
            color: {{ $bodyTextColor ?? '#111827' }};
            font-size: 8pt;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .badge-title {
            margin: 8mm auto 6mm;
            display: inline-block;
            padding: 3mm 6mm;
            border: 1px solid {{ $accentColor ?? '#d4af37' }};
            border-radius: 6px;
            color: {{ $accentColor ?? '#d4af37' }};
            font-size: 13pt;
            font-weight: 800;
            line-height: 1.05;
            text-transform: uppercase;
        }

        .child-name {
            margin: 0 0 4mm;
            color: {{ $bodyTextColor ?? '#111827' }};
            font-size: 14pt;
            font-weight: 800;
            text-transform: uppercase;
        }

        .child-data {
            margin: 0 auto;
            max-width: 130mm;
            color: {{ $bodyTextColor ?? '#111827' }};
            font-size: 10pt;
            line-height: 1.55;
            font-weight: 700;
        }

        .child-data div {
            margin-top: 1.5mm;
        }

        .label {
            font-weight: 800;
        }

        .qr-box {
            width: 53mm;
            height: 53mm;
            margin: 8mm auto 5mm;
            padding: 0.8mm;
            box-sizing: border-box;
            border: 1px solid {{ $qrBorderColor ?? '#d1d5db' }};
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-box img {
            width: 100%;
            height: 100%;
            display: block;
        }

        .qr-box svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .code-chip {
            display: inline-block;
            margin-top: 2mm;
            padding: 2mm 6mm;
            border-radius: 999px;
            background: {{ $chipBgColor ?? '#111827' }};
            color: {{ $chipTextColor ?? '#ffffff' }};
            font-size: 8.5pt;
            font-weight: 700;
        }

        .footer-note {
            margin: 8mm auto 0;
            max-width: 135mm;
            font-size: 8pt;
            line-height: 1.45;
            color: #374151;
        }

        .page-two-title {
            margin: 0 0 4mm;
            font-size: 15pt;
            font-weight: 800;
            text-transform: uppercase;
        }

        .page-two-subtitle {
            margin: 0 0 8mm;
            font-size: 8pt;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }

        .table-wrap {
            width: 100%;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8pt;
        }

        .attendance-table thead th {
            background: {{ $tableHeaderColor ?? '#d892ad' }};
            color: #ffffff;
            border: 1px solid {{ $tableHeaderColor ?? '#d892ad' }};
            padding: 2.5mm 1mm;
            font-weight: 800;
        }

        .attendance-table tbody td {
            border: 1px solid {{ $tableBorderColor ?? '#e5b7c6' }};
            height: 7.2mm;
            padding: 2mm 1.5mm;
        }

        .month-cell {
            width: 24%;
            font-weight: 800;
            text-align: left;
        }

        .week-cell {
            width: 15.2%;
        }

        .page-two-footer {
            margin-top: 8mm;
            font-size: 7.5pt;
            line-height: 1.45;
            color: #4b5563;
            text-align: left;
        }
    </style>
</head>
<body>
    <section class="page page-one">
        @if($pageOneBackgroundPath)
            <img class="page-bg" src="{{ $pageOneBackgroundPath }}" alt="Plantilla de fondo página 1" aria-hidden="true" />
        @endif

        <div class="page-one-inner content-scale">
            @if($badgeLogoPath)
                <img class="logo-seal" src="{{ $badgeLogoPath }}" alt="Imagen de la virgen" />
            @else
                <div class="logo-seal logo-fallback">+</div>
            @endif
            <h1 class="church-name">{{ mb_strtoupper($churchName) }}</h1>
            <div class="location">{{ mb_strtoupper(trim($municipalityName . ($stateName ? ', ' . $stateName : ''))) }}</div>

            <div class="badge-title">{{ $badgeTitleText ?? 'Gafete de catequesis' }}</div>

            <div class="child-name">{{ mb_strtoupper($childName) }}</div>

            <div class="child-data">
                <div><span class="label">PARROQUIA:</span> {{ mb_strtoupper($churchName) }}</div>
                <div><span class="label">MUNICIPIO:</span> {{ mb_strtoupper($municipalityName) }}</div>
                <div><span class="label">COMUNIDAD:</span> {{ mb_strtoupper($communityName) }}</div>
                <div><span class="label">NIVEL:</span> {{ mb_strtoupper($levelName) }}</div>
            </div>

            <div class="qr-box">
                @if(!empty($qrMatrixHtml))
                    {!! $qrMatrixHtml !!}
                @elseif($qrImageUrl)
                    <img src="{{ $qrImageUrl }}" alt="" />
                @elseif(!empty($qrSvg))
                    {!! $qrSvg !!}
                @else
                    <div style="width:100%;height:100%;display:table;text-align:center;color:#9ca3af;font-size:10pt;font-weight:700;">
                        <div style="display:table-cell;vertical-align:middle;">QR no disponible</div>
                    </div>
                @endif
            </div>

            <div class="code-chip">Código único: {{ $childCode }}</div>

            <div class="footer-note">
                {{ $footerText ?? 'Para registrar su asistencia dominical, será necesario presentar el código QR asignado al momento de su llegada. Sin este código, no podrá ser validada su participación.' }}
            </div>
        </div>
    </section>

    <section class="page page-two">
        @if($pageTwoBackgroundPath)
            <img class="page-bg" src="{{ $pageTwoBackgroundPath }}" alt="Plantilla de fondo página 2" aria-hidden="true" />
        @endif

        <div class="page-two-inner content-scale">
            @if($badgeLogoPath)
                <img class="logo-seal" src="{{ $badgeLogoPath }}" alt="Imagen de la virgen" />
            @else
                <div class="logo-seal logo-fallback">+</div>
            @endif
            <h2 class="page-two-title">{{ mb_strtoupper($churchName) }}</h2>
            <div class="page-two-subtitle">{{ mb_strtoupper(trim($municipalityName . ($stateName ? ', ' . $stateName : ''))) }}</div>

            <div class="table-wrap">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th style="width:24%">Mes</th>
                            @foreach($weekHeaders as $weekHeader)
                                <th class="week-cell">{{ $weekHeader }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attendanceMonths as $month)
                            <tr>
                                <td class="month-cell">{{ $month }}</td>
                                @foreach($weekHeaders as $weekHeader)
                                    <td class="week-cell"></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="page-two-footer">
                Nombre del niño: {{ mb_strtoupper($childName) }}<br>
                Código único: {{ $childCode }}<br>
                Parroquia de origen: {{ mb_strtoupper($churchName) }}
            </div>
        </div>
    </section>
</body>
</html>
