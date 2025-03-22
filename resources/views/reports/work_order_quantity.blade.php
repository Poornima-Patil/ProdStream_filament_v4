<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Work Order Quantity Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .report-title {
            font-size: 20px;
            margin-bottom: 20px;
        }
        .info-section {
            margin-bottom: 30px;
        }
        .info-row {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .quantities-table {
            margin-top: 30px;
        }
        .quantities-table th {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">ProdStream</div>
        <div class="report-title">Work Order Quantity Report</div>
    </div>

    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Work Order ID:</span> {{ $workOrderQuantity->workOrder->unique_id }}
        </div>
        <div class="info-row">
            <span class="info-label">Part Number:</span> {{ $workOrderQuantity->workOrder->bom->purchaseOrder->partNumber->partnumber }}
        </div>
        <div class="info-row">
            <span class="info-label">Revision:</span> {{ $workOrderQuantity->workOrder->bom->purchaseOrder->partNumber->revision }}
        </div>
        <div class="info-row">
            <span class="info-label">Date:</span> {{ $workOrderQuantity->created_at->format('Y-m-d H:i:s') }}
        </div>
    </div>

    <table class="quantities-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Quantity</th>
                <th>Scrapped Reason</th>
            </tr>
        </thead>
        <tbody>
            @php
                $latestCreatedAt = $workOrderQuantity->workOrder->quantities()
                    ->latest('created_at')
                    ->first()
                    ->created_at;

                $quantities = $workOrderQuantity->workOrder->quantities()
                    ->where('created_at', '>=', $latestCreatedAt->subSeconds(5))
                    ->orderBy('type')
                    ->get();
            @endphp
            @foreach($quantities as $quantity)
                <tr>
                    <td>{{ ucfirst($quantity->type) }}</td>
                    <td>{{ $quantity->quantity }}</td>
                    <td>{{ $quantity->type === 'scrapped' ? ($quantity->reason->description ?? 'N/A') : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th>Machine</th>
                <th>Operator</th>
                <th>Material Batch</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $workOrderQuantity->workOrder->machine->name }}</td>
                <td>{{ $workOrderQuantity->workOrder->operator->user->first_name }}</td>
                <td>{{ $workOrderQuantity->workOrder->material_batch }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Generated on: {{ now()->format('Y-m-d H:i:s') }}</p>
        <p>This is a computer-generated report and does not require a signature.</p>
    </div>
</body>
</html> 