<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materieel extends Model
{
    protected $table = 'materieel';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['laatste_uithuur' => 'date', 'extra' => 'array'];

    /** Statuscodes met weergavenaam en volgorde voor de binnendienst (eerst pakken wat direct kan). */
    public const STATUSSEN = [
        'available'   => ['Available', 'Beschikbaar', 1],
        'in_service'  => ['In Service', 'In service (nakijken)', 2],
        'in_repair'   => ['In Repair', 'In reparatie', 3],
        'in_transfer' => ['In Transfer', 'Onderweg', 4],
        'on_hire'     => ['On Hire', 'In huur', 5],
        'own_use'     => ['Own Use', 'Eigen gebruik', 6],
        'onbekend'    => ['?', 'Onbekend', 9],
    ];

    /** Alleen de actuele materieellijst (laatste upload). */
    public function scopeActueel($q)
    {
        return $q->whereIn('upload_id', Upload::where('type', 'materieel')->where('actueel', true)->select('id'));
    }
}
