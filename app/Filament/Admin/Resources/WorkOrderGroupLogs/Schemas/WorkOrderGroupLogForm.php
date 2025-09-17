<?php

namespace App\Filament\Admin\Resources\WorkOrderGroupLogs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WorkOrderGroupLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('work_order_group_id')
                    ->relationship('workOrderGroup', 'name')
                    ->required(),
                TextInput::make('event_type')
                    ->required(),
                TextInput::make('event_description')
                    ->required(),
                Select::make('triggered_work_order_id')
                    ->relationship('triggeredWorkOrder', 'id'),
                Select::make('triggering_work_order_id')
                    ->relationship('triggeringWorkOrder', 'id'),
                TextInput::make('previous_status'),
                TextInput::make('new_status'),
                TextInput::make('metadata'),
                Select::make('user_id')
                    ->relationship('user', 'id'),
            ]);
    }
}
