<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Upload extends Model
{
    public const TYPES = ['materieel' => 'Materieellijst', 'contract' => 'Contract', 'project' => 'Project'];

    protected $fillable = [
        'type', 'bestandsnaam', 'pad', 'referentie', 'omschrijving', 'depot_nummer', 'aantal_rijen',
        'aantal_overgeslagen', 'meldingen', 'actueel', 'user_id', 'gebruiker_naam',
    ];

    protected $casts = ['meldingen' => 'array', 'actueel' => 'boolean'];

    public function regels(): HasMany
    {
        return $this->hasMany(OrderRegel::class);
    }

    public function materieel(): HasMany
    {
        return $this->hasMany(Materieel::class);
    }

    public function typeNaam(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
