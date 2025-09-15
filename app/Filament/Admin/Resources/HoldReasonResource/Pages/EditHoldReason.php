<?php

namespace App\Filament\Admin\Resources\HoldReasonResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Admin\Resources\HoldReasonResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHoldReason extends EditRecord
{
    protected static string $resource = HoldReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
