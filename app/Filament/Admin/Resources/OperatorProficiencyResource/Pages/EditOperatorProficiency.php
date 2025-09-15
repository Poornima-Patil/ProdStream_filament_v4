<?php

namespace App\Filament\Admin\Resources\OperatorProficiencyResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use App\Filament\Admin\Resources\OperatorProficiencyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOperatorProficiency extends EditRecord
{
    protected static string $resource = OperatorProficiencyResource::class;

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
