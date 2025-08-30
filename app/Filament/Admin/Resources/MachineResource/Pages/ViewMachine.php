<?php

namespace App\Filament\Admin\Resources\MachineResource\Pages;

use App\Filament\Admin\Resources\MachineResource;
use App\Livewire\Calendar\Machines\MachineScheduleCalendar;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Livewire;

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
                            if (!$record) {
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
                                <div class="overflow-x-auto rounded-lg shadow">
                                    <table class="w-full text-sm border border-gray-300 dark:border-gray-700 text-center bg-white dark:bg-gray-900">
                                        <thead class="bg-primary-500 dark:bg-primary-700 text-white">
                                            <tr>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Asset ID</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Machine Name</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Status</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Department</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100">Machine Group</th>
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
                            ');
                        })->html(),
                ]),
                   

                Section::make('Machine Schedule Calendar')
                    ->schema([
                        Livewire::make(MachineScheduleCalendar::class, ['machine' => $this->record])
                            ->key('machine-calendar-' . $this->record->id)
                    ])
                    ->collapsible()
                    ->persistCollapsed()
                    ->id('machine-schedule-section'),
            ]);
    }
}
