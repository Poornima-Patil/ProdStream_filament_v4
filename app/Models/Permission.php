<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission as ModelsPermission;

class Permission extends ModelsPermission
{
    use SoftDeletes;

    protected $fillable = ['name', 'group', 'guard_name', 'factory_id'];

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
