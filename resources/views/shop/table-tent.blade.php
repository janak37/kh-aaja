<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $shop->name }} · {{ __('Table card') }}</title>
    <style>
        @page { size: A5 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #e4e4e7;
            color: #18181b;
            font-family: system-ui, "Segoe UI", "Noto Sans Devanagari", "Nirmala UI", sans-serif;
        }
        .sheet {
            width: 148mm;
            height: 210mm;
            margin: 0 auto 10mm;
            padding: 12mm 10mm;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .brand { font-size: 11pt; letter-spacing: .08em; text-transform: uppercase; color: #71717a; }
        h1 { font-size: 26pt; line-height: 1.15; margin: 4mm 0 2mm; }
        .meta { font-size: 12pt; color: #52525b; }
        .qr { width: 95mm; height: 95mm; margin: 8mm 0 5mm; }
        .qr svg { width: 100%; height: 100%; }
        .ne { font-size: 17pt; font-weight: 700; margin: 0; }
        .en { font-size: 13pt; margin: 1.5mm 0 0; color: #3f3f46; }
        .phone { font-size: 13pt; font-weight: 600; margin-top: 4mm; }
        .url { margin-top: auto; font-size: 10pt; color: #52525b; word-break: break-all; }
        .actions { text-align: center; padding: 6mm; }
        .actions button {
            font-size: 15px; padding: 10px 20px; border: 0; border-radius: 8px;
            background: #18181b; color: #fff; cursor: pointer;
        }
        @media print {
            body { background: #fff; }
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
        <div class="brand">Kh-aaja · खाजा आज</div>
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