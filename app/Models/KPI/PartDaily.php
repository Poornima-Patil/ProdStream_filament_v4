<?php

namespace App\Models\KPI;

use App\Models\Factory;
use App\Models\PartNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartDaily extends Model
{
    protected $table = 'kpi_part_daily';

    protected $fillable = [
        'factory_id',
        'part_number_id',
        'summary_date',
        'units_produced',
        'work_orders_count',
        'production_volume_percentage',
        'quality_rate',
        'scrap_rate',
        'first_pass_yield',
        'defect_count',
        'average_cycle_time',
        'average_lead_time',
        'fulfillment_rate',
        'on_time_completion_rate',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'production_volume_percentage' => 'decimal:2',
            'quality_rate' => 'decimal:2',
            'scrap_rate' => 'decimal:2',
            'first_pass_yield' => 'decimal:2',
            'average_cycle_time' => 'decimal:2',
            'average_lead_time' => 'decimal:2',
            'fulfillment_rate' => 'decimal:2',
            'on_time_completion_rate' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function partNumber(): BelongsTo
    {
        return $this->belongsTo(PartNumber::class);
    }
}
