<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use App\Filament\Admin\Resources\WorkOrderResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrder extends EditRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function beforeSave()
    {
        // dd($this->data);
        $quantity = $this->data['qty'] ?? 0; // Total quantity (expected)
        $okQtys = $this->data['ok_qtys'] ?? 0;   // OK quantities
        $scrappedQtys = $this->data['scrapped_qtys'] ?? 0; // Scrapped quantities

        if ($this->data['status'] === 'Hold' && ($okQtys + $scrappedQtys) > $quantity) {
            Notification::make()
                ->title('Validation Error')
                ->body('The sum of OK quantities and scrapped quantities must not exceed the total quantity when the status is "Hold".')
                ->danger() // Marks the notification as an error
                ->send();
            $this->halt(); // Stop the save process
        }

        if ($this->data['status'] === 'Completed' && ($okQtys + $scrappedQtys) !== $quantity) {
            Notification::make()
                ->title('Validation Error')
                ->body('The sum of OK quantities and scrapped quantities must exactly match the total quantity when the status is "Completed".')
                ->danger()
                ->send();

            $this->halt(); // Stop the save process

        }

    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
