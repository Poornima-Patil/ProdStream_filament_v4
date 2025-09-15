<?php

namespace App\Filament\Admin\Resources\PartnumberResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use App\Filament\Admin\Resources\PartnumberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartnumber extends EditRecord
{
    protected static string $resource = PartnumberResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
