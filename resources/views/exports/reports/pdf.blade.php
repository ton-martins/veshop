<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório Veshop</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #0f172a;
            background: #ffffff;
        }
        .wrapper { padding: 26px 28px; }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 18px;
            border-bottom: 1px solid #dbe4ee;
            padding-bottom: 14px;
        }
        .brand-cell,
        .meta-cell {
            display: table-cell;
            vertical-align: middle;
        }
        .meta-cell {
            text-align: right;
            width: 40%;
        }
        .brand-box { display: table; }
        .brand-logo,
        .brand-initials {
            display: table-cell;
            vertical-align: middle;
            width: 46px;
            height: 46px;
            border-radius: 12px;
            overflow: hidden;
            text-align: center;
        }
        .brand-logo img {
            width: 46px;
            height: 46px;
            object-fit: cover;
        }
        .brand-initials {
            background: #0b3c4a;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            line-height: 46px;
        }
        .brand-info {
            display: table-cell;
            vertical-align: middle;
            padding-left: 10px;
        }
        .brand-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        .brand-sub {
            margin: 2px 0 0;
            font-size: 10px;
            color: #64748b;
        }
        .meta-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .meta-text {
            margin: 4px 0 0;
            font-size: 10px;
            color: #64748b;
        }
        .section {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 14px;
            overflow: hidden;
        }
        .section-header {
            background: #f8fafc;
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .section-title {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }
        .section-description {
            margin: 4px 0 0;
            font-size: 10px;
            color: #64748b;
        }
        .summary {
            padding: 8px 12px 10px;
            border-bottom: 1px solid #edf2f7;
        }
        .summary-item {
            margin: 0 0 4px;
            font-size: 10px;
            color: #334155;
        }
        .summary-item strong {
            color: #0f172a;
        }
        .table-wrap { padding: 10px 12px 12px; }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 6px 7px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }
        th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 10px;
            font-weight: 700;
        }
        td {
            font-size: 10px;
            color: #1e293b;
        }
        .muted {
            color: #64748b;
            font-size: 10px;
            margin: 0;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <header class="header">
        <div class="brand-cell">
            <div class="brand-box">
                @if(!empty($identity['logo_data_uri']))
                    <div class="brand-logo">
                        <img src="{{ $identity['logo_data_uri'] }}" alt="Logo">
                    </div>
                @else
                    <div class="brand-initials">{{ $identity['initials'] ?? 'CT' }}</div>
                @endif
                <div class="brand-info">
                    <p class="brand-name">{{ $identity['name'] ?? ($contractor->brand_name ?? $contractor->name ?? 'Contratante') }}</p>
                    <p class="brand-sub">Relatório gerado pelo Veshop</p>
                </div>
            </div>
        </div>
        <div class="meta-cell">
            <p class="meta-title">Relatório Operacional</p>
            <p class="meta-text">Período: {{ $period['start'] ?? '-' }} até {{ $period['end'] ?? '-' }}</p>
            <p class="meta-text">Emitido em {{ $generated_at ?? '-' }}</p>
        </div>
    </header>

    @foreach(($sections ?? []) as $section)
        <section class="section">
            <div class="section-header">
                <p class="section-title">{{ $section['title'] ?? 'Seção' }}</p>
                @if(!empty($section['description']))
                    <p class="section-description">{{ $section['description'] }}</p>
                @endif
            </div>

            @if(!empty($section['summary']) && is_array($section['summary']))
                <div class="summary">
                    @foreach($section['summary'] as $summary)
                        <p class="summary-item">
                            <strong>{{ $summary['label'] ?? '-' }}:</strong> {{ $summary['value'] ?? '-' }}
                        </p>
                    @endforeach
                </div>
            @endif

            <div class="table-wrap">
                @php
                    $columns = is_array($section['columns'] ?? null) ? $section['columns'] : [];
                    $rows = is_array($section['rows'] ?? null) ? $section['rows'] : [];
                @endphp

                @if(count($columns) > 0 && count($rows) > 0)
                    <table>
                        <thead>
                            <tr>
                                @foreach($columns as $column)
                                    <th>{{ $column['label'] ?? '-' }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    @foreach($columns as $column)
                                        @php($key = $column['key'] ?? '')
                                        <td>{{ is_array($row) ? ($row[$key] ?? '-') : '-' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="muted">Sem dados para o período selecionado.</p>
                @endif
            </div>
        </section>
    @endforeach
</div>
</body>
</html>
