<?php

namespace App\Filament\Admin\Resources\WorkOrderGroupResource\Pages;

use App\Filament\Admin\Resources\WorkOrderGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrderGroup extends EditRecord
{
    protected static string $resource = WorkOrderGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Check for validation errors stored in session
        if (session()->has('workorder_group_validation_errors')) {
            $errors = session()->get('workorder_group_validation_errors');

            Notification::make()
                ->title('Cannot Activate WorkOrder Group')
                ->body('Dependencies must be defined before activation: '.implode('; ', $errors))
                ->danger()
                ->persistent()
                ->send();

            // Clear the session data
            session()->forget('workorder_group_validation_errors');
        }
    }
}
