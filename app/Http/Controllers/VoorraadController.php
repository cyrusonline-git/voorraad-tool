<?php

namespace App\Http\Controllers;

use App\Models\Depot;
use App\Models\Materieel;
use App\Models\MinVoorraad;
use App\Models\Upload;
use App\Services\Voorraadstand;
use Illuminate\Http\Request;

class VoorraadController extends Controller
{
    /** Depotkeuze: gekozen → eigen depot van de gebruiker → eerste depot. */
    private function kiesDepot(Request $request): ?Depot
    {
        $depots = Depot::actief()->whereNotNull('depot_nummer')->orderBy('volgorde')->get();
        $nr = (string) $request->input('depot', '');
        if ($nr === '') {
            $nr = (string) (Depot::where('naam', session('eigen_depot'))->value('depot_nummer') ?? '');
        }

        return $depots->firstWhere('depot_nummer', $nr) ?: $depots->first();
    }

    /** Werkplaats: wat nakijken om de minimale voorraad te halen + aankomende orders. */
    public function werkplaats(Request $request, Voorraadstand $stand)
    {
        $depot = $this->kiesDepot($request);
        $materieel = Upload::where('type', 'materieel')->where('actueel', true)->first();
        $data = $depot ? $stand->depot($depot->depot_nummer, $request->boolean('tekort', true), (string) $request->input('q')) : null;
        $horizon = max(1, (int) setting('orders_horizon_dagen', 14));

        return view('voorraad.werkplaats', [
            'depot' => $depot, 'depots' => Depot::actief()->whereNotNull('depot_nummer')->orderBy('volgorde')->get(),
            'materieel' => $materieel, 'data' => $data,
            'orders' => $depot ? $stand->aankomendeOrders($depot->depot_nummer, $horizon) : [],
            'horizon' => $horizon,
        ]);
    }

    /** Manager/fleet: alle depots op één rij. */
    public function depots(Request $request, Voorraadstand $stand)
    {
        $area = (string) $request->input('area', '');
        $rijen = $stand->alleDepots($area ?: null);
        $materieel = Upload::where('type', 'materieel')->where('actueel', true)->first();
        $z = mb_strtolower(trim((string) $request->input('q')));
        if ($z !== '') {
            $rijen = array_values(array_filter($rijen, fn ($r) => str_contains(mb_strtolower($r['nummer'].' '.$r['depot']->naam), $z)));
        }

        return view('voorraad.depots', [
            'rijen' => $rijen, 'areas' => Depot::actief()->whereNotNull('area')->distinct()->orderBy('area')->pluck('area'),
            'area' => $area, 'materieel' => $materieel,
            'fleetStatus' => Materieel::actueel()->selectRaw('status_code, count(*) as n')->groupBy('status_code')->pluck('n', 'status_code'),
            'fleetArea' => Materieel::actueel()->selectRaw('area_raw, count(*) as n')->groupBy('area_raw')->orderByDesc('n')->pluck('n', 'area_raw'),
        ]);
    }

    /** Minimale voorraad instellen per depot. */
    public function minimaal(Request $request)
    {
        $depot = $this->kiesDepot($request);
        $sub = $depot ? Voorraadstand::subgroepenOpDepot($depot->depot_nummer) : collect();
        $minima = $depot ? MinVoorraad::where('depot_nummer', $depot->depot_nummer)->get()->keyBy('subgroep_nr') : collect();
        // Subgroepen met een minimum die (nu) niet op het depot staan ook tonen
        $rijen = $sub->map(fn ($s) => ['subgroep_nr' => $s->subgroep_nr, 'naam' => $s->naam, 'totaal' => (int) $s->totaal, 'available' => (int) $s->available, 'minimum' => (int) ($minima[$s->subgroep_nr]->minimum ?? 0)])->keyBy('subgroep_nr');
        foreach ($minima as $m) {
            if (! isset($rijen[$m->subgroep_nr])) {
                $rijen[$m->subgroep_nr] = ['subgroep_nr' => $m->subgroep_nr, 'naam' => $m->subgroep_naam, 'totaal' => 0, 'available' => 0, 'minimum' => (int) $m->minimum];
            }
        }
        $z = mb_strtolower(trim((string) $request->input('q')));
        $alleenIngesteld = $request->boolean('ingesteld');
        $rijen = $rijen->filter(fn ($r) => (! $alleenIngesteld || $r['minimum'] > 0) && ($z === '' || str_contains(mb_strtolower($r['subgroep_nr'].' '.$r['naam']), $z)))
            ->sortBy('subgroep_nr')->values();

        return view('voorraad.minimaal', [
            'depot' => $depot, 'depots' => Depot::actief()->whereNotNull('depot_nummer')->orderBy('volgorde')->get(),
            'rijen' => $rijen, 'aantalIngesteld' => $minima->where('minimum', '>', 0)->count(),
            'magBewerken' => in_array(actieve_rol(), ['werkplaats', 'manager', 'fleet', 'admin'], true),
        ]);
    }

