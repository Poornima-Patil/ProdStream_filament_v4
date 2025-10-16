<?php

namespace App\Models\KPI;

use App\Models\Factory;
use App\Models\Operator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorDaily extends Model
{
    protected $table = 'kpi_operator_daily';

    protected $fillable = [
        'factory_id',
        'operator_id',
        'summary_date',
        'work_orders_completed',
        'work_orders_assigned',
        'units_produced',
        'hours_worked',
        'efficiency_rate',
        'productivity_score',
        'average_cycle_time',
        'quality_rate',
        'first_pass_yield',
        'defect_count',
        'scrap_units',
        'skill_level',
        'proficiency_score',
        'training_hours',
        'workload_balance_score',
        'overtime_hours',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'hours_worked' => 'decimal:2',
            'efficiency_rate' => 'decimal:2',
            'productivity_score' => 'decimal:2',
            'average_cycle_time' => 'decimal:2',
            'quality_rate' => 'decimal:2',
            'first_pass_yield' => 'decimal:2',
            'proficiency_score' => 'decimal:2',
            'training_hours' => 'decimal:2',
            'workload_balance_score' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
