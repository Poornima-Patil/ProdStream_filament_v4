<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Pages;

use App\Filament\Admin\Resources\WorkOrderResource;
use App\Filament\Admin\Resources\WorkOrderResource\Widgets\WorkOrderPieChart;
use App\Models\PartNumber;
use App\Models\WorkOrderLog;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ListWorkOrders extends ListRecords
{
    protected static string $resource = WorkOrderResource::class;

    use ExposesTableToWidgets;

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

    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Retrieves the query builder for the WorkOrders table.
     *
     * This method logs an info message indicating that the `getTableQuery` has been called.
     * It then returns an eloquent query filtered by the factory ID of the authenticated user.
     *
     * @return Builder The query builder for the WorkOrders table.
     */

    /*******  ef5a735e-e81c-4b32-be8c-930717cd88f5  *******/

    protected function getTableQuery(): Builder
    {
        \Log::info('getTableQuery called for WorkOrders');

        return $this->getResource()::getEloquentQuery()
            ->where('factory_id', auth()->user()->factory_id);
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All Work Orders')
                ->badge(fn () => $this->getFilteredTableQuery()->count())
                ->modifyQueryUsing(fn (Builder $query) => $query),
        ];

        // Get unique statuses from the filtered table query inside the badge closure
        $statuses = \App\Models\WorkOrder::where('factory_id', auth()->user()->factory_id)
            ->pluck('status')
            ->unique();

        foreach ($statuses as $status) {
            $tabs[str($status)->slug()->toString()] = Tab::make($status)
                ->badge(fn () => $this->getFilteredTableQuery()->clone()->where('status', $status)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        return $tabs;
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->filters([
                TrashedFilter::make(),

                // Operator Filter
                SelectFilter::make('operator_id')
                    ->label('Operator')
                    ->relationship('operator.user', 'first_name', function ($query) {
                        $query->whereHas('roles', function ($roleQuery) {
                            $roleQuery->where('name', 'operator');
                        })
                        ->where('factory_id', auth()->user()->factory_id);
                    })
                    ->query(function ($query) {
                        $query->where('factory_id', auth()->user()->factory_id);
                    })
                    ->searchable()
                    ->preload()
                    ->multiple(),

                // Machine Filter
                SelectFilter::make('machine_id')
                    ->label('Machine')
                    ->relationship('machine', 'assetId', function ($query) {
                        $query->where('factory_id', auth()->user()->factory_id);
                    })
                    ->query(function ($query) {
                        $query->where('factory_id', auth()->user()->factory_id);
                    })
                    ->searchable()
                    ->preload()
                    ->multiple(),

                // Part Number Filter
                SelectFilter::make('partnumber_revision')
                    ->label('Part Number Revision')
                    ->options(function () {
                        return PartNumber::where('factory_id', auth()->user()->factory_id)
                            ->pluck(DB::raw("CONCAT(partnumber, ' - ', revision)"), 'id')
                            ->mapWithKeys(function ($item, $key) {
                                return [$key => $item];
                            })
                            ->toArray();
                    })
                    ->query(function ($query, $data) {
                        $query->where('factory_id', auth()->user()->factory_id);
                        if (! is_array($data) || empty($data['value'])) {
                            return;
                        }
                        $partnumber_id = $data['value'];
                        $part = \App\Models\PartNumber::find($partnumber_id);
                        if ($part) {
                            $partnumber = $part->partnumber;
                            $revision = $part->revision;
                            $query->whereHas('bom.purchaseorder.partnumber', function ($q) use ($partnumber, $revision) {
                                $q->where('partnumber', $partnumber)
                                    ->where('revision', $revision);
                            });
                        } else {
                            $query->whereRaw('1 = 0');
                        }
                    })
                    ->searchable()
                    ->preload(),

                // Unique ID Filter
                Filter::make('unique_id')
                    ->label('Unique ID')
                    ->query(fn (Builder $query, $data) =>
                        $query->where('factory_id', auth()->user()->factory_id)
                            ->where('unique_id', 'like', '%'.(is_array($data) ? implode(',', $data) : $data).'%')
                    )
                    ->form([
                        TextInput::make('unique_id')->label('Unique ID'),
                    ]),

                // Created Date Range Filter
                Filter::make('created_at_range')
                    ->label('Created Date Range')
                    ->form([
                        DatePicker::make('from')
                            ->label('From Date')
                            ->default(Carbon::now()->subDays(30)->toDateString()),
                        DatePicker::make('to')
                            ->label('To Date')
                            ->default(Carbon::now()->toDateString()),
                    ])
                    ->default([
                        'from' => Carbon::now()->subDays(30)->toDateString(),
                        'to' => Carbon::now()->toDateString(),
                    ])
                    ->query(function ($query, $data) {
                        $query->where('factory_id', auth()->user()->factory_id);
                        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : null;
                        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : null;
                        if ($from && $to) {
                            $query->whereBetween('created_at', [$from, $to]);
                        }
                        return $query;
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! isset($data['from'], $data['to'])) {
                            return null;
                        }
                        $from = Carbon::parse($data['from'])->format('d-m-Y');
                        $to = Carbon::parse($data['to'])->format('d-m-Y');
                        return "Created between {$from} and {$to}";
                    }),
            ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WorkOrderPieChart::class,
            \App\Filament\Admin\Resources\WorkOrderResource\Widgets\SimpleWorkOrderGantt::class,

        ];
    }

    public function getTableRecordKey($record): string
    {
        return $record->unique_id;
    }
}
