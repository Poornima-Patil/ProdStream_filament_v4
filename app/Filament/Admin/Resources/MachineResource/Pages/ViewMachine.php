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
                    ->schema([
                        TextEntry::make('assetId')
                            ->label('Asset ID'),
                        TextEntry::make('name')
                            ->label('Machine Name'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                '1' => 'success',
                                '0' => 'danger',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                '1' => 'Active',
                                '0' => 'Inactive',
                            }),
                        TextEntry::make('department.name')
                            ->label('Department'),
                        TextEntry::make('machineGroup.group_name')
                            ->label('Machine Group'),
                    ])
                    ->columns(2),

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
