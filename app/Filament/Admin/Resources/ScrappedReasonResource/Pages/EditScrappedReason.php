<?php

namespace App\Filament\Admin\Resources\ScrappedReasonResource\Pages;

use App\Filament\Admin\Resources\ScrappedReasonResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScrappedReason extends EditRecord
{
    protected static string $resource = ScrappedReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
