<?php

namespace App\Filament\Admin\Resources\OperatorResource\Pages;

use App\Filament\Admin\Resources\OperatorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOperator extends EditRecord
{
    protected static string $resource = OperatorResource::class;

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
