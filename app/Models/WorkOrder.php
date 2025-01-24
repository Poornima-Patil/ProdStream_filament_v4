<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'bom_id',
        'qty',
        'machine_id',
        'operator_id',
        'start_time',
        'end_time',
        'status',
        'ok_qtys',
        'scrapped_qtys',
        'unique_id',
        'factory_id',
    ];

    public function bom()
    {
        return $this->belongsTo(Bom::class, 'bom_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class, 'operator_id');
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function scrappedQuantities()
    {
        return $this->hasMany(ScrappedQuantity::class);
    }
}
