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
            margin-bottom: 20px;
        }
        .info-row {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            margin-right: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
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
        <div class="info-row">
            <span class="info-label">Material Batch:</span> {{ $workOrderQuantity->workOrder->material_batch ?? 'N/A' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Machine</th>
                <th>Operator</th>
                <th>Material Batch</th>
                <th>OK Quantity</th>
                <th>Scrapped Quantity</th>
                <th>Scrapped Reason</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $workOrderQuantity->workOrder->machine->name }}</td>
                <td>{{ $workOrderQuantity->workOrder->operator->user->first_name }} {{ $workOrderQuantity->workOrder->operator->user->last_name }}</td>
                <td>{{ $workOrderQuantity->workOrder->material_batch }}</td>
                <td>{{ $workOrderQuantity->ok_quantity }}</td>
                <td>{{ $workOrderQuantity->scrapped_quantity }}</td>
                <td>{{ $workOrderQuantity->reason->description ?? 'N/A' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Report generated on: {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html> 