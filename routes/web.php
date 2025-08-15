<?php

use App\Filament\Admin\Widgets\AdvancedWorkOrderGantt;
use App\Models\OkQuantity;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use App\Exports\WorkOrderExport;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/', function () {
    return view('welcome');
});

// KPI Dashboard Route (Direct Access) - Updated for tenant structure
Route::get('/admin/{tenant}/kpi-dashboard', \App\Livewire\KPIDashboard::class)
    ->middleware(['auth'])
    ->name('tenant.kpi.dashboard');

// Keep the non-tenant route for testing
Route::get('/kpi-dashboard', \App\Livewire\KPIDashboard::class)
    ->middleware(['auth'])
    ->name('kpi.dashboard');

Route::get('/okquantity/download/{id}', function ($id) {
    $okQuantity = OkQuantity::findOrFail($id);

    // Get the report from media library
    $media = $okQuantity->getFirstMedia('report_pdf');
    if (! $media) {
        abort(404, 'Report not found.');
    }

    return response()->download($media->getPath(), $media->file_name);
})->name('okquantity.download');

Route::get('/work-order-quantity/{id}/download', function ($id) {
    try {
        $workOrderQuantity = \App\Models\WorkOrderQuantity::findOrFail($id);

        // Get the media item from the report_pdf collection
        $media = $workOrderQuantity->getFirstMedia('report_pdf');

        if (! $media) {
            \Illuminate\Support\Facades\Log::error("Report not found for WorkOrderQuantity ID: {$id}");
            abort(404, 'Report not found');
        }

        // Get the file path and ensure it exists
        $filePath = $media->getPath();

        if (! file_exists($filePath)) {
            \Illuminate\Support\Facades\Log::error("File not found at path: {$filePath}");
            abort(404, 'Report file not found');
        }

        // Generate a proper filename
        $filename = sprintf(
            'work_order_quantity_report_%s.pdf',
            $workOrderQuantity->id
        );

        // Return the file as a download
        return response()->download(
            $filePath,
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment',
            ]
        );
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        \Illuminate\Support\Facades\Log::error("WorkOrderQuantity not found: {$id}");
        abort(404, 'Work Order Quantity not found');
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Error downloading report: ' . $e->getMessage());
        abort(500, 'Error downloading report');
    }
})->name('workorderquantity.download');

// Add a direct file access route for testing
Route::get('/storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);
    if (file_exists($fullPath)) {
        return response()->download($fullPath);
    }
    abort(404, 'File not found');
})->where('path', '.*');

// Force HTTP for all routes
if (app()->environment('local')) {
    URL::forceScheme('http');
}

Route::get('/test-chart', function () {
    return view('test-chart');
});

/*Route::get('/admin/{tenant}/advanced-work-order-gantt', function () {
    return app(AdvancedWorkOrderGantt::class)->render();
})->name('filament.admin.widgets.advanced-work-order-gantt');
*/

Route::get('/export/work-orders', function (\Illuminate\Http\Request $request) {
    $start = $request->input('start') ?? now()->subMonth()->toDateString();
    $end = $request->input('end') ?? now()->toDateString();

    return Excel::download(new WorkOrderExport($start, $end), 'work-orders.xlsx');
})->name('export.workorders');


Route::get('/debug-workorders', function () {
    $export = new \App\Exports\WorkOrderSheet('2025-06-01', '2025-06-30');
    dd($export->collection());
});

// Pivot Table Builder Route
Route::get('/pivot-table-builder', \App\Livewire\PivotTableBuilder::class)
    ->middleware(['auth'])
    ->name('pivot.table.builder');

// Simple Pivot Table Builder Route for testing
Route::get('/simple-pivot', \App\Livewire\SimplePivotTableBuilder::class)
    ->middleware(['auth'])
    ->name('simple.pivot.builder');

// Auto Pivot Table Builder Route - No CSV upload required
Route::get('/auto-pivot', \App\Livewire\AutoPivotTableBuilder::class)
    ->middleware(['auth'])
    ->name('auto.pivot.builder');
