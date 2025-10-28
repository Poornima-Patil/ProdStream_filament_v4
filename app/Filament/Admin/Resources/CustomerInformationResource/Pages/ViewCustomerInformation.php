<?php

namespace App\Filament\Admin\Resources\CustomerInformationResource\Pages;

use App\Filament\Admin\Resources\CustomerInformationResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewCustomerInformation extends ViewRecord
{
    protected static string $resource = CustomerInformationResource::class;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public function mount($record): void
    {
        parent::mount($record);

        // Set default date range to last 30 days if not provided
        if (! $this->dateFrom || ! $this->dateTo) {
            $this->dateTo = Carbon::now()->format('Y-m-d');
            $this->dateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getDateRange(): array
    {
        return [$this->dateFrom, $this->dateTo];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // Work Order Analytics & Distribution with Date Filter
            Section::make('Work Order Analytics & Distribution')
                ->collapsible()
                ->columnSpanFull()
                ->description('Filter work orders and view analytics for this customer')
                ->headerActions([
                    Action::make('filterByDate')
                        ->label('Filter by Date Range')
                        ->icon('heroicon-o-calendar')
                        ->color('primary')
                        ->form([
                            DatePicker::make('date_from')
                                ->label('From Date')
                                ->default($this->dateFrom)
                                ->required(),
                            DatePicker::make('date_to')
                                ->label('To Date')
                                ->default($this->dateTo)
                                ->required(),
                        ])
                        ->action(function (array $data): void {
                            $this->dateFrom = $data['date_from'];
                            $this->dateTo = $data['date_to'];

                            // Dispatch event to update the analytics component
                            $this->dispatch('dateRangeUpdated', dateFrom: $this->dateFrom, dateTo: $this->dateTo);

                            // Show notification
                            \Filament\Notifications\Notification::make()
                                ->title('Date range updated')
                                ->body('Data filtered from '.$this->dateFrom.' to '.$this->dateTo)
                                ->success()
                                ->send();
                        }),
                ])
                ->schema([
                    Livewire::make(\App\Livewire\CustomerAnalytics::class, [
                        'customer' => $this->record,
                        'fromDate' => $this->dateFrom,
                        'toDate' => $this->dateTo,
                        'factoryId' => \Filament\Facades\Filament::getTenant()?->id,
                    ])->key('customer-analytics-'.$this->record->id),
                ]),

            Section::make('Customer Information')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('Customer Details')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (! $record) {
                                return '<div class="text-gray-500 dark:text-gray-400">No Customers Found</div>';
                            }

                            $Customer_Id = $record->customer_id;
                            $Name = $record->name;
                            $Address = $record->address;

                            return new HtmlString('
                                <!-- Large screen table -->
                                <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Customer ID</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Name</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Address</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Customer_Id).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Name).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Address).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Customer Details
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Customer ID: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Customer_Id).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Name: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Name).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Address: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Address).'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),
        ]);
    }
}
