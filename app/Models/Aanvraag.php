<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aanvraag extends Model
{
    protected $table = 'aanvragen';
    protected $guarded = [];
    protected $casts = ['machines' => 'array', 'reactie_voor' => 'date', 'verhuurdatum' => 'date'];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
