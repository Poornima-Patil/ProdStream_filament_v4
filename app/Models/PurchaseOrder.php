<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'part_number_id',
        'description',
        'QTY',
        'Unit Of Measurement',
        'supplierInfo',
        'price',
        'factory_id'
    ];
    public function partNumber()
    {
        return $this->belongsTo(PartNumber::class,'part_number_id');
    }

    public function boms() {
        return $this->hasMany(Bom::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
