<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkOrderResource\Pages;
use App\Filament\Widgets\WorkOrderGantt;
use App\Models\WorkOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ... existing form fields ...
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ... existing columns ...
            ])
            ->filters([
                // ... existing filters ...
            ])
            ->actions([
                // ... existing actions ...
            ])
            ->bulkActions([
                // ... existing bulk actions ...
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // ... existing relations ...
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkOrders::route('/'),
            'create' => Pages\CreateWorkOrder::route('/create'),
            'edit' => Pages\EditWorkOrder::route('/{record}/edit'),
            'view' => Pages\ViewWorkOrder::route('/{record}'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            WorkOrderGantt::class,
        ];
    }
} 