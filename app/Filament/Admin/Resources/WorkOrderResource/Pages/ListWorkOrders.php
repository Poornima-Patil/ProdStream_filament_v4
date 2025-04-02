<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Pages;

use App\Filament\Admin\Resources\WorkOrderResource;
use App\Models\WorkOrderLog;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ListWorkOrders extends ListRecords
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->after(function ($livewire, array $data) {
                    $workOrder = $livewire->record;

                    WorkOrderLog::create([
                        'work_order_id' => $workOrder->id,
                        'user_id' => Auth::id(),
                        'comments' => $data['log_comments'] ?? null,
                        'priority' => $data['log_priority'],
                        'status' => $workOrder->status,
                    ]);

                    Notification::make()
                        ->title('Log Added')
                        ->body('Work Order log has been recorded successfully.')
                        ->success()
                        ->send();
                })
                ->form([
                    Textarea::make('log_comments')
                        ->label('Comments')
                        ->nullable(),

                    Select::make('log_priority')
                        ->label('Priority')
                        ->options([
                            'Low' => 'Low',
                            'Medium' => 'Medium',
                            'High' => 'High',
                        ])
                        ->default('Medium')
                        ->required(),
                ]),
        ];
    }

    public function getTabs(): array
    {
        $baseQuery = $this->getResource()::getEloquentQuery()
            ->where('factory_id', auth()->user()->factory_id);
        
        // Debug the total count and raw data
        $totalCount = $baseQuery->count();
        Log::info('Total work orders count:', ['count' => $totalCount]);
        
        // Get all work orders to debug their status values
        $workOrders = $baseQuery->get();
        Log::info('Work Orders:', ['work_orders' => $workOrders->map(fn($wo) => [
            'id' => $wo->id,
            'status' => $wo->status,
            'factory_id' => $wo->factory_id
        ])->toArray()]);

        $tabs = [
            'all' => Tab::make('All Work Orders')
                ->badge($totalCount)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('factory_id', auth()->user()->factory_id)),
        ];

        // Group work orders by status and count them
        $statusCounts = $workOrders->groupBy('status')
            ->map(fn($group) => $group->count())
            ->toArray();

        Log::info('Status counts:', $statusCounts);

        foreach ($statusCounts as $status => $count) {
            $slug = str($status)->slug()->toString();
            
            $tabs[$slug] = Tab::make($status)
                ->badge($count)
                ->modifyQueryUsing(function (Builder $query) use ($status) {
                    return $query->where('status', $status)
                        ->where('factory_id', auth()->user()->factory_id);
                });
        }

        return $tabs;
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('operator_id')
                    ->label('Operator')
                    ->relationship('operator.user', 'first_name', function ($query) {
                        $query->whereHas('roles', function ($roleQuery) {
                            $roleQuery->where('name', 'operator');
                        });
                    })
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('machine_id')
                    ->label('Machine')
                    ->relationship('machine', 'assetId')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('part_number_id')
                    ->label('Part Number')
                    ->relationship('bom.purchaseorder.partnumber', 'partnumber')
                    ->searchable()
                    ->preload(),
            ])
            ->filtersLayout(FiltersLayout::AboveContent);
    }
}
