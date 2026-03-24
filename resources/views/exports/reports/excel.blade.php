<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório Veshop</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; font-size: 12px; }
        .header { margin-bottom: 12px; }
        .title { font-size: 18px; font-weight: bold; margin: 0 0 4px; }
        .meta { margin: 0; color: #475569; font-size: 11px; }
        .section { margin-top: 16px; }
        .section h2 { margin: 0 0 4px; font-size: 14px; }
        .section p { margin: 0 0 6px; color: #64748b; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background: #e2e8f0; font-weight: bold; }
        .summary-item { margin: 0 0 3px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">{{ $identity['name'] ?? ($contractor->brand_name ?? $contractor->name ?? 'Contratante') }}</p>
        <p class="meta">Relatório operacional - período {{ $period['start'] ?? '-' }} até {{ $period['end'] ?? '-' }}</p>
        <p class="meta">Gerado em {{ $generated_at ?? '-' }}</p>
    </div>

    @foreach(($sections ?? []) as $section)
        <div class="section">
            <h2>{{ $section['title'] ?? 'Seção' }}</h2>
            @if(!empty($section['description']))
                <p>{{ $section['description'] }}</p>
            @endif

            @if(!empty($section['summary']) && is_array($section['summary']))
                @foreach($section['summary'] as $summary)
                    <p class="summary-item"><strong>{{ $summary['label'] ?? '-' }}:</strong> {{ $summary['value'] ?? '-' }}</p>
                @endforeach
            @endif

            @php
                $columns = is_array($section['columns'] ?? null) ? $section['columns'] : [];
                $rows = is_array($section['rows'] ?? null) ? $section['rows'] : [];
            @endphp

            @if(count($columns) > 0)
                <table>
                    <thead>
                        <tr>
                            @foreach($columns as $column)
                                <th>{{ $column['label'] ?? '-' }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @if(count($rows) > 0)
                            @foreach($rows as $row)
                                <tr>
                                    @foreach($columns as $column)
                                        @php($key = $column['key'] ?? '')
                                        <td>{{ is_array($row) ? ($row[$key] ?? '-') : '-' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="{{ count($columns) }}">Sem dados para o período selecionado.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
</body>
</html>
