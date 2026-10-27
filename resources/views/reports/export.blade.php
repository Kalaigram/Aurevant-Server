<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        @page { margin: 12mm 10mm; }
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #1f2937;
            font-size: 9px;
            line-height: 1.4;
            margin: 0;
        }
        .header {
            border-bottom: 2px solid #0f5132;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .company {
            color: #0f5132;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.2px;
        }
        h1 {
            font-size: 15px;
            margin: 4px 0 2px;
        }
        .meta {
            color: #4b5563;
            font-size: 9px;
        }
        h2 {
            color: #0f5132;
            font-size: 10px;
            margin: 14px 0 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary td {
            border-bottom: 1px solid #e5e7eb;
            padding: 4px 6px;
        }
        .summary td:first-child {
            color: #4b5563;
        }
        .summary td:last-child {
            text-align: right;
            font-weight: bold;
            font-variant-numeric: tabular-nums;
        }
        .data th {
            background: #0f5132;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            padding: 5px 4px;
            border: 1px solid #0f5132;
        }
        .data td {
            padding: 4px;
            border: 1px solid #d1d5db;
            vertical-align: top;
        }
        .data tbody tr:nth-child(even) td {
            background: #f6f8f7;
        }
        .data .total td {
            font-weight: bold;
            background: #e8f3ee;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .empty {
            color: #6b7280;
            text-align: center;
            padding: 8px;
        }
        .footer {
            margin-top: 16px;
            padding-top: 6px;
            border-top: 1px solid #d1d5db;
            color: #6b7280;
            font-size: 8px;
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $format = static function (string $type, int|float|string $value): string {
            if ($type === 'money') {
                return 'Rp ' . number_format((float) $value, 0, ',', '.');
            }

            if ($type === 'number') {
                return number_format((float) $value, 0, ',', '.');
            }

            return (string) $value;
        };
    @endphp

    <div class="header">
        <div class="company">{{ $companyName }}</div>
        <h1>{{ $document->title }}</h1>
        <div class="meta">
            {{ $document->periodLabel }}
            @if ($document->branchName)
                &middot; Cabang: {{ $document->branchName }}
            @endif
        </div>
    </div>

    <table class="summary">
        <tbody>
            @foreach ($document->summary as $item)
                <tr>
                    <td>{{ $item['label'] }}</td>
                    <td>{{ $format($item['type'], $item['value']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach ($document->sections as $section)
        <h2>{{ $section['title'] }}</h2>
        <table class="data">
            <thead>
                <tr>
                    @foreach ($section['headings'] as $index => $heading)
                        <th class="{{ ($section['types'][$index] ?? 'text') === 'text' ? 'left' : 'right' }}">
                            {{ $heading }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    @php
                        $isTotal = is_string($row[0] ?? null)
                            && stripos((string) $row[0], 'total') === 0;
                    @endphp
                    <tr class="{{ $isTotal ? 'total' : '' }}">
                        @foreach ($row as $index => $value)
                            @php
                                $type = $section['types'][$index] ?? 'text';
                            @endphp
                            <td class="{{ $type === 'text' ? '' : 'right' }}">
                                {{ $format($type, $value) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($section['headings']) }}" class="empty">
                            Belum ada data.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <div class="footer">Dicetak {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
