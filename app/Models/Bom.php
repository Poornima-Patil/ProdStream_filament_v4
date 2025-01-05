<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Factory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bom extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'purchase_order_id',
        'requirement_pkg',
        'process_flowchart',
        'machine_id',
        'operator_proficiency_id',
        'lead_time',
        'status',
        'factory_id'
    ];

public function purchaseOrder()
{
    return $this->belongsTo(PurchaseOrder::class,'purchase_order_id');
}
public function operatorProficiency()
{
    return $this->belongsTo(OperatorProficiency::class, 'operator_proficiency_id');
}

public function machine()
{
    return $this->belongsTo(Machine::class, 'id');
}

public function workOrders() {
    return $this->hasMany(WorkOrder::class);
}

public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
