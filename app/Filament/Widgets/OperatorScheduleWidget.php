<?php

namespace App\Filament\Widgets;

use App\Models\Operator;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class OperatorScheduleWidget extends Widget
{
    protected static string $view = 'filament.widgets.operator-schedule-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public ?Operator $operator = null;

    public string $viewType = 'week';

    public function mount(?Operator $operator = null): void
    {
        $this->operator = $operator;

        // If no operator is provided, try to find one for the current user
        if (! $this->operator && Auth::user()) {
            $this->operator = Operator::where('user_id', Auth::id())
                ->where('factory_id', Auth::user()->factory_id)
                ->with(['shift', 'user'])
                ->first();
        }
    }

    public function getOperatorsProperty()
    {
        return Operator::where('factory_id', Auth::user()->factory_id)
            ->with(['user', 'shift'])
            ->get()
            ->mapWithKeys(function ($operator) {
                $name = $operator->user
                    ? $operator->user->getFilamentName()
                    : 'Unknown Operator';

                $shiftInfo = $operator->shift
                    ? " ({$operator->shift->name})"
                    : '';

                return [$operator->id => $name.$shiftInfo];
            });
    }

    public function selectOperator($operatorId): void
    {
        $this->operator = Operator::with(['shift', 'user'])->find($operatorId);
    }

    public function changeView($viewType): void
    {
        $this->viewType = $viewType;
    }

    public static function canView(): bool
    {
        return Auth::check() && Auth::user()->factory_id;
    }

    protected function getViewData(): array
    {
        return [
            'operator' => $this->operator,
            'operators' => $this->operators,
            'viewType' => $this->viewType,
        ];
    }
}
