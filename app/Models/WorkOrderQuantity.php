<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class WorkOrderQuantity extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'work_order_id',
        'work_order_log_id',
        'quantity',
        'type',
        'reason_id'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'type' => 'string'
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function workOrderLog()
    {
        return $this->belongsTo(WorkOrderLog::class);
    }

    public function reason()
    {
        return $this->belongsTo(ScrappedReason::class, 'reason_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('report_pdf')->singleFile();
        $this->addMediaCollection('qr_code')->singleFile();
    }

    protected static function booted()
    {
        static::created(function ($workOrderQuantity) {
            if ($workOrderQuantity->type === 'ok') {
                $workOrderQuantity->generateReport();
                $workOrderQuantity->generateQRCode();
            }
        });
    }

    public function generateQRCode()
    {
        Log::info('generateQRCode called for ID: ' . $this->id);

        // Force HTTP for the URL
        $url = route('workorderquantity.download', ['id' => $this->id]);
        $url = str_replace(['https://', 'http://'], 'http://', $url);

        Log::info('Generated URL for QR code: ' . $url);

        $qrCodeImage = QrCode::size(300)->format('png')->generate($url);

        $qrPath = 'qr_codes/work_order_quantity_' . $this->id . '.png';

        Storage::disk('public')->put($qrPath, $qrCodeImage);

        $manager = new ImageManager(new Driver());
        $image = $manager->read(Storage::disk('public')->path($qrPath));

        $workOrderNumber = $this->workOrder->unique_id ?? 'N/A';
        $partNumber = $this->workOrder->bom->purchaseOrder->partNumber->partnumber ?? 'N/A';
        $revision = $this->workOrder->bom->purchaseOrder->partNumber->revision ?? 'N/A';

        $fontSize = 24;
        $textColor = '#000000';
        $imageWidth = $image->width();
        $imageHeight = $image->height();
        $padding = 10;

        $newHeight = $imageHeight + (2 * $fontSize) + (2 * $padding);
        $finalImage = $manager->create($imageWidth, $newHeight)->fill('#ffffff');

        $finalImage->place($image, 'top-center');

        $finalImage->text("WO#: " . $workOrderNumber, 10, $imageHeight + ($fontSize / 2) + $padding, function ($font) {
            $font->size(24);
            $font->color('#000000');
            $font->align('left');
            $font->valign('middle');
        });

        $finalImage->text("Part#: " . $partNumber . " Rev: " . $revision, 10, $imageHeight + (2 * $fontSize) + $padding, function ($font) {
            $font->size(24);
            $font->color('#000000');
            $font->align('left');
            $font->valign('middle');
        });

        $finalImage->save(Storage::disk('public')->path($qrPath));

        Log::info('Final QR Code with Work Order saved at: ' . Storage::disk('public')->path($qrPath));

        $this->addMedia(Storage::disk('public')->path($qrPath))->toMediaCollection('qr_code');

        Log::info('QR Code added to media library for ID: ' . $this->id);
    }

    public function generateReport()
    {
        try {
            \Illuminate\Support\Facades\Log::info('Generating report for WorkOrderQuantity ID: ' . $this->id);
            
            $pdf = Pdf::loadView('reports.work_order_quantity', ['workOrderQuantity' => $this]);
            
            // Generate a unique filename
            $filename = 'work_order_quantity_' . $this->id . '.pdf';
            
            // Create a temporary file path
            $tempPath = storage_path('app/temp/' . $filename);
            $tempDir = dirname($tempPath);
            
            // Ensure temp directory exists
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            // Save PDF to temporary location
            $pdf->save($tempPath);
            
            \Illuminate\Support\Facades\Log::info('PDF saved to temp location: ' . $tempPath);
            
            // Attach to Media Library using the temporary file
            $this->addMedia($tempPath)
                ->toMediaCollection('report_pdf');
                
            \Illuminate\Support\Facades\Log::info('PDF added to media library');
            
            // Clean up temporary file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error generating report: ' . $e->getMessage());
            throw $e;
        }
    }
} 