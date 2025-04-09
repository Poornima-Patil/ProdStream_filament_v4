<?php

use App\Models\OkQuantity;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

Route::get('/', function () {
    return view('welcome');
});

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
        \Illuminate\Support\Facades\Log::error('Error downloading report: '.$e->getMessage());
        abort(500, 'Error downloading report');
    }
})->name('workorderquantity.download');

// Add a direct file access route for testing
Route::get('/storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/'.$path);
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

