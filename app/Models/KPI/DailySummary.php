<?php

namespace App\Models\KPI;

use App\Models\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySummary extends Model
{
    protected $table = 'kpi_daily_summaries';

    protected $fillable = [
        'factory_id',
        'summary_date',
        'total_orders',
        'completed_orders',
        'in_progress_orders',
        'assigned_orders',
        'hold_orders',
        'closed_orders',
        'completion_rate',
        'total_units_produced',
        'ok_units',
        'scrapped_units',
        'scrap_rate',
        'throughput_per_day',
        'total_production_hours',
        'total_downtime_hours',
        'average_cycle_time',
        'first_pass_yield',
        'quality_rate',
        'defect_count',
        'oee',
        'operator_efficiency',
        'machine_utilization',
        'capacity_utilization',
        'on_time_delivery_rate',
        'orders_delivered',
        'orders_delayed',
        'bom_utilization_rate',
        'so_to_wo_conversion_rate',
        'planning_accuracy',
        'scrap_cost',
        'downtime_cost',
        'labor_cost',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'completion_rate' => 'decimal:2',
            'scrap_rate' => 'decimal:2',
            'throughput_per_day' => 'decimal:2',
            'total_production_hours' => 'decimal:2',
            'total_downtime_hours' => 'decimal:2',
            'average_cycle_time' => 'decimal:2',
            'first_pass_yield' => 'decimal:2',
            'quality_rate' => 'decimal:2',
            'oee' => 'decimal:2',
            'operator_efficiency' => 'decimal:2',
            'machine_utilization' => 'decimal:2',
            'capacity_utilization' => 'decimal:2',
            'on_time_delivery_rate' => 'decimal:2',
            'bom_utilization_rate' => 'decimal:2',
            'so_to_wo_conversion_rate' => 'decimal:2',
            'planning_accuracy' => 'decimal:2',
            'scrap_cost' => 'decimal:2',
            'downtime_cost' => 'decimal:2',
            'labor_cost' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
