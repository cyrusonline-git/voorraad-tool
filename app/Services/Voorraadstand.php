<?php

namespace App\Services;

use App\Models\Depot;
use App\Models\Materieel;
use App\Models\MinVoorraad;
use App\Models\OrderRegel;
use App\Models\Reservering;
use Illuminate\Support\Collection;

/**
 * Minimale voorraad bewaken: per depot per subgroep het minimum vergelijken
 * met wat er Available staat; het tekort moet de werkplaats aanvullen door
 * machines In Service / In Repair na te kijken. Plus aankomende orders
 * (Niet toegekend, ingangsdatum binnen de horizon) zonder voorraad.
 */
class Voorraadstand
{
    /** Depotnummer uit een contractnummer (7590019510 → 759). */
    public static function depotUitContract(?string $contractNr): ?string
    {
        return preg_match('/^(\d{3})\d{4,}$/', (string) $contractNr, $m) ? $m[1] : null;
    }

    /**
     * Voorraadstand van één depot: per subgroep tellingen, minimum, tekort en
     * de na te kijken machines (In Service eerst, dan In Repair).
     */
    public function depot(string $depotNr, bool $alleenTekorten = false, string $zoek = ''): array
    {
        $machines = Materieel::actueel()->where('depot_nummer', $depotNr)->get();
        $perSub = $machines->groupBy('subgroep_nr');
        $minima = MinVoorraad::where('depot_nummer', $depotNr)->get()->keyBy('subgroep_nr');

        $subgroepen = $perSub->keys()->merge($minima->keys())->unique()->filter()->values();
        $rijen = [];
        foreach ($subgroepen as $sub) {
            $g = $perSub[$sub] ?? collect();
            $min = (int) ($minima[$sub]->minimum ?? 0);
            $naam = $minima[$sub]->subgroep_naam ?? $g->first()?->subgroep_naam ?? $g->first()?->omschrijving;
            $available = $g->where('status_code', 'available')->count();
            $tekort = max(0, $min - $available);
            $kandidaten = $g->whereIn('status_code', ['in_service', 'in_repair'])
                ->sortBy(fn ($m) => ($m->status_code === 'in_service' ? '0' : '1').($m->laatste_uithuur?->format('Y-m-d') ?? '9999'))->values();
            $rijen[] = [
                'subgroep_nr' => $sub, 'naam' => $naam, 'minimum' => $min, 'available' => $available,
                'in_service' => $g->where('status_code', 'in_service')->count(),
                'in_repair' => $g->where('status_code', 'in_repair')->count(),
                'on_hire' => $g->where('status_code', 'on_hire')->count(),
                'overig' => $g->whereNotIn('status_code', ['available', 'in_service', 'in_repair', 'on_hire'])->count(),
                'totaal' => $g->count(),
                'tekort' => $tekort,
                'haalbaar' => min($tekort, $kandidaten->count()),
                'kandidaten' => $tekort > 0 ? $kandidaten : collect(),
                'na_te_kijken' => $tekort > 0 ? $kandidaten->take($tekort) : collect(),
            ];
        }
        $z = mb_strtolower(trim($zoek));
        $rijen = array_values(array_filter($rijen, function ($r) use ($alleenTekorten, $z) {
            if ($alleenTekorten && $r['tekort'] === 0) {
                return false;
            }

            return $z === '' || str_contains(mb_strtolower($r['subgroep_nr'].' '.$r['naam']), $z);
        }));
        usort($rijen, fn ($a, $b) => [$b['tekort'] > 0, $b['tekort'], $a['subgroep_nr']] <=> [$a['tekort'] > 0, $a['tekort'], $b['subgroep_nr']]);

        return [
            'rijen' => $rijen,
            'ingesteld' => $minima->where('minimum', '>', 0)->count(),
            'tekorten' => count(array_filter($rijen, fn ($r) => $r['tekort'] > 0)),
            'tekort_totaal' => array_sum(array_column($rijen, 'tekort')),
            'na_te_kijken' => array_sum(array_map(fn ($r) => $r['na_te_kijken']->count(), $rijen)),
            'status' => [
                'available' => $machines->where('status_code', 'available')->count(),
                'in_service' => $machines->where('status_code', 'in_service')->count(),
                'in_repair' => $machines->where('status_code', 'in_repair')->count(),
                'on_hire' => $machines->where('status_code', 'on_hire')->count(),
                'totaal' => $machines->count(),
            ],
        ];
    }

