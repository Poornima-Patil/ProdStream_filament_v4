<?php

namespace App\Filament\Admin\Resources\MachineGroupResource\Pages;

use App\Filament\Admin\Resources\MachineGroupResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;

class ViewMachineGroup extends ViewRecord
{
    protected static string $resource = MachineGroupResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            MachineGroupResource\Widgets\MachineGroupStatusChart::class,
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Machine Group Details')
                ->hiddenLabel()
                ->collapsible()->columnSpanFull()
                ->schema([
                    TextEntry::make('Machine Group Information')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (!$record) {
                                return '<div class="text-gray-500 dark:text-gray-400">No Machine Group Found</div>';
                            }

                            $name = $record->name ?? '';
                            $description = $record->description ?? '';
                            $machineCount = $record->machines ? $record->machines->count() : 0;

                            return new HtmlString('
                                <!-- Large screen table -->
                                <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Name</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Description</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Machine Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($name).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($description).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$machineCount.'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Machine Group Details
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Name: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($name).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Description: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($description).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Machine Count: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.$machineCount.'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),
        ]);
    }
}