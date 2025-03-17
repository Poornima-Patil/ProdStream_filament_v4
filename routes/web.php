<?php

use Illuminate\Support\Facades\Route;
use App\Models\OkQuantity;


Route::get('/', function () {
    return view('welcome');
});


Route::get('/okquantity/download/{id}', function ($id) {
    $okQuantity = OkQuantity::findOrFail($id);

    // Get the report from media library
    $media = $okQuantity->getFirstMedia('report_pdf');
    if (!$media) {
        abort(404, 'Report not found.');
    }

    return response()->download($media->getPath(), $media->file_name);
})->name('okquantity.download');