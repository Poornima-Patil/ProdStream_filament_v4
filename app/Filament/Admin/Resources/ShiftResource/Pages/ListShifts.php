<?php

namespace App\Filament\Admin\Resources\ShiftResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Resources\ShiftResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShifts extends ListRecords
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
