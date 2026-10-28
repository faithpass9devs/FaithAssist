<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gafete personal</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; }
        .page {
            width: 100%;
            min-height: 100vh;
            padding: 42px 46px;
            color: {{ $bodyTextColor }};
            background-image:
                linear-gradient(rgba(255,255,255,0.88), rgba(255,255,255,0.88))
                @if($backgroundPath), url('{{ $backgroundPath }}') @endif;
            background-size: cover;
            background-position: center;
        }
        .header { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
        .logo { width: 64px; height: 64px; object-fit: contain; }
        .title-box {
            background: {{ $accentColor }};
            color: #fff;
            font-size: 22px;
            font-weight: 700;
            padding: 10px 16px;
            border-radius: 10px;
        }
        .content { display: table; width: 100%; }
        .left, .right { display: table-cell; vertical-align: top; }
        .left { width: 60%; padding-right: 18px; }
        .right { width: 40%; text-align: center; }
        .name { font-size: 28px; font-weight: 800; line-height: 1.2; margin-bottom: 14px; }
        .meta { font-size: 14px; line-height: 1.65; }
        .meta strong { display: inline-block; min-width: 120px; }
        .qr-wrap {
            border: 2px solid {{ $qrBorderColor }};
            border-radius: 14px;
            padding: 10px;
            display: inline-block;
            background: #fff;
        }
        .qr-img { width: 190px; height: 190px; }
        .code {
            margin-top: 12px;
            display: inline-block;
            background: {{ $chipBgColor }};
            color: {{ $chipTextColor }};
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .04em;
        }
    </style>
</head>
<body>
<section class="page">
    <header class="header">
        @if($badgeLogoPath)
            <img class="logo" src="{{ $badgeLogoPath }}" alt="Logo">
        @endif
        <div class="title-box">{{ $badgeTitleText }}</div>
    </header>

    <section class="content">
        <article class="left">
            <h1 class="name">{{ $staffName }}</h1>

            <div class="meta">
                <div><strong>Tipo de personal:</strong> {{ $staffTypeName }}</div>
                <div><strong>Parroquia:</strong> {{ $churchName }}</div>
                <div><strong>Comunidad:</strong> {{ $communityName }}</div>
                <div><strong>Teléfono:</strong> {{ $phone ?: 'N/D' }}</div>
                <div><strong>Nacimiento:</strong> {{ $birthdate ?: 'N/D' }}</div>
            </div>
        </article>

        <aside class="right">
            @if($qrImageUrl)
                <div class="qr-wrap">
                    <img class="qr-img" src="{{ $qrImageUrl }}" alt="QR">
                </div>
            @endif
            <div class="code">{{ $staffCode }}</div>
        </aside>
    </section>
</section>
</body>
</html>
