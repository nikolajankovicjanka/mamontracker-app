<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1e293b;
        }

        .header {
            margin-bottom: 20px;
        }

        .title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .meta {
            font-size: 11px;
            color: #64748b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }

        tbody td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            vertical-align: top;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }
    </style>
</head>
<body>
<div class="header">
    <div class="title">{{ $title }}</div>
    <div class="meta">Tenant: {{ $tenantName }}</div>
    <div class="meta">Generated at: {{ $generatedAt }}</div>
</div>

<table>
    <thead>
    <tr>
        @foreach ($columns as $column)
            <th>{{ $column['label'] }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @forelse ($rows as $row)
        <tr>
            @foreach ($columns as $column)
                <td>{{ $row[$column['key']] ?? '—' }}</td>
            @endforeach
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($columns) }}">No data available.</td>
        </tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
