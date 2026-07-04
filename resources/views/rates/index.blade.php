<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Currency Rates</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 0.75rem; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Currency Rates</h1>

    @if($rates->isEmpty())
        <p>No rates found yet. Run the scraper first.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>Currency</th>
                    <th>Type</th>
                    <th>Buy</th>
                    <th>Sell</th>
                    <th>Scraped At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rates as $rate)
                    <tr>
                        <td>{{ $rate->organization->name ?? 'N/A' }}</td>
                        <td>{{ $rate->currency->code ?? 'N/A' }}</td>
                        <td>{{ $rate->rate_type->value ?? 'N/A' }}</td>
                        <td>{{ $rate->buy_rate }}</td>
                        <td>{{ $rate->sell_rate }}</td>
                        <td>{{ $rate->scraped_at ? $rate->scraped_at->format('Y-m-d H:i:s') : 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
