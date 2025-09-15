<?php

namespace App\Models;

use Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'part_number_id',
        'QTY',
        'Unit Of Measurement',
        'supplierInfo',
        'price',
        'factory_id',
        'cust_id',
        'unique_id',
        'delivery_target_date',
    ];

    protected $with = ['boms.workOrders.operator.user'];

    public function partNumber()
    {
        return $this->belongsTo(PartNumber::class, 'part_number_id');
    }

    public function boms()
    {
        Log::info('Accessing BOMs for PO:', [
            'po_id' => $this->id,
            'po_unique_id' => $this->unique_id,
            'boms_count' => $this->hasMany(Bom::class)->count(),
            'boms' => $this->hasMany(Bom::class)->get()->map(function ($bom) {
                return [
                    'id' => $bom->id,
                    'unique_id' => $bom->unique_id,
                    'purchase_order_id' => $bom->purchase_order_id,
                ];
            })->toArray(),
        ]);

        return $this->hasMany(Bom::class);
    }

    public function workOrders()
    {
        Log::info('Accessing workOrders relationship for PO:', [
            'po_id' => $this->id,
            'has_boms' => $this->boms->isNotEmpty(),
            'boms_count' => $this->boms->count(),
        ]);

        $workOrders = $this->hasManyThrough(
            WorkOrder::class,
            Bom::class,
            'purchase_order_id', // Foreign key on BOMs table
            'bom_id', // Foreign key on WorkOrders table
            'id', // Local key on PurchaseOrders table
            'id' // Local key on BOMs table
        );

        Log::info('WorkOrders query results:', [
            'po_id' => $this->id,
            'work_orders_count' => $workOrders->count(),
            'work_orders' => $workOrders->get()->map(function ($wo) {
                return [
                    'id' => $wo->id,
                    'unique_id' => $wo->unique_id,
                    'status' => $wo->status,
                    'bom_id' => $wo->bom_id,
                ];
            })->toArray(),
        ]);

        return $workOrders;
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function customer()
    {
        return $this->belongsTo(CustomerInformation::class, 'cust_id', 'id');
    }

    public function customerInformation()
    {
        return $this->belongsTo(CustomerInformation::class, 'cust_id', 'id');
    }
}
