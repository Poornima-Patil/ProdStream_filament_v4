<?php

namespace App\Filament\Admin\Resources\OperatorResource\Pages;

use App\Filament\Admin\Resources\OperatorResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOperator extends ViewRecord
{
    protected static string $resource = OperatorResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('View Operator')
                ->hiddenLabel()
                ->collapsible()
                ->schema([
                    TextEntry::make('View Operator')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (!$record) {
                                return '<div class="text-gray-500 dark:text-gray-400">No Operators Found</div>';
                            }

                            $Proficiency = $record->operator_proficiency->proficiency ?? '';
                            $OperatorFirstName = $record->user->first_name ?? '';
                            $OperatorLastName = $record->user->last_name ?? '';
                            $OperatorFullName = trim($OperatorFirstName . ' ' . $OperatorLastName);
                            $Shift = $record->shift->name ?? '';

                            return new \Illuminate\Support\HtmlString('
                                <!-- Large screen table -->
                                <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Operator</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Proficiency</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Shift</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($OperatorFullName).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Proficiency).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Shift).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Operator Details
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Operator: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($OperatorFullName).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Proficiency: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Proficiency).'</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-black dark:text-white">Shift: </span>
                                            <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Shift).'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),
        ]);
    }
}
