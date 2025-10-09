<?php

namespace App\Models\KPI;

use App\Models\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyAggregate extends Model
{
    protected $table = 'kpi_monthly_aggregates';

    protected $fillable = [
        'factory_id',
        'year',
        'month',
        'avg_completion_rate',
        'avg_throughput',
        'avg_quality_rate',
        'avg_oee',
        'total_units_produced',
        'total_work_orders',
        'total_production_hours',
        'total_downtime_hours',
        'capacity_utilization',
        'planning_efficiency_score',
        'customer_satisfaction_score',
        'total_scrap_cost',
        'total_labor_cost',
        'revenue_per_hour',
        'is_finalized',
        'finalized_at',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'avg_completion_rate' => 'decimal:2',
            'avg_throughput' => 'decimal:2',
            'avg_quality_rate' => 'decimal:2',
            'avg_oee' => 'decimal:2',
            'total_production_hours' => 'decimal:2',
            'total_downtime_hours' => 'decimal:2',
            'capacity_utilization' => 'decimal:2',
            'planning_efficiency_score' => 'decimal:2',
            'customer_satisfaction_score' => 'decimal:2',
            'total_scrap_cost' => 'decimal:2',
            'total_labor_cost' => 'decimal:2',
            'revenue_per_hour' => 'decimal:2',
            'is_finalized' => 'boolean',
            'finalized_at' => 'datetime',
            'calculated_at' => 'datetime',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
