<?php

namespace App\Services;

use App\Models\Depot;
use App\Models\Materieel;
use App\Models\OrderRegel;
use App\Models\Upload;
use Illuminate\Support\Collection;

/**
 * Voor een contract/project: per te zoeken regel de machines uit de actuele
 * materieellijst, eerst op het eigen depot, daarna per depot; statusvolgorde
 * Available → In Service → In Repair. Machines worden niet dubbel toegewezen
 * over regels met dezelfde subgroep.
 */
class Beschikbaarheid
{
    public const TIERS = ['available', 'in_service', 'in_repair'];

    /** Welke regels van deze upload moeten gezocht worden? */
    public static function teZoeken(Upload $upload): Collection
    {
        $ookToegekendZonderNr = (bool) (int) setting('zoek_toegekend_zonder_nummer', 1);

        return OrderRegel::where('upload_id', $upload->id)
            ->whereNotNull('subgroep_nr')
            ->orderBy('regel_nr')->get()
            ->filter(function (OrderRegel $r) use ($ookToegekendZonderNr) {
                if ($r->status_code === 'not_allocated') {
                    return true;
                }
                if ($ookToegekendZonderNr && $r->status_code === 'allocated') {
                    return $r->artikel_nr === null || $r->artikel_nr === $r->subgroep_nr;
                }

                return false;
            })->values();
    }

    /**
     * @return array{regels: array, perDepot: array, eigenDepot: ?string, tekorten: int, totaalNodig: float, totaalGevonden: float}
     */
    public function bereken(Upload $upload, ?string $eigenDepotNr): array
    {
        $regels = self::teZoeken($upload);
        $subgroepen = $regels->pluck('subgroep_nr')->unique()->values()->all();

        // Kandidaten uit de materieellijst, gegroepeerd per subgroep
        $kandidaten = Materieel::actueel()
            ->whereIn('subgroep_nr', $subgroepen)
            ->whereIn('status_code', self::TIERS)
            ->orderBy('laatste_uithuur')
            ->get()
            ->groupBy('subgroep_nr');

        // Ook alles wat er wél is maar niet inzetbaar (voor de uitleg per regel)
        $nietInzetbaar = Materieel::actueel()
            ->whereIn('subgroep_nr', $subgroepen)
            ->whereNotIn('status_code', self::TIERS)
            ->selectRaw('subgroep_nr, status_code, count(*) as n')
            ->groupBy('subgroep_nr', 'status_code')->get()
            ->groupBy('subgroep_nr');

        $depotNamen = Depot::whereNotNull('depot_nummer')->pluck('naam', 'depot_nummer')->all();
        $depotIds = Depot::whereNotNull('depot_nummer')->pluck('id', 'depot_nummer')->all();

        $gebruikt = [];
        $uitRegels = [];
        $perDepot = [];
        $tekorten = 0;
        $totaalNodig = 0;
        $totaalGevonden = 0;

        foreach ($regels as $regel) {
            $nodig = (int) ceil($regel->aantal);
            $totaalNodig += $nodig;
            $sub = $regel->subgroep_nr;
            $pool = ($kandidaten[$sub] ?? collect())->reject(fn ($m) => isset($gebruikt[$m->uniek_nr]));

            $toewijzing = [];
            $rest = $nodig;
            foreach (self::TIERS as $tier) {
                if ($rest <= 0) {
                    break;
                }
                $tierPool = $pool->where('status_code', $tier);
                // Eigen depot eerst, daarna depots met de meeste voorraad van deze subgroep
                $volgorde = $tierPool->groupBy('depot_nummer')
                    ->sortBy(fn ($groep, $nr) => ($nr === $eigenDepotNr ? '0' : '1').str_pad((string) (100000 - $groep->count()), 6, '0', STR_PAD_LEFT));
                foreach ($volgorde as $depotNr => $groep) {
                    foreach ($groep as $m) {
                        if ($rest <= 0) {
                            break 2;
                        }
                        $gebruikt[$m->uniek_nr] = true;
                        $toewijzing[] = $m;
                        $rest--;
                    }
                }
            }
            $gevonden = count($toewijzing);
            $totaalGevonden += $gevonden;
            if ($gevonden < $nodig) {
                $tekorten++;
            }

            // Overzicht van alle voorraad van deze subgroep per depot (ook wat niet toegewezen is)
            $voorraad = ($kandidaten[$sub] ?? collect())->groupBy('depot_nummer')->map(fn ($g) => [
                'available' => $g->where('status_code', 'available')->count(),
                'in_service' => $g->where('status_code', 'in_service')->count(),
                'in_repair' => $g->where('status_code', 'in_repair')->count(),
            ])->sortByDesc(fn ($v, $nr) => ($nr === $eigenDepotNr ? 1000000 : 0) + $v['available'] * 1000 + $v['in_service'] * 10 + $v['in_repair']);

            $uitRegels[] = [
                'regel' => $regel,
                'nodig' => $nodig,
                'gevonden' => $gevonden,
                'tekort' => max(0, $nodig - $gevonden),
                'toewijzing' => $toewijzing,
                'voorraad' => $voorraad,
                'niet_inzetbaar' => ($nietInzetbaar[$sub] ?? collect())->pluck('n', 'status_code')->all(),
            ];

            foreach ($toewijzing as $m) {
                $nr = $m->depot_nummer ?: '?';
                $perDepot[$nr]['depot_nummer'] = $nr;
                $perDepot[$nr]['depot_naam'] = $depotNamen[$nr] ?? $m->depot_naam ?? $nr;
                $perDepot[$nr]['depot_id'] = $depotIds[$nr] ?? null;
                $perDepot[$nr]['eigen'] = $nr === $eigenDepotNr;
                $perDepot[$nr]['machines'][] = ['regel' => $regel, 'machine' => $m];
            }
        }

        uasort($perDepot, fn ($a, $b) => [$b['eigen'], count($b['machines'])] <=> [$a['eigen'], count($a['machines'])]);

        return [
            'regels' => $uitRegels,
            'perDepot' => $perDepot,
            'eigenDepot' => $eigenDepotNr,
            'tekorten' => $tekorten,
            'totaalNodig' => $totaalNodig,
            'totaalGevonden' => $totaalGevonden,
            'depotNamen' => $depotNamen,
        ];
    }
}
