<?php

namespace App\Filament\Admin\Resources\HoldReasonResource\Pages;

use App\Filament\Admin\Resources\HoldReasonResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHoldReasons extends ListRecords
{
    protected static string $resource = HoldReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
