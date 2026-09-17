<?php

namespace App\Services;

use App\Models\Depot;
use App\Models\Materieel;
use App\Models\OrderRegel;
use App\Models\Upload;
use Illuminate\Support\Collection;

/**
 * Voor een contract/project: per te zoeken regel de machines uit de actuele
 * materieellijst: eerst het eigen depot volledig (Available, dan In Service),
 * daarna andere depots (Available, dan In Service), In Repair als laatste.
 * Machines worden niet dubbel toegewezen
 * over regels met dezelfde subgroep.
 */
class Beschikbaarheid
{
    public const TIERS = ['available', 'in_service', 'in_repair'];

    /**
     * Welke regels moeten gezocht worden? Alleen status "Niet toegekend".
     * Toegekend/Allocated staat al vast, Inhuur/On hire is al geregeld,
     * Uit-verhuur en Goederen in zijn niet nodig (Wim, 17-09-2026).
     */
    public static function teZoeken(Upload $upload): Collection
    {
        return OrderRegel::where('upload_id', $upload->id)
            ->where('status_code', 'not_allocated')
            ->whereNotNull('subgroep_nr')
            ->orderBy('regel_nr')->get();
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
            // Volgorde (Wim, 17-09-2026): eerst het eigen depot volledig (Available, dan
            // In Service — die is snel inzetbaar), dan andere depots (Available, dan In
            // Service; depot met de meeste voorraad eerst), en pas als laatste In Repair.
            $stappen = [
                ['eigen', 'available'], ['eigen', 'in_service'],
                ['ander', 'available'], ['ander', 'in_service'],
                ['eigen', 'in_repair'], ['ander', 'in_repair'],
            ];
            foreach ($stappen as [$waar, $tier]) {
                if ($rest <= 0) {
                    break;
                }
                $stapPool = $pool->where('status_code', $tier)->filter(fn ($m) => $waar === 'eigen'
                    ? (string) $m->depot_nummer === (string) $eigenDepotNr
                    : (string) $m->depot_nummer !== (string) $eigenDepotNr);
                // groupBy maakt van "759" het getal 759 — sleutels daarom als tekst behandelen
                $volgorde = $stapPool->groupBy('depot_nummer')->sortByDesc(fn ($groep) => $groep->count());
                foreach ($volgorde as $groep) {
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
            ])->sortByDesc(fn ($v, $nr) => ((string) $nr === (string) $eigenDepotNr ? 1000000 : 0) + $v['available'] * 1000 + $v['in_service'] * 10 + $v['in_repair']);

            $uitRegels[] = [
                'regel' => $regel,
                'nodig' => $nodig,
                'gevonden' => $gevonden,
                'tekort' => max(0, $nodig - $gevonden),
                'toewijzing' => $toewijzing,
                'voorraad' => $voorraad,
                'niet_inzetbaar' => ($nietInzetbaar[$sub] ?? collect())->pluck('n', 'status_code')->all(),
            ];

            // Per depot op SUBGROEP-niveau (aanvragen gaan altijd per subgroep, nooit per machinenummer)
            foreach ($toewijzing as $m) {
                $nr = $m->depot_nummer ?: '?';
                $perDepot[$nr]['depot_nummer'] = $nr;
                $perDepot[$nr]['depot_naam'] = $depotNamen[$nr] ?? $m->depot_naam ?? $nr;
                $perDepot[$nr]['depot_id'] = $depotIds[$nr] ?? null;
                $perDepot[$nr]['eigen'] = (string) $nr === (string) $eigenDepotNr;
                $sleutel = $sub.'|'.$regel->id;
                $perDepot[$nr]['regels'][$sleutel] ??= [
                    'regel' => $regel, 'subgroep_nr' => $sub, 'omschrijving' => $regel->omschrijving ?: ($m->subgroep_naam ?: $m->omschrijving),
                    'aantal' => 0, 'available' => 0, 'in_service' => 0, 'in_repair' => 0,
                ];
                $perDepot[$nr]['regels'][$sleutel]['aantal']++;
                $perDepot[$nr]['regels'][$sleutel][$m->status_code]++;
            }
        }

        foreach ($perDepot as &$d) {
            $d['regels'] = array_values($d['regels']);
            $d['aantal'] = array_sum(array_column($d['regels'], 'aantal'));
        }
        unset($d);
        uasort($perDepot, fn ($a, $b) => [$b['eigen'], $b['aantal']] <=> [$a['eigen'], $a['aantal']]);

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
