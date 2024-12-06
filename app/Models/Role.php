<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as ModelsRole;
use App\Models\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;



class Role extends ModelsRole
{
    use SoftDeletes;

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

}
