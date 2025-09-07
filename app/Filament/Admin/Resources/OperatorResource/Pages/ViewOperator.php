<?php

namespace App\Filament\Admin\Resources\OperatorResource\Pages;

use App\Filament\Admin\Resources\OperatorResource;
use App\Livewire\Calendar\Operators\OperatorScheduleCalendar;
use App\Livewire\Calendar\Machines\MachineScheduleGantt;
use Filament\Infolists\Components\Livewire;
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
            Section::make('Operator Information')
                ->hiddenLabel()
                ->collapsible()
                ->schema([
                    TextEntry::make('View Operator')
                        ->label('')
                        ->getStateUsing(function ($record) {
                            if (! $record) {
                                return '<div class="text-gray-500 dark:text-gray-400">NO Operators found</div>';
                            }
                            $Proficiency = $record->operator_proficiency->proficiency;
                            $OperatorFirstName = $record->user->first_name;
                            $OperatorLastName = $record->user->last_name;
                            $OperatorFullName = trim($OperatorFirstName.' '.$OperatorLastName);
                            $Shift = $record->shift->name;
                            $ShiftHours = $record->shift->start_time.' - '.$record->shift->end_time;

                            return new \Illuminate\Support\HtmlString('
                                <!-- Desktop Table -->
                                <div class="hidden lg:block overflow-x-auto rounded-lg shadow">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                        <thead class="bg-primary-500 dark:bg-primary-700">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Operator</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Proficiency</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Shift</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Shift Hours</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($OperatorFullName).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Proficiency).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Shift).'</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-700 text-blue-600 dark:text-blue-400 font-medium">'.htmlspecialchars($ShiftHours).'</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Card -->
                                <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4 overflow-hidden">
                                    <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                        Operator Information
                                    </div>
                                    <div class="p-4 space-y-3 max-w-full overflow-x-auto">
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Operator:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($OperatorFullName).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Proficiency:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($Proficiency).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Shift:</span>
                                            <span class="text-gray-900 dark:text-gray-100 break-all sm:text-right sm:ml-2">'.htmlspecialchars($Shift).'</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between min-w-0">
                                            <span class="font-bold text-black dark:text-white mb-1 sm:mb-0 flex-shrink-0">Shift Hours:</span>
                                            <span class="text-blue-600 dark:text-blue-400 font-medium break-all sm:text-right sm:ml-2">'.htmlspecialchars($ShiftHours).'</span>
                                        </div>
                                    </div>
                                </div>
                            ');
                        })->html(),
                ]),

            Section::make('Operator Schedule Gantt Chart')
                ->schema([
                    Livewire::make(OperatorScheduleCalendar::class, ['operator' => $this->record])
                        ->key('operator-calendar-'.$this->record->id),
                ])
                ->collapsible()
                ->persistCollapsed()
                ->id('operator-schedule-section')
                ->extraAttributes([
                    'class' => 'hidden lg:block', // Hide on small/medium devices, show on large and above
                ]),
        ]);
    }
}
