<?php

namespace App\Filament\Admin\Resources\MachineResource\Pages;

use App\Filament\Admin\Resources\MachineResource;
use App\Livewire\Calendar\Machines\MachineScheduleCalendar;
use App\Livewire\Calendar\Machines\MachineScheduleGantt;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewMachine extends ViewRecord
{
    protected static string $resource = MachineResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Machine Information')
                    ->hiddenLabel()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('View Machine')
                            ->label('')
                            ->getStateUsing(function ($record) {
                                if (! $record) {
                                    return '<div class="text-gray-500 dark:text-gray-400">No Machine Found</div>';
                                }
                                $AssetID = $record->assetId;
                                $MachineName = $record->name;
                                $Status = $record->status;
                                $Department = $record->department ? $record->department->name : '';
                                $MachineGroup = $record->machineGroup ? $record->machineGroup->group_name : '';
                                // Status formatting
                                if ($Status == 1) {
                                    $StatusLabel = '<span style="color: #22c55e; font-weight: bold;">Active</span>'; // green
                                } else {
                                    $StatusLabel = '<span style="color: #dc2626; font-weight: bold;">Inactive</span>'; // red
                                }

                                return new \Illuminate\Support\HtmlString('
                                    <!-- Desktop Table -->
                                    <div class="hidden lg:block overflow-x-auto shadow rounded-lg">
                                        <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                                            <thead class="bg-primary-500 dark:bg-primary-700">
                                                <tr>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Asset ID</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Machine Name</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Status</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Department</th>
                                                    <th class="p-2 border border-gray-300 dark:border-gray-700 font-bold text-black dark:text-white">Machine Group</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($AssetID).'</td>
                                                    <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($MachineName).'</td>
                                                    <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.$StatusLabel.'</td>
                                                    <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($Department).'</td>
                                                    <td class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">'.htmlspecialchars($MachineGroup).'</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <!-- Mobile Card -->
                                    <div class="block lg:hidden bg-white dark:bg-gray-900 shadow rounded-lg border border-gray-300 dark:border-gray-700 mt-4">
                                        <div class="bg-primary-500 text-white px-4 py-2 rounded-t-lg">
                                            Machine Details
                                        </div>
                                        <div class="p-4 space-y-3">
                                            <div>
                                                <span class="font-bold text-black dark:text-white">Asset ID: </span>
                                                <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($AssetID).'</span>
                                            </div>
                                            <div>
                                                <span class="font-bold text-black dark:text-white">Machine Name: </span>
                                                <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($MachineName).'</span>
                                            </div>
                                            <div>
                                                <span class="font-bold text-black dark:text-white">Status: </span>
                                                <span class="text-gray-900 dark:text-gray-100">'.$StatusLabel.'</span>
                                            </div>
                                            <div>
                                                <span class="font-bold text-black dark:text-white">Department: </span>
                                                <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($Department).'</span>
                                            </div>
                                            <div>
                                                <span class="font-bold text-black dark:text-white">Machine Group: </span>
                                                <span class="text-gray-900 dark:text-gray-100">'.htmlspecialchars($MachineGroup).'</span>
                                            </div>
                                        </div>
                                    </div>
                                ');
                            })->html(),
                    ]),

                // Machine Schedule Calendar visible on both desktop and mobile
                Section::make('Machine Schedule Calendar')
                    ->collapsible()
                    ->persistCollapsed()
                    ->id('machine-schedule-section')
                    ->schema([
                        // No hidden classes, so visible everywhere
                        Livewire::make(MachineScheduleCalendar::class, ['machine' => $this->record])
                            ->key('machine-calendar-'.$this->record->id),
                    ]),

                Section::make('Machine Schedule Gantt Chart')
                    ->schema([
                        Livewire::make(MachineScheduleGantt::class, ['machine' => $this->record])
                            ->key('machine-gantt-'.$this->record->id),
                    ])
                    ->collapsible()
                    ->persistCollapsed()
                    ->id('machine-gantt-section')
                    ->extraAttributes([
                        'class' => 'hidden lg:block', // Hide on small/medium devices, show on large and above
                    ]),
            ]);
    }
}
