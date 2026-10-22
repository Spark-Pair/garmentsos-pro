@php
    $customerName = trim(($customer?->customer_name ?? '-') . ' | ' . ($customer?->city?->title ?? '-'), ' |');
    $totalQuantity = $lines->sum('quantity');
    $totalAmount = $lines->sum('amount');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Return {{ $returnNo }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; margin: 0; padding: 24px; }
        .document { max-width: 820px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #111; padding-bottom: 12px; }
        .header h1 { margin: 0 0 6px; font-size: 24px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; margin: 18px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 8px; text-align: left; }
        th { background: #eee; }
        .number { text-align: right; }
        .totals { margin-top: 14px; margin-left: auto; width: 280px; }
        .totals div { display: flex; justify-content: space-between; padding: 5px 0; }
        @media print { body { padding: 0; } .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="document">
        <div class="header">
            <div>
                <h1>{{ $branding['name'] ?? $client_company->name }}</h1>
                @if (!empty($branding['address'])) <div>{{ $branding['address'] }}</div> @endif
                @if (!empty($branding['phone'])) <div>{{ $branding['phone'] }}</div> @endif
            </div>
            <div><h1>Sales Return</h1><div><strong>{{ $returnNo }}</strong></div></div>
        </div>
        <div class="meta">
            <div><strong>Customer:</strong> {{ $customerName }}</div>
            <div><strong>Date:</strong> {{ optional($lines->first()->date)->format('d-M-Y') }}</div>
            <div><strong>Type:</strong> {{ $lines->first()->type === 'adjustment' ? 'Adjustment' : 'Return' }}</div>
            <div><strong>Invoice(s):</strong> {{ $lines->pluck('invoice.invoice_no')->filter()->unique()->implode(', ') }}</div>
        </div>
        <table>
            <thead><tr><th>#</th><th>Article</th><th>Description</th><th>Invoice</th><th class="number">Quantity</th><th class="number">Rate</th><th class="number">Amount</th></tr></thead>
            <tbody>
                @foreach ($lines as $index => $line)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $line->article?->article_no ?? '-' }}</td>
                        <td>{{ $line->article?->description ?? '-' }}</td>
                        <td>{{ $line->invoice?->invoice_no ?? '-' }}</td>
                        <td class="number">{{ number_format($line->quantity) }}</td>
                        <td class="number">{{ number_format((float) ($line->article?->sales_rate ?? 0), 1) }}</td>
                        <td class="number">{{ number_format((float) $line->amount, 1) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="totals">
            <div><strong>Total Quantity</strong><span>{{ number_format($totalQuantity) }} Pcs</span></div>
            <div><strong>Total Amount</strong><span>Rs. {{ number_format($totalAmount, 1) }}</span></div>
        </div>
    </div>
</body>
</html>
