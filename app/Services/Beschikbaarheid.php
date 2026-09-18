<?php

namespace App\Services;

use App\Models\Aanvraag;
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
     * "Order compleet?": per subgroep totaal nodig, wat het eigen depot levert,
     * wat al bij andere depots is aangevraagd (verstuurde aanvraagmails) en wat
     * nog open staat. Antwoorden van depots worden niet automatisch verwerkt.
     */
    public function orderStatus(Upload $upload, array $berekend, ?string $eigenDepotNr): array
    {
        $depotNamen = $berekend['depotNamen'] ?? [];
        $subs = [];
        foreach ($berekend['regels'] as $r) {
            $sub = (string) $r['regel']->subgroep_nr;
            $subs[$sub] ??= ['subgroep_nr' => $sub, 'omschrijving' => $r['regel']->omschrijving, 'nodig' => 0,
                'eigen' => 0, 'eigen_status' => ['available' => 0, 'in_service' => 0, 'in_repair' => 0],
                'advies' => [], 'aangevraagd' => [], 'geregeld' => 0, 'open' => 0];
            $subs[$sub]['nodig'] += $r['nodig'];
            foreach ($r['toewijzing'] as $m) {
                $nr = (string) $m->depot_nummer;
                if ($nr === (string) $eigenDepotNr) {
                    $subs[$sub]['eigen']++;
                    $subs[$sub]['eigen_status'][$m->status_code] = ($subs[$sub]['eigen_status'][$m->status_code] ?? 0) + 1;
                } else {
                    $subs[$sub]['advies'][$nr] ??= ['nr' => $nr, 'naam' => $depotNamen[$nr] ?? $m->depot_naam ?? $nr, 'aantal' => 0];
                    $subs[$sub]['advies'][$nr]['aantal']++;
                }
            }
        }
        // Verstuurde aanvragen voor dit contract/project
        $aanvragen = Aanvraag::where('upload_id', $upload->id)->where('status', 'verzonden')->orderBy('id')->get();
        $perDepotAangevraagd = [];
        foreach ($aanvragen as $a) {
            foreach ((array) $a->machines as $rij) {
                $sub = (string) ($rij['subgroep_nr'] ?? '');
                $aantal = (int) ($rij['aantal'] ?? 1);
                if ($sub === '' || ! isset($subs[$sub])) {
                    continue;
                }
                $nr = (string) $a->depot_nummer;
                $subs[$sub]['aangevraagd'][$nr] ??= ['nr' => $nr, 'naam' => $a->depot_naam, 'aantal' => 0, 'aanvraag_id' => $a->id, 'datum' => $a->created_at];
                $subs[$sub]['aangevraagd'][$nr]['aantal'] += $aantal;
                $perDepotAangevraagd[$nr] ??= ['aantal' => 0, 'laatste' => $a->created_at, 'aanvraag_id' => $a->id];
                $perDepotAangevraagd[$nr]['aantal'] += $aantal;
                $perDepotAangevraagd[$nr]['laatste'] = $a->created_at;
                $perDepotAangevraagd[$nr]['aanvraag_id'] = $a->id;
            }
        }
        foreach ($subs as &$x) {
            $x['aangevraagd_totaal'] = array_sum(array_column($x['aangevraagd'], 'aantal'));
            $x['geregeld'] = $x['eigen'] + $x['aangevraagd_totaal'];
            $x['open'] = max(0, $x['nodig'] - $x['geregeld']);
            $x['compleet'] = $x['open'] === 0;
            // Advies alleen voor depots waar nog niet (voldoende) is aangevraagd
            $x['advies'] = array_values(array_filter($x['advies'], fn ($a) => ($x['aangevraagd'][$a['nr']]['aantal'] ?? 0) < $a['aantal']));
            $x['aangevraagd'] = array_values($x['aangevraagd']);
            // Wat volgens de materieellijst NERGENS meer te halen is, ook niet met de adviezen
            $x['advies_totaal'] = array_sum(array_column($x['advies'], 'aantal'));
            $x['tekort'] = max(0, $x['open'] - $x['advies_totaal']);
        }
        unset($x);
        uasort($subs, fn ($a, $b) => [$a['compleet'], $a['subgroep_nr']] <=> [$b['compleet'], $b['subgroep_nr']]);

        return [
            'subgroepen' => array_values($subs),
            'aantal' => count($subs),
            'compleet' => count(array_filter($subs, fn ($x) => $x['compleet'])),
            'nodig' => array_sum(array_column($subs, 'nodig')),
            'geregeld' => array_sum(array_column($subs, 'geregeld')),
            'open' => array_sum(array_column($subs, 'open')),
            'tekort' => array_sum(array_column($subs, 'tekort')),
            'tekort_subgroepen' => count(array_filter($subs, fn ($x) => $x['tekort'] > 0)),
            'perDepotAangevraagd' => $perDepotAangevraagd,
        ];
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
                // Dezelfde subgroep uit meerdere orderregels wordt samengevoegd tot één regel met totaal aantal
                $perDepot[$nr]['regels'][$sub] ??= [
                    'regel' => $regel, 'subgroep_nr' => $sub, 'omschrijving' => $regel->omschrijving ?: ($m->subgroep_naam ?: $m->omschrijving),
                    'aantal' => 0, 'available' => 0, 'in_service' => 0, 'in_repair' => 0, 'contracten' => [],
                ];
                $perDepot[$nr]['regels'][$sub]['aantal']++;
                $perDepot[$nr]['regels'][$sub][$m->status_code]++;
                if ($regel->contract_nr) {
                    $perDepot[$nr]['regels'][$sub]['contracten'][$regel->contract_nr] = true;
                }
            }
        }

        foreach ($perDepot as &$d) {
            foreach ($d['regels'] as &$rg) {
                $rg['contracten'] = array_keys($rg['contracten']);
            }
            unset($rg);
            usort($d['regels'], fn ($a, $b) => $b['aantal'] <=> $a['aantal']);
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
