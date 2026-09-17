<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRegel extends Model
{
    protected $table = 'order_regels';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['afleverdatum' => 'date', 'verhuurdatum' => 'date', 'extra' => 'array'];

    public const STATUSSEN = [
        'not_allocated' => 'Niet toegekend',
        'allocated'     => 'Toegekend',
        'on_hire'       => 'In huur',
        'off_hire'      => 'Uit-verhuur',
        'goods_in'      => 'Goederen in',
        'onbekend'      => 'Onbekend',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
