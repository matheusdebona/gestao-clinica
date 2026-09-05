<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Orçamento — {{ $payload['branding']['display_name'] }}</title>
    <style>
        :root {
            --primary: {{ $payload['branding']['primary_color'] }};
            --secondary: {{ $payload['branding']['secondary_color'] }};
        }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .brand img {
            max-height: 64px;
            max-width: 140px;
        }
        .brand-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--secondary);
            margin: 0;
        }
        .doc-meta {
            text-align: right;
            color: #4b5563;
        }
        .doc-meta .title {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section h2 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--secondary);
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 6px;
            margin: 0 0 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px 6px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
            color: var(--secondary);
            font-size: 11px;
            text-transform: uppercase;
        }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .discount {
            color: #b45309;
            font-weight: 600;
        }
        .totals {
            margin-top: 16px;
            width: 280px;
            margin-left: auto;
        }
        .totals tr td {
            border: none;
            padding: 4px 0;
        }
        .totals .grand td {
            font-size: 14px;
            font-weight: 700;
            color: var(--primary);
            padding-top: 8px;
            border-top: 2px solid var(--primary);
        }
        .notes {
            white-space: pre-wrap;
            background: #f9fafb;
            padding: 10px;
            border-left: 3px solid var(--primary);
        }
        .footer {
            margin-top: 32px;
            font-size: 10px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
    </style>
</head>
<body>
@php
    $branding = $payload['branding'];
    $clinic = $payload['clinic'];
    $client = $payload['client'];
    $budget = $payload['budget'];
@endphp

<div class="header">
    <div class="brand">
        @if (!empty($logoDataUri))
            <img src="{{ $logoDataUri }}" alt="Logo">
        @endif
        <div>
            <p class="brand-name">{{ $branding['display_name'] }}</p>
            @if (!empty($clinic['document']))
                <div>{{ $clinic['document'] }}</div>
            @endif
            @if (!empty($clinic['phone']) || !empty($clinic['email']))
                <div>{{ collect([$clinic['phone'] ?? null, $clinic['email'] ?? null])->filter()->implode(' · ') }}</div>
            @endif
        </div>
    </div>
    <div class="doc-meta">
        <div class="title">Orçamento</div>
        <div>Versão {{ $budget['version'] }} · {{ strtoupper($budget['status']) }}</div>
        @if (!empty($budget['valid_until']))
            <div>Válido até {{ \Illuminate\Support\Carbon::parse($budget['valid_until'])->format('d/m/Y') }}</div>
        @endif
        <div>Gerado em {{ \Illuminate\Support\Carbon::parse($payload['generated_at'])->format('d/m/Y H:i') }}</div>
    </div>
</div>

<div class="section">
    <h2>Cliente</h2>
    <div><strong>{{ $client['name'] }}</strong></div>
    @if (!empty($client['whatsapp']))
        <div>WhatsApp: {{ $client['whatsapp'] }}</div>
    @endif
</div>

<div class="section">
    <h2>Itens</h2>
    <table>
        <thead>
            <tr>
                <th>Produto</th>
                <th class="num">Qtd</th>
                <th class="num">Preço cheio (un.)</th>
                <th class="num">Preço ofertado (un.)</th>
                <th class="num">Total cheio</th>
                <th class="num">Total ofertado</th>
                <th class="num">Desconto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payload['items'] as $item)
                <tr>
                    <td>{{ $item['product_name'] }}</td>
                    <td class="num">{{ $item['quantity'] }}</td>
                    <td class="num">R$ {{ number_format((float) $item['list_unit_price'], 2, ',', '.') }}</td>
                    <td class="num">R$ {{ number_format((float) $item['unit_price'], 2, ',', '.') }}</td>
                    <td class="num">R$ {{ number_format((float) $item['list_line_total'], 2, ',', '.') }}</td>
                    <td class="num">R$ {{ number_format((float) $item['line_total'], 2, ',', '.') }}</td>
                    <td class="num discount">
                        R$ {{ number_format((float) $item['discount_amount'], 2, ',', '.') }}
                        ({{ number_format((float) $item['discount_percent'], 1, ',', '.') }}%)
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Total cheio</td>
            <td class="num">R$ {{ number_format((float) $budget['list_total'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Desconto total</td>
            <td class="num discount">R$ {{ number_format((float) $budget['discount_total'], 2, ',', '.') }}</td>
        </tr>
        <tr class="grand">
            <td>Total ofertado</td>
            <td class="num">R$ {{ number_format((float) $budget['expected_amount'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Mínimo</td>
            <td class="num">R$ {{ number_format((float) $budget['min_amount'], 2, ',', '.') }}</td>
        </tr>
    </table>
</div>

@if (!empty($budget['notes']))
    <div class="section">
        <h2>Observações</h2>
        <div class="notes">{{ $budget['notes'] }}</div>
    </div>
@endif

<div class="footer">
    Documento gerado automaticamente. Preço cheio = lista de catálogo no momento do orçamento; preço ofertado = proposta comercial.
    @if (!empty($clinic['address']))
        <div>{{ $clinic['address'] }}</div>
    @endif
</div>
</body>
</html>
