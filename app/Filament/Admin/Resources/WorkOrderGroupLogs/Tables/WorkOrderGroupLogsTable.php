<?php

namespace App\Filament\Admin\Resources\WorkOrderGroupLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkOrderGroupLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->size('sm'),

                TextColumn::make('workOrderGroup.name')
                    ->label('Group')
                    ->searchable()
                    ->size('sm')
                    ->weight('medium'),

                IconColumn::make('event_type')
                    ->label('Type')
                    ->icon(fn (string $state): string => match ($state) {
                        'dependency_satisfied' => 'heroicon-o-arrow-right-circle',
                        'status_change' => 'heroicon-o-arrow-path',
                        'work_order_triggered' => 'heroicon-o-play',
                        default => 'heroicon-o-information-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'dependency_satisfied' => 'success',
                        'status_change' => 'warning',
                        'work_order_triggered' => 'primary',
                        default => 'gray',
                    })
                    ->size('sm'),

                TextColumn::make('event_description')
                    ->label('Event')
                    ->searchable()
                    ->wrap()
                    ->size('sm'),

                TextColumn::make('triggeredWorkOrder.unique_id')
                    ->label('Triggered WO')
                    ->size('sm')
                    ->toggleable(),

                TextColumn::make('triggeringWorkOrder.unique_id')
                    ->label('Triggering WO')
                    ->size('sm')
                    ->toggleable(),

                TextColumn::make('status_change')
                    ->label('Status Change')
                    ->formatStateUsing(function ($record) {
                        if ($record->previous_status && $record->new_status) {
                            return $record->previous_status.' → '.$record->new_status;
                        }

                        return null;
                    })
                    ->badge()
                    ->color('info')
                    ->size('sm')
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->size('sm')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('work_order_group_id')
                    ->label('Work Order Group')
                    ->relationship('workOrderGroup', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'dependency_satisfied' => 'Dependency Satisfied',
                        'status_change' => 'Status Change',
                        'work_order_triggered' => 'Work Order Triggered',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalContent(function ($record) {
                        return view('filament.admin.resources.work-order-group-logs.view-log', compact('record'));
                    })
                    ->modalWidth('2xl'),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
