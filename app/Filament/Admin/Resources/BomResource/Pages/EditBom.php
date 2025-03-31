<?php

namespace App\Filament\Admin\Resources\BomResource\Pages;

use App\Filament\Admin\Resources\BomResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBom extends EditRecord
{
    protected static string $resource = BomResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
