<?php

namespace App\Filament\Admin\Resources\OperatorProficiencyResource\Pages;

use App\Filament\Admin\Resources\OperatorProficiencyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOperatorProficiency extends EditRecord
{
    protected static string $resource = OperatorProficiencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