    /**
     * Aankomende orders voor dit depot (Niet toegekend, ingangsdatum binnen de
     * horizon): alleen subgroepen waar minder Available staat dan nodig én waar
     * machines In Service / In Repair staan — die moet de werkplaats nakijken.
     * Wat de binnendienst/expeditie nog toewijst is hier niet relevant.
     */
    public function aankomendeOrders(string $depotNr, int $horizonDagen): array
    {
        $tot = now()->addDays($horizonDagen)->toDateString();
        // Bron 1: reserveringen (quotes) voor dit depot; bron 2: geüploade contracten/projecten
        // met regels 'Niet toegekend' (alleen contracten die niet al in de reserveringen zitten).
        $reserveringen = Reservering::actueel()->where('depot_nummer', $depotNr)->whereNotNull('subgroep_nr')
            ->whereNotNull('startdatum')->where('startdatum', '<=', $tot)->orderBy('startdatum')->get();
        $bekend = $reserveringen->pluck('contract_nr')->filter()->unique()->all();
        $regels = OrderRegel::where('status_code', 'not_allocated')
            ->whereNotNull('subgroep_nr')
            ->whereNotNull('verhuurdatum')
            ->where('verhuurdatum', '<=', $tot)
            ->orderBy('verhuurdatum')->get()
            ->filter(fn ($r) => ($r->vestiging_nr ?: self::depotUitContract($r->contract_nr)) === $depotNr)
            ->reject(fn ($r) => $r->contract_nr && in_array($r->contract_nr, $bekend, true));
        $regels = $regels->concat($reserveringen->map(fn ($x) => (object) [
            'subgroep_nr' => $x->subgroep_nr, 'omschrijving' => $x->omschrijving, 'aantal' => $x->aantal,
            'verhuurdatum' => $x->startdatum, 'contract_nr' => $x->contract_nr, 'project_nr' => null,
        ]));
        if ($regels->isEmpty()) {
            return [];
        }
        $subs = $regels->pluck('subgroep_nr')->unique()->all();
        $machines = Materieel::actueel()->where('depot_nummer', $depotNr)->whereIn('subgroep_nr', $subs)
            ->whereIn('status_code', ['available', 'in_service', 'in_repair'])->get()->groupBy('subgroep_nr');
        $uit = [];
        foreach ($regels->groupBy('subgroep_nr') as $sub => $rs) {
            $g = $machines[$sub] ?? collect();
            $nodig = (int) ceil($rs->sum('aantal'));
            $available = $g->where('status_code', 'available')->count();
            $tekort = max(0, $nodig - $available);
            $kandidaten = $g->whereIn('status_code', ['in_service', 'in_repair'])
                ->sortBy(fn ($m) => ($m->status_code === 'in_service' ? '0' : '1').($m->laatste_uithuur?->format('Y-m-d') ?? '9999'))->values();
            if ($tekort === 0 || $kandidaten->isEmpty()) {
                continue; // genoeg Available, of niets om na te kijken
            }
            $uit[] = [
                'subgroep_nr' => $sub, 'omschrijving' => $rs->first()->omschrijving, 'nodig' => $nodig,
                'available' => $available, 'in_service' => $g->where('status_code', 'in_service')->count(), 'in_repair' => $g->where('status_code', 'in_repair')->count(),
                'tekort' => $tekort,
                'na_te_kijken' => $kandidaten->take($tekort),
                'rest' => max(0, $tekort - $kandidaten->count()),
                'eerste_datum' => $rs->min('verhuurdatum'),
                'orders' => $rs->map(fn ($r) => ($r->contract_nr ?: '?').($r->project_nr ? ' / '.$r->project_nr : ''))->unique()->values()->all(),
            ];
        }
        usort($uit, fn ($a, $b) => $a['eerste_datum'] <=> $b['eerste_datum']);

        return $uit;
    }

    /** Overzicht voor manager/fleet: alle depots op één rij. */
    public function alleDepots(?string $area = null): array
    {
        $depots = Depot::actief()->whereNotNull('depot_nummer')->when($area, fn ($q) => $q->where('area', $area))->orderBy('area')->orderBy('volgorde')->get();
        $tellingen = Materieel::actueel()->selectRaw('depot_nummer, status_code, count(*) as n')->groupBy('depot_nummer', 'status_code')->get()
            ->groupBy('depot_nummer')->map(fn ($g) => $g->pluck('n', 'status_code')->all());
        $minima = MinVoorraad::where('minimum', '>', 0)->get()->groupBy('depot_nummer');
        $availablePerSub = Materieel::actueel()->where('status_code', 'available')->selectRaw('depot_nummer, subgroep_nr, count(*) as n')
            ->groupBy('depot_nummer', 'subgroep_nr')->get()->groupBy('depot_nummer')->map(fn ($g) => $g->pluck('n', 'subgroep_nr')->all());
        $uit = [];
        foreach ($depots as $d) {
            $nr = $d->depot_nummer;
            $t = $tellingen[$nr] ?? [];
            $mins = $minima[$nr] ?? collect();
            $tekorten = 0;
            $tekortTotaal = 0;
            foreach ($mins as $m) {
                $av = (int) ($availablePerSub[$nr][$m->subgroep_nr] ?? 0);
                if ($av < $m->minimum) {
                    $tekorten++;
                    $tekortTotaal += $m->minimum - $av;
                }
            }
            $uit[] = [
                'depot' => $d, 'nummer' => $nr, 'ingesteld' => $mins->count(), 'tekorten' => $tekorten, 'tekort_totaal' => $tekortTotaal,
                'available' => (int) ($t['available'] ?? 0), 'in_service' => (int) ($t['in_service'] ?? 0), 'in_repair' => (int) ($t['in_repair'] ?? 0),
                'on_hire' => (int) ($t['on_hire'] ?? 0), 'totaal' => array_sum($t),
                'ok' => $mins->count() > 0 && $tekorten === 0,
            ];
        }

        return $uit;
    }

    /** Subgroepen die op een depot voorkomen (voor de instelpagina). */
    public static function subgroepenOpDepot(string $depotNr): Collection
    {
        return Materieel::actueel()->where('depot_nummer', $depotNr)->whereNotNull('subgroep_nr')
            ->selectRaw('subgroep_nr, max(subgroep_naam) as naam, count(*) as totaal, sum(case when status_code = \'available\' then 1 else 0 end) as available')
            ->groupBy('subgroep_nr')->orderBy('subgroep_nr')->get();
    }
}
