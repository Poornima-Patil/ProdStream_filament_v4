<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as ModelsRole;

class Role extends ModelsRole
{
    use SoftDeletes;

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
