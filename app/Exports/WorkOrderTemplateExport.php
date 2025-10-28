<?php

namespace App\Exports;

use App\Models\Factory;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class WorkOrderTemplateExport
{
    public function __construct(
        public string $start,
        public string $end,
        public Factory $factory
    ) {}

    public function downloadCsv()
    {
        $filename = 'work_order_report.csv';
        $csvRelativePath = "csv/{$filename}";
        $csvFullPath = storage_path("app/public/{$csvRelativePath}");

        if (! file_exists(dirname($csvFullPath))) {
            mkdir(dirname($csvFullPath), 0755, true);
        }

        $factoryId = $this->factory->id;

        Log::info('Factory ID: ', ['factory_id' => $factoryId]);

        $records = WorkOrder::with([
            'bom.purchaseOrder.partNumber',
            'machine',
            'operator',
            'okQuantities',
            'scrappedQuantities',
        ])
            ->where('factory_id', $factoryId)
            ->whereBetween('created_at', [$this->start, $this->end])
            ->get();

        $handle = fopen($csvFullPath, 'w');

        // Write headers
        fputcsv($handle, [
            'Work Order No', 'BOM', 'Part Number', 'Revision', 'Machine', 'Operator',
            'Qty', 'Status', 'Start Time', 'End Time', 'OK Qty', 'KO Qty',
        ]);

        // Write data rows
        foreach ($records as $wo) {
            fputcsv($handle, [
                $wo->unique_id,
                optional($wo->bom)->unique_id,
                optional($wo->bom?->purchaseOrder?->partNumber)->partnumber,
                optional($wo->bom?->purchaseOrder?->partNumber)->revision,
                optional($wo->machine)->name,
                optional($wo->operator->user)->first_name,
                $wo->qty,
                ucfirst($wo->status),
                $wo->start_time?->format('Y-m-d H:i'),
                $wo->end_time?->format('Y-m-d H:i'),
                $wo->ok_qtys,
                $wo->scrapped_qtys,
            ]);
        }

        fclose($handle);

        $publicUrl = Storage::url($csvRelativePath);
        Log::info('CSV public URL:', ['url' => $publicUrl]);

        return response()->file($csvFullPath, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'inline; filename="'.basename($csvFullPath).'"',
        ]);

    }

    public function download()
    {
        $relativeTemplatePath = $this->factory->template_path;
        Log::info('Factory template path:', ['template_path' => $relativeTemplatePath]);

        $templatePath = storage_path('app/public/'.$relativeTemplatePath);
        Log::info('Resolved template full path:', ['path' => $templatePath]);

        if (! file_exists($templatePath) || is_dir($templatePath)) {
            abort(404, 'Template file not found or is a directory.');
        }

        $filename = 'workorder_'.Str::slug($this->factory->name).'_'.now()->format('Ymd_His').'.xlsx';
        $tempPath = storage_path("app/temp/{$filename}");

        if (! file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        copy($templatePath, $tempPath);

        $spreadsheet = IOFactory::load($tempPath);
        $sheet = $spreadsheet->getSheetByName('Work Order Header');

        if (! $sheet) {
            abort(500, 'Sheet "Work Order Header" not found in template.');
        }

        $records = WorkOrder::with([
            'bom.purchaseOrder.partNumber',
            'machine',
            'operator',
            'okQuantities',
            'scrappedQuantities',
        ])
            ->where('factory_id', $this->factory->id)
            ->whereBetween('created_at', [$this->start, $this->end])
            ->get();

        $row = 2;

        foreach ($records as $wo) {
            $sheet->setCellValue("A{$row}", $wo->unique_id);
            $sheet->setCellValue("B{$row}", optional($wo->bom)->unique_id);
            $sheet->setCellValue("C{$row}", optional($wo->bom?->purchaseOrder?->partNumber)->partnumber);
            $sheet->setCellValue("D{$row}", optional($wo->bom?->purchaseOrder?->partNumber)->revision);
            $sheet->setCellValue("E{$row}", optional($wo->machine)->name);
            $sheet->setCellValue("F{$row}", optional($wo->operator->user)->first_name);
            $sheet->setCellValue("G{$row}", $wo->qty);
            $sheet->setCellValue("H{$row}", ucfirst($wo->status));
            $sheet->setCellValue("I{$row}", $wo->start_time?->format('Y-m-d H:i'));
            $sheet->setCellValue("J{$row}", $wo->end_time?->format('Y-m-d H:i'));
            $sheet->setCellValue("K{$row}", $wo->ok_qtys);
            $sheet->setCellValue("L{$row}", $wo->scrapped_qtys);
            $row++;
        }

        return response()->download($tempPath)->deleteFileAfterSend();
    }
}
