<?php

namespace App\Http\Controllers;

use App\Models\Aanvraag;
use App\Models\Depot;
use App\Models\Materieel;
use App\Models\MinVoorraad;
use App\Models\OrderRegel;
use App\Models\Upload;
use App\Models\User;
use App\Services\Beschikbaarheid;
use App\Services\DepotKoppeling;
use App\Services\DepotSync;
use App\Services\Voorraadstand;
use Illuminate\Http\Request;

/** Startpagina per rol: de cijfers en snelkoppelingen die voor die rol tellen. */
class DashboardController extends Controller
{
    public function index(Request $request, DepotSync $depotSync, Voorraadstand $stand)
    {
        $depotSync->syncIndienNodig();
        $rol = actieve_rol();
        $materieel = Upload::where('type', 'materieel')->where('actueel', true)->first();
        $basis = [
            'materieel' => $materieel,
            'materieelAantal' => $materieel ? Materieel::where('upload_id', $materieel->id)->count() : 0,
            'eigenDepot' => Depot::where('naam', session('eigen_depot'))->first(),
        ];

        return match ($rol) {
            'binnendienst' => view('dashboard.binnendienst', $basis + $this->binnendienst()),
            'werkplaats' => view('dashboard.werkplaats', $basis + $this->werkplaats($basis['eigenDepot'], $stand)),
            'manager' => view('dashboard.manager', $basis + $this->manager($stand)),
            'fleet' => view('dashboard.fleet', $basis + $this->fleet($stand)),
            'admin' => view('dashboard.admin', $basis + $this->admin()),
            default => abort(500, "Geen dashboard voor rol $rol"),
        };
    }

    private function binnendienst(): array
    {
        $uploads = Upload::whereIn('type', ['contract', 'project'])->latest()->limit(8)->get()
            ->map(function ($u) {
                $u->te_zoeken = Beschikbaarheid::teZoeken($u)->count();

                return $u;
            });
        $teZoekenTotaal = OrderRegel::where('status_code', 'not_allocated')->count();

        return [
            'uploads' => $uploads,
            'openOrders' => $uploads->where('te_zoeken', '>', 0)->count(),
            'teZoekenTotaal' => $teZoekenTotaal,
            'aanvragenWeek' => Aanvraag::where('created_at', '>=', now()->subDays(7))->count(),
            'aanvragen' => Aanvraag::latest()->limit(5)->get(),
        ];
    }

    private function werkplaats(?Depot $depot, Voorraadstand $stand): array
    {
        $depot = $depot?->depot_nummer ? $depot : Depot::actief()->whereNotNull('depot_nummer')->orderBy('volgorde')->first();
        if (! $depot) {
            return ['depot' => null, 'data' => null, 'orders' => []];
        }
        $data = $stand->depot($depot->depot_nummer, true);
        $horizon = max(1, (int) setting('orders_horizon_dagen', 14));

        return [
            'depot' => $depot,
            'data' => $data,
            'topRijen' => array_slice($data['rijen'], 0, 6),
            'orders' => array_slice($stand->aankomendeOrders($depot->depot_nummer, $horizon), 0, 5),
            'horizon' => $horizon,
        ];
    }

    private function manager(Voorraadstand $stand): array
    {
        $rijen = $stand->alleDepots();
        $areas = [];
        foreach ($rijen as $r) {
            $a = $r['depot']->area ?: 'Zonder area';
            $areas[$a] ??= ['depots' => 0, 'tekort' => 0, 'ok' => 0, 'niet_ingesteld' => 0, 'in_service' => 0, 'in_repair' => 0, 'available' => 0];
            $areas[$a]['depots']++;
            $areas[$a]['in_service'] += $r['in_service'];
            $areas[$a]['in_repair'] += $r['in_repair'];
            $areas[$a]['available'] += $r['available'];
            if ($r['ingesteld'] === 0) {
                $areas[$a]['niet_ingesteld']++;
            } elseif ($r['ok']) {
                $areas[$a]['ok']++;
            } else {
                $areas[$a]['tekort']++;
            }
        }
        $metTekort = array_values(array_filter($rijen, fn ($r) => $r['tekorten'] > 0));
        usort($metTekort, fn ($a, $b) => $b['tekort_totaal'] <=> $a['tekort_totaal']);

        return [
            'depots' => $rijen,
            'areas' => $areas,
            'depotsTekort' => array_slice($metTekort, 0, 8),
            'aantalOk' => count(array_filter($rijen, fn ($r) => $r['ok'])),
            'aantalTekort' => count($metTekort),
            'aantalNietIngesteld' => count(array_filter($rijen, fn ($r) => $r['ingesteld'] === 0)),
            'totaalService' => array_sum(array_column($rijen, 'in_service')),
            'totaalRepair' => array_sum(array_column($rijen, 'in_repair')),
            'aanvragen' => Aanvraag::latest()->limit(5)->get(),
        ];
    }

    private function fleet(Voorraadstand $stand): array
    {
        $rijen = $stand->alleDepots();

        return [
            'depots' => $rijen,
            'fleetStatus' => Materieel::actueel()->selectRaw('status_code, count(*) as n')->groupBy('status_code')->pluck('n', 'status_code'),
            'fleetArea' => Materieel::actueel()->selectRaw('area_raw, count(*) as n')->groupBy('area_raw')->orderByDesc('n')->pluck('n', 'area_raw'),
            'topSubgroepen' => Materieel::actueel()->selectRaw('subgroep_nr, max(subgroep_naam) as naam, count(*) as n')->groupBy('subgroep_nr')->orderByDesc('n')->limit(8)->get(),
            'aantalTekort' => count(array_filter($rijen, fn ($r) => $r['tekorten'] > 0)),
            'aanvragenWeek' => Aanvraag::where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    private function admin(): array
    {
        $depots = Depot::all();
        $gezien = DepotKoppeling::uitMaterieel();
        $bekend = [];
        foreach ($depots as $d) {
            foreach ($d->alleNummers() as $n) {
                $bekend[$n] = true;
            }
        }
        $ongekoppeld = array_filter(array_keys($gezien), fn ($n) => ! isset($bekend[$n]));

        return [
            'gebruikers' => User::count(),
            'gebruikersWeek' => User::where('last_seen_at', '>=', now()->subDays(7))->count(),
            'depotsActief' => $depots->where('actief', true)->count(),
            'depotsZonderNummer' => $depots->where('actief', true)->whereNull('depot_nummer')->count(),
            'nummersOngekoppeld' => array_values($ongekoppeld),
            'gezien' => $gezien,
            'minimaIngesteld' => MinVoorraad::where('minimum', '>', 0)->distinct('depot_nummer')->count('depot_nummer'),
            'uploads' => Upload::latest()->limit(6)->get(),
            'aanvragenMislukt' => Aanvraag::where('status', 'mislukt')->count(),
            'mailer' => config('mail.default'),
            'laatsteGebruikers' => User::orderByDesc('last_seen_at')->limit(6)->get(),
        ];
    }
}
