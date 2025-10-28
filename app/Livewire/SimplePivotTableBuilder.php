<?php

namespace App\Livewire;

use Exception;
use Livewire\Component;
use Livewire\WithFileUploads;

class SimplePivotTableBuilder extends Component
{
    use WithFileUploads;

    protected $layout = 'components.layouts.app';

    public $csvFile;

    public $csvData = [];

    public $csvHeaders = [];

    // UI state
    public $showConfiguration = false;

    public $showResults = false;

    protected $rules = [
        'csvFile' => 'required|file|mimetypes:text/csv,text/plain|max:10240',
    ];

    public function updatedCsvFile()
    {
        $this->validate(['csvFile' => 'required|file|mimetypes:text/csv,text/plain|max:10240']);

        try {
            $this->processCsvFile();
            $this->showConfiguration = true;
            session()->flash('success', 'CSV file uploaded successfully!');
        } catch (Exception $e) {
            session()->flash('error', 'Error processing CSV: '.$e->getMessage());
        }
    }

    private function processCsvFile()
    {
        $path = $this->csvFile->getRealPath();
        $csvData = [];
        $headers = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $headers = fgetcsv($handle);
            if (! $headers) {
                throw new Exception('Invalid CSV format');
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) === count($headers)) {
                    $csvData[] = array_combine($headers, $row);
                }
            }
            fclose($handle);
        }

        $this->csvHeaders = $headers;
        $this->csvData = $csvData;
    }

    public function render()
    {
        return view('livewire.pivot-table-builder-simple');
    }
}
