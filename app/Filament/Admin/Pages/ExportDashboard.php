<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\Log;

/*class ExportDashboard extends Page implements HasForms
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
}*/


namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\Auth;
use App\Exports\WorkOrderTemplateExport;

class ExportDashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.export-dashboard';
    protected static string | \UnitEnum | null $navigationGroup = 'Work Order Reports';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Excel Download';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->subMonth()->toDateString(),
            'endDate' => now()->toDateString(),
        ]);
    }

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                DatePicker::make('startDate')
                    ->label('Start Date')
                    ->required(),
                DatePicker::make('endDate')
                    ->label('End Date')
                    ->required(),

                \Filament\Schemas\Components\Actions::make([
                    \Filament\Actions\Action::make('download_excel')
                        ->label('Download Excel')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('primary')
                        ->action('download'),

                    \Filament\Actions\Action::make('download_csv')
                        ->label('Download CSV')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->action('downloadCsv'),

                    \Filament\Actions\Action::make('pivot_table')
                        ->label('Create Pivot Table')
                        ->icon('heroicon-o-table-cells')
                        ->color('info')
                        ->url('/pivot-table-builder')
                        ->openUrlInNewTab(),

                    \Filament\Actions\Action::make('auto_pivot_table')
                        ->label('⚡ Auto Pivot (No Upload)')
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->url('/auto-pivot')
                        ->openUrlInNewTab(),

                    \Filament\Actions\Action::make('download_and_pivot')
                        ->label('Download CSV & Create Pivot')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action('downloadCsvAndRedirect'),
                ])
            ])
            ->statePath('data');
    }

    public function download()
    {
        $formState = $this->form->getState();

        $export = new WorkOrderTemplateExport(
            $formState['startDate'],
            $formState['endDate'],
            Auth::user()->factory
        );

        return $export->download();
    }
    // app/Http/Livewire/ExportDashboard.php

    public function downloadCsv()
    {
        $formState = $this->form->getState();
        $export = new WorkOrderTemplateExport(
            $formState['startDate'],
            $formState['endDate'],
            Auth::user()->factory
        );
        return $export->downloadCsv();
    }

    public function downloadCsvAndRedirect()
    {
        // First download the CSV
        $this->downloadCsv();

        // Then redirect to pivot table builder
        return redirect('/pivot-table-builder');
    }
}
