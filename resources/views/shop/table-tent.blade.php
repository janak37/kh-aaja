<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $shop->name }} · {{ __('Table card') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        @page { size: A5 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #e7e5e4;
            color: #1c1917;
            font-family: system-ui, "Segoe UI", "Noto Sans Devanagari", "Nirmala UI", sans-serif;
        }
        .sheet {
            width: 148mm;
            height: 210mm;
            margin: 0 auto 10mm;
            padding: 0 10mm 10mm;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border-top: 5mm solid #ea580c;
        }
        .brand { display: flex; align-items: center; gap: 2.5mm; margin-top: 8mm; }
        .brand svg { width: 11mm; height: 11mm; }
        .brand-name { text-align: left; line-height: 1.1; }
        .brand-name strong { display: block; font-size: 14pt; }
        .brand-name span { font-size: 10pt; color: #78716c; }
        h1 { font-size: 26pt; line-height: 1.15; margin: 7mm 0 2mm; }
        .meta { font-size: 12pt; color: #57534e; }
        .qr {
            width: 92mm; height: 92mm; margin: 7mm 0 5mm; padding: 4mm;
            border: 1.2mm solid #fed7aa; border-radius: 6mm;
        }
        .qr svg { width: 100%; height: 100%; }
        .ne { font-size: 17pt; font-weight: 700; margin: 0; color: #c2410c; }
        .en { font-size: 13pt; margin: 1.5mm 0 0; color: #44403c; }
        .phone { font-size: 13pt; font-weight: 600; margin-top: 4mm; }
        .url { margin-top: auto; font-size: 10pt; color: #57534e; word-break: break-all; }
        .actions { text-align: center; padding: 6mm; }
        .actions button {
            font-size: 15px; padding: 10px 20px; border: 0; border-radius: 8px;
            background: #ea580c; color: #fff; cursor: pointer; font-weight: 600;
        }
        @media print {
            body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sheet { margin: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
    </div>

    <main class="sheet">
        <div class="brand">
            <x-logo-mark />
            <div class="brand-name">
                <strong>खाजा आज</strong>
                <span>Kh-aaja</span>
            </div>
        </div>

        <h1>{{ $shop->name }}</h1>
        <div class="meta">{{ $shop->categoryLabel() }} · {{ $shop->area }}</div>

        <div class="qr">{!! $qr !!}</div>

        <p class="ne">आजको मेनु हेर्न स्क्यान गर्नुहोस्</p>
        <p class="en">Scan to see what's available today</p>

        <div class="phone">{{ __('Phone') }}: {{ $shop->phone }}</div>
        <div class="url">{{ $url }}</div>
    </main>
</body>
</html>