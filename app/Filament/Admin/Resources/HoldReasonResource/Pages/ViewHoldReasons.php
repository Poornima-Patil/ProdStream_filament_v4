<?php

namespace App\Filament\Admin\Resources\HoldReasonResource\Pages;

use App\Filament\Admin\Resources\HoldReasonResource;

use App\Models\HoldReason;
use Filament\Infolists\Components\Progress;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewHoldReasons extends ViewRecord
{
    protected static string $resource = HoldReasonResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('View Hold Reason')
                ->hiddenLabel()
                ->collapsible()
                ->schema([
                    TextEntry::make('View Hold Reason')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (!$record) {
                                return '<div class="text-gray-500 dark:text-gray-400">No Hold Reasons Found</div>';
                            }
                            $Code = $record->code;
                            $Description = $record->description;

                            return new \Illuminate\Support\HtmlString('
                                <div class="overflow-x-auto rounded-lg shadow">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900">
                                        <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Code</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Code).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Description).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            ');
                        })->html(),
                ]),
        ]);
    }
}


