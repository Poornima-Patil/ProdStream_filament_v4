<?php

namespace App\Filament\Admin\Resources\PartnumberResource\Pages;

use App\Filament\Admin\Resources\PartnumberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartnumber extends EditRecord
{
    protected static string $resource = PartnumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
