<?php

namespace App\Models;

use Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Bom extends Model implements HasMedia
{
    use HasFactory,InteractsWithMedia,SoftDeletes;

    protected $fillable = [
        'purchase_order_id',
        'requirement_pkg',
        'process_flowchart',
        'machine_group_id',
        'operator_proficiency_id',
        'lead_time',
        'status',
        'factory_id',
        'unique_id',
    ];

    public function purchaseOrder()
    {
        Log::info('Accessing PurchaseOrder for BOM:', [
            'bom_id' => $this->id,
            'bom_unique_id' => $this->unique_id,
            'purchase_order_id' => $this->purchase_order_id,
        ]);

        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function operatorProficiency()
    {
        return $this->belongsTo(OperatorProficiency::class, 'operator_proficiency_id');
    }

    public function machineGroup()
    {
        return $this->belongsTo(MachineGroup::class, 'machine_group_id');
    }

    public function workOrders()
    {
        Log::info('Accessing Work Orders for BOM:', [
            'bom_id' => $this->id,
            'bom_unique_id' => $this->unique_id,
            'purchase_order_id' => $this->purchase_order_id,
            'work_orders_count' => $this->hasMany(WorkOrder::class)->count(),
            'work_orders' => $this->hasMany(WorkOrder::class)->get()->map(function ($wo) {
                return [
                    'id' => $wo->id,
                    'unique_id' => $wo->unique_id,
                    'status' => $wo->status,
                    'bom_id' => $wo->bom_id,
                ];
            })->toArray(),
        ]);

        return $this->hasMany(WorkOrder::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
