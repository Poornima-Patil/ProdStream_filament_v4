<?php

namespace App\Filament\Admin\Resources\ScrappedReasonResource\Pages;

use App\Filament\Admin\Resources\ScrappedReasonResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditScrappedReason extends EditRecord
{
    protected static string $resource = ScrappedReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
