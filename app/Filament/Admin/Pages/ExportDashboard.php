<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\Log;

class ExportDashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.admin.pages.export-dashboard';
    protected static ?string $navigationGroup = 'Work Order Reports';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Excel Download';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->subMonth()->toDateString(),
            'endDate' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('startDate')
                    ->label('Start Date')
                    ->required(),
                DatePicker::make('endDate')
                    ->label('End Date')
                    ->required(),
            ])
            ->statePath('data'); // ✅ use 'data' instead of '.'
    }

    public function download()
    {
        $formState = $this->form->getState();

        Log::info('Export form submitted', [
            'startDate' => $formState['startDate'],
            'endDate'   => $formState['endDate'],
        ]);

        return redirect()->route('export.workorders', [
            'start' => $formState['startDate'],
            'end'   => $formState['endDate'],
        ]);
    }
}

