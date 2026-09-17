<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Depot extends Model
{
    protected $fillable = [
        'naam', 'area', 'business_unit', 'land', 'plaats', 'email_core', 'email',
        'depot_nummer', 'actief', 'volgorde', 'gesynct_op',
    ];

    protected $casts = ['actief' => 'boolean', 'gesynct_op' => 'datetime'];

    /** Mailadres voor aanvragen: eigen overschrijving gaat vóór het CORE-adres. */
    public function mailadres(): ?string
    {
        return $this->email ?: $this->email_core;
    }

    public function scopeActief($q)
    {
        return $q->where('actief', true);
    }
}
