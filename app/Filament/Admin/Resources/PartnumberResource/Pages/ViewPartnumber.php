<?php

namespace App\Filament\Admin\Resources\PartnumberResource\Pages;

use App\Filament\Admin\Resources\PartNumberResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPartnumber extends ViewRecord
{
    protected static string $resource = PartNumberResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('View Part Number')
                ->collapsible()
                ->schema([
                    TextEntry::make('View PartNumber')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (!$record) {
                                return '<div class="text-gray-500 dark:text-gray-400">No Part Number Found</div>';
                            }

                            $Partnumber = $record->partnumber ?? '';
                            $Revision = $record->revision ?? '';
                            $Description = $record->description ?? '';

                            // Format cycle_time as MM:SS
                            $seconds = intval($record->cycle_time);
                            $minutes = floor($seconds / 60);
                            $remainingSeconds = $seconds % 60;
                            $Cycle_time = sprintf('%02d:%02d', $minutes, $remainingSeconds);

                            return new \Illuminate\Support\HtmlString('
                                <!-- Desktop Table -->
                                <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Part Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Revision</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Description</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Cycle Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Partnumber).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Revision).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Description).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$Cycle_time.'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Part Number Details
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Part Number: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Partnumber).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Revision: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Revision).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Description: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Description).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Cycle Time: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.$Cycle_time.'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),
        ]);
    }
}
