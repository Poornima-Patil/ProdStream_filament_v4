<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OK Quantity Report</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { width: 100%; padding: 20px; }
        .title { text-align: center; font-size: 20px; font-weight: bold; }
        .details { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid black; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="container">
        <div class="title">OK Quantity Report</div>
        <div class="details">
           
            <p><strong>Quantity:</strong> {{ $okQuantity->quantity }}</p>
            <p><strong>Created At:</strong> {{ $okQuantity->created_at->format('Y-m-d H:i:s') }}</p>
        </div>
        <table>
            <tr>
                <th>Field</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>Work Order Uniquee ID </td>
                <td>{{ $okQuantity->workOrder->unique_id ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Report Generated On</td>
                <td>{{ now()->format('Y-m-d H:i:s') }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
