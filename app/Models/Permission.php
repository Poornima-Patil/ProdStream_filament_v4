<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission as ModelsPermission;

use App\Models\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Permission extends ModelsPermission
{
    use SoftDeletes;

    protected $fillable = ['name', 'group', 'guard_name', 'factory_id'];

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}

