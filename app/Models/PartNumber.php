<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartNumber extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'partnumber',
        'revision',
        'description',
        'factory_id'
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->validateUniquePartNumber();
        });

        static::updating(function ($model) {
            $model->validateUniquePartNumber();
        });
    }

    public function validateUniquePartNumber()
    {
        $exists = self::where('partnumber', $this->partnumber)
            ->where('revision', $this->revision)
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'partnumber' => 'The combination of Part Number and Revision must be unique.',
            ]);
        }
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
