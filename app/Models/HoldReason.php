<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HoldReason extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'factory_id',
    ];

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