    public function minimaalOpslaan(Request $request)
    {
        abort_unless(in_array(actieve_rol(), ['werkplaats', 'manager', 'fleet', 'admin'], true), 403);
        $depotNr = (string) $request->input('depot');
        abort_unless(Depot::where('depot_nummer', $depotNr)->exists(), 404);
        $namen = Voorraadstand::subgroepenOpDepot($depotNr)->pluck('naam', 'subgroep_nr');
        $wie = core_gebruiker()['name'] ?? null;
        $n = 0;
        foreach ((array) $request->input('minimum', []) as $sub => $waarde) {
            $sub = trim((string) $sub);
            $min = max(0, (int) $waarde);
            if ($sub === '') {
                continue;
            }
            if ($min === 0) {
                MinVoorraad::where('depot_nummer', $depotNr)->where('subgroep_nr', $sub)->delete();
            } else {
                MinVoorraad::updateOrCreate(['depot_nummer' => $depotNr, 'subgroep_nr' => $sub],
                    ['minimum' => $min, 'subgroep_naam' => $namen[$sub] ?? null, 'gewijzigd_door' => $wie]);
                $n++;
            }
        }
        // Extra subgroep toevoegen (die nu niet op het depot staat)
        $nieuw = preg_replace('/\D+/', '', (string) $request->input('nieuw_subgroep'));
        $nieuwMin = max(0, (int) $request->input('nieuw_minimum'));
        if ($nieuw !== '' && $nieuwMin > 0) {
            $naam = Materieel::actueel()->where('subgroep_nr', $nieuw)->value('subgroep_naam');
            MinVoorraad::updateOrCreate(['depot_nummer' => $depotNr, 'subgroep_nr' => $nieuw], ['minimum' => $nieuwMin, 'subgroep_naam' => $naam, 'gewijzigd_door' => $wie]);
            $n++;
        }

        return redirect()->route('voorraad.minimaal', ['depot' => $depotNr, 'ingesteld' => $request->boolean('ingesteld') ? 1 : null, 'q' => $request->input('q')])
            ->with('ok', "Minimale voorraad opgeslagen ($n subgroepen met een minimum).");
    }

    /** Minima van een ander depot overnemen (alleen waar nog niets staat). */
    public function minimaalKopieer(Request $request)
    {
        abort_unless(in_array(actieve_rol(), ['manager', 'fleet', 'admin'], true), 403);
        $van = (string) $request->input('van');
        $naar = (string) $request->input('depot');
        $n = 0;
        foreach (MinVoorraad::where('depot_nummer', $van)->where('minimum', '>', 0)->get() as $m) {
            if (! MinVoorraad::where('depot_nummer', $naar)->where('subgroep_nr', $m->subgroep_nr)->exists()) {
                MinVoorraad::create(['depot_nummer' => $naar, 'subgroep_nr' => $m->subgroep_nr, 'subgroep_naam' => $m->subgroep_naam, 'minimum' => $m->minimum, 'gewijzigd_door' => core_gebruiker()['name'] ?? null]);
                $n++;
            }
        }

        return redirect()->route('voorraad.minimaal', ['depot' => $naar])->with('ok', "$n minima overgenomen van depot $van.");
    }
}
