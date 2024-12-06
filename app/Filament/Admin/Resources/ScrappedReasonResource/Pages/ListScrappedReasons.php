<?php

namespace App\Filament\Admin\Resources\ScrappedReasonResource\Pages;

use App\Filament\Admin\Resources\ScrappedReasonResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScrappedReasons extends ListRecords
{
    protected static string $resource = ScrappedReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
