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
        'ok_quantity',
        'scrapped_quantity',
        'reason_id',
        'work_order_log_id'
    ];

    protected $casts = [
        'ok_quantity' => 'integer',
        'scrapped_quantity' => 'integer',
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
        static::creating(function ($quantity) {
            // Get the latest work order log for this work order
            $latestLog = WorkOrderLog::where('work_order_id', $quantity->work_order_id)
                ->latest()
                ->first();
            
            if (!$latestLog) {
                // If no log exists, create one
                $workOrder = WorkOrder::find($quantity->work_order_id);
                if ($workOrder) {
                    $latestLog = $workOrder->createWorkOrderLog($workOrder->status);
                }
            }
            
            // Set the work_order_log_id
            if ($latestLog) {
                $quantity->work_order_log_id = $latestLog->id;
            }
        });

        static::created(function ($workOrderQuantity) {
            $workOrderQuantity->generateReport();
            $workOrderQuantity->generateQRCode();
        });
    }

    public function generateQRCode()
    {
        Log::info('generateQRCode called for ID: ' . $this->id);

        // Generate the correct URL format
        $url = url("/work-order-quantity/{$this->id}/download");

        // Generate QR code image
        $qrCodeImage = QrCode::size(300)->format('png')->generate($url);

        // Define file path
        $qrPath = 'qr_codes/work_order_quantity_' . $this->id . '.png';

        // Store raw QR code image temporarily
        Storage::disk('public')->put($qrPath, $qrCodeImage);

        // Initialize ImageManager (GD Driver)
        $manager = new ImageManager(new Driver());

        // Load the stored QR code image with Intervention Image
        $image = $manager->read(Storage::disk('public')->path($qrPath));

        // Get Work Order Unique ID
        $workOrderNumber = $this->workOrder->unique_id ?? 'N/A';

        // Get Part Number and Revision
        $partNumber = $this->workOrder->bom->purchaseOrder->partNumber->partnumber ?? 'N/A';
        $revision = $this->workOrder->bom->purchaseOrder->partNumber->revision ?? 'N/A';

        // Define text properties
        $fontSize = 24;
        $textColor = '#000000'; // Black color
        $imageWidth = $image->width();
        $imageHeight = $image->height();
        $padding = 10; // Reduced padding

        // Create a new blank image with additional height for text
        $newHeight = $imageHeight + (2 * $fontSize) + (2 * $padding);
        $finalImage = $manager->create($imageWidth, $newHeight)->fill('#ffffff');

        // Merge QR Code and Text Image
        $finalImage->place($image, 'top-center');

        // Add Work Order Number Below QR Code
        $finalImage->text("WO#: " . $workOrderNumber, 10, $imageHeight + ($fontSize / 2) + $padding, function ($font) {
            $font->size(24);
            $font->color('#000000');
            $font->align('left');
            $font->valign('middle');
        });

        // Add Part Number and Revision Below WO Number
        $finalImage->text("Part#: " . $partNumber . " Rev: " . $revision, 10, $imageHeight + (2 * $fontSize) + $padding, function ($font) {
            $font->size(24);
            $font->color('#000000');
            $font->align('left');
            $font->valign('middle');
        });

        // Save the final image
        $finalImage->save(Storage::disk('public')->path($qrPath));

        Log::info('Final QR Code with Work Order saved at: ' . Storage::disk('public')->path($qrPath));

        // Attach to Media Library
        $this->addMedia(Storage::disk('public')->path($qrPath))->toMediaCollection('qr_code');

        Log::info('QR Code added to media library for ID: ' . $this->id);
    }

    public function generateReport()
    {
        $pdf = Pdf::loadView('reports.work_order_quantity', ['workOrderQuantity' => $this]);
        $pdfPath = 'reports/work_order_quantity_' . $this->id . '.pdf';

        // Store PDF in Public Storage
        $success = Storage::disk('public')->put($pdfPath, $pdf->output());

        // Attach to Media Library
        $this->addMedia(Storage::disk('public')->path($pdfPath))->toMediaCollection('report_pdf');
    }
} 