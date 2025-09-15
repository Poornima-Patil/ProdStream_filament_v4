<?php

namespace App\Filament\Admin\Resources\CustomerInformationResource\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;
use App\Filament\Admin\Resources\CustomerInformationResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerInformation extends ViewRecord
{
    protected static string $resource = CustomerInformationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('View Customer Information')
                ->hiddenLabel()
                ->collapsible()->columnSpanFull()
                ->schema([
                    TextEntry::make('View Customer Information')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (!$record) {
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
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Customer Id</th>
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
                                            <span class="font-bold text-black dark:text-white">Customer Id: </span>
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
