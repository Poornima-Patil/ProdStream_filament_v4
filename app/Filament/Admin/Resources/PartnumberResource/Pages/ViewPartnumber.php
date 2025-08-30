<?php

namespace App\Filament\Admin\Resources\PartnumberResource\Pages;

use App\Filament\Admin\Resources\PartNumberResource;
use App\Models\PartNumber;
use Filament\Infolists\Components\Progress;
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
                            $Partnumber = $record->partnumber;
                            $Revision = $record->revision;
                            $Description = $record->description;
                            // Format cycle_time as MM:SS
                            $seconds = intval($record->cycle_time);
                            $minutes = floor($seconds / 60);
                            $remainingSeconds = $seconds % 60;
                            $Cycle_time = sprintf('%02d:%02d', $minutes, $remainingSeconds);

                            return new \Illuminate\Support\HtmlString('
                                <div class="overflow-x-auto rounded-lg shadow">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900">
                                        <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Part Number</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Revision</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Description</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Cycle Time</th>
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
                            ');
                        }),
                ]),
        ]);
    }
}
