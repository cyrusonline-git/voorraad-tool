<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservering extends Model
{
    protected $table = 'reserveringen';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['startdatum' => 'date'];

    /** Alleen de actuele reserveringenlijst (laatste upload). */
    public function scopeActueel($q)
    {
        return $q->whereIn('upload_id', Upload::where('type', 'reserveringen')->where('actueel', true)->select('id'));
    }

    /** Reserveringen die binnen de horizon starten (vandaag t/m vandaag + dagen). */
    public function scopeBinnenHorizon($q, int $dagen)
    {
        return $q->whereNotNull('startdatum')->where('startdatum', '<=', now()->addDays($dagen)->toDateString());
    }

    public static function horizonDagen(): int
    {
        return max(1, (int) setting('reservering_horizon_dagen', 21));
    }
}
