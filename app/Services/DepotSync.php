<?php

namespace App\Services;

use App\Models\Depot;
use Illuminate\Support\Facades\Cache;

/**
 * Depots en areas spiegelen uit Boels CORE (Beheer → Infrastructuur is de
 * leidende namenlijst). Depots die uit CORE verdwijnen worden hier op
 * inactief gezet, nooit verwijderd (uploads/instellingen verwijzen ernaar).
 */
class DepotSync
{
    public function __construct(private CoreSso $sso)
    {
    }

    /** Synchroniseren; geeft het aantal verwerkte depots of null bij een storing. */
    public function sync(): ?int
    {
        $infra = $this->sso->infrastructure();
        if (! is_array($infra) || empty($infra)) {
            return null;
        }
        $gezien = [];
        $volgorde = 0;
        foreach ($infra as $bu) {
            foreach ($bu['areas'] ?? [] as $area) {
                foreach ($area['depots'] ?? [] as $d) {
                    $naam = trim((string) ($d['name'] ?? ''));
                    if ($naam === '') {
                        continue;
                    }
                    $gezien[] = $naam;
                    Depot::updateOrCreate(['naam' => $naam], [
                        'area' => $area['name'] ?? null,
                        'business_unit' => $bu['name'] ?? null,
                        'land' => $area['country'] ?? null,
                        'plaats' => $d['city'] ?? null,
                        'email_core' => $d['email'] ?? null,
                        'actief' => true,
                        'volgorde' => $volgorde++,
                        'gesynct_op' => now(),
                    ]);
                }
            }
        }
        if ($gezien) {
            Depot::whereNotIn('naam', $gezien)->update(['actief' => false]);
        }
        Cache::put('depots.gesynct_op', now()->timestamp, 86400);

        return count($gezien);
    }

    /** Automatisch (max 1x per uur) synchroniseren als het lang geleden is of de tabel leeg is. */
    public function syncIndienNodig(): void
    {
        $laatst = (int) Cache::get('depots.gesynct_op', 0);
        if (Depot::count() === 0 || time() - $laatst > 3600) {
            try {
                $this->sync();
            } catch (\Throwable $e) {
                report($e);
            }
            Cache::put('depots.gesynct_op', now()->timestamp, 86400);
        }
    }
}
