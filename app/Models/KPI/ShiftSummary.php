<?php

namespace App\Models\KPI;

use App\Models\Factory;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSummary extends Model
{
    protected $table = 'kpi_shift_summaries';

    protected $fillable = [
        'factory_id',
        'shift_id',
        'shift_date',
        'shift_name',
        'shift_start_time',
        'shift_end_time',
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
        'throughput_per_hour',
        'total_production_hours',
        'total_downtime_hours',
        'average_cycle_time',
        'first_pass_yield',
        'quality_rate',
        'defect_count',
        'operator_efficiency',
        'machine_utilization',
        'schedule_adherence',
        'setup_time_hours',
        'changeover_count',
        'changeover_efficiency',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'shift_start_time' => 'datetime',
            'shift_end_time' => 'datetime',
            'completion_rate' => 'decimal:2',
            'scrap_rate' => 'decimal:2',
            'throughput_per_hour' => 'decimal:2',
            'total_production_hours' => 'decimal:2',
            'total_downtime_hours' => 'decimal:2',
            'average_cycle_time' => 'decimal:2',
            'first_pass_yield' => 'decimal:2',
            'quality_rate' => 'decimal:2',
            'operator_efficiency' => 'decimal:2',
            'machine_utilization' => 'decimal:2',
            'schedule_adherence' => 'decimal:2',
            'setup_time_hours' => 'decimal:2',
            'changeover_efficiency' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
