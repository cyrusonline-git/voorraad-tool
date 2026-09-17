<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Depot extends Model
{
    protected $fillable = [
        'naam', 'area', 'business_unit', 'land', 'plaats', 'email_core', 'email',
        'depot_nummer', 'nummer_core', 'extra_nummers', 'actief', 'volgorde', 'gesynct_op',
    ];

    protected $casts = ['actief' => 'boolean', 'gesynct_op' => 'datetime', 'extra_nummers' => 'array'];

    /** Komt het nummer uit CORE (dan is het hier niet te wijzigen)? */
    public function nummerUitCore(): bool
    {
        return trim((string) $this->nummer_core) !== '';
    }

    /** Alle nummers van dit depot: hoofdnummer + aliassen. */
    public function alleNummers(): array
    {
        return array_values(array_unique(array_filter(array_merge([(string) $this->depot_nummer], (array) $this->extra_nummers))));
    }

    /** Alias → hoofdnummer voor alle depots (bv. 769 → 384). */
    public static function aliasKaart(): array
    {
        $kaart = [];
        foreach (static::whereNotNull('extra_nummers')->get() as $d) {
            foreach ((array) $d->extra_nummers as $alias) {
                if ($alias !== '' && $d->depot_nummer) {
                    $kaart[(string) $alias] = (string) $d->depot_nummer;
                }
            }
        }

        return $kaart;
    }

    /** Mailadres voor aanvragen: het CORE-adres; het lokale veld alleen als CORE er geen heeft. */
    public function mailadres(): ?string
    {
        return $this->email_core ?: $this->email;
    }

    public function scopeActief($q)
    {
        return $q->where('actief', true);
    }
}
