<?php

namespace App\Filament\Admin\Resources\WorkOrderGroupLogs;

use App\Filament\Admin\Resources\WorkOrderGroupLogs\Pages\ListWorkOrderGroupLogs;
use App\Filament\Admin\Resources\WorkOrderGroupLogs\Schemas\WorkOrderGroupLogForm;
use App\Filament\Admin\Resources\WorkOrderGroupLogs\Tables\WorkOrderGroupLogsTable;
use App\Models\WorkOrderGroupLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkOrderGroupLogResource extends Resource
{
    protected static ?string $model = WorkOrderGroupLog::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlineRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return WorkOrderGroupLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkOrderGroupLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkOrderGroupLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
