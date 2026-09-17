<?php

namespace App\Http\Controllers;

use App\Models\Depot;
use App\Models\Materieel;
use App\Models\Upload;
use App\Services\Beschikbaarheid;
use Illuminate\Http\Request;

class BeschikbaarheidController extends Controller
{
    public function toon(Upload $upload, Request $request, Beschikbaarheid $service)
    {
        abort_unless(in_array($upload->type, ['contract', 'project']), 404);
        $materieel = Upload::where('type', 'materieel')->where('actueel', true)->first();
        if (! $materieel || Materieel::actueel()->count() === 0) {
            return redirect()->route('uploads.index')->with('fout', 'Er is nog geen materieellijst ingelezen. Upload eerst de materieellijst.');
        }

        // Eigen depot: gekozen → vestiging uit het contract → depot van de gebruiker (CORE)
        $depots = Depot::actief()->whereNotNull('depot_nummer')->orderBy('volgorde')->get();
        $eigen = (string) $request->input('depot', '');
        if ($eigen === '') {
            $eigen = (string) ($upload->depot_nummer ?: (Depot::where('naam', session('eigen_depot'))->value('depot_nummer') ?? ''));
        }
        $eigen = $eigen !== '' ? $eigen : null;

        $data = $service->bereken($upload, $eigen);

        // Filters op de regels (tekst, alleen tekorten)
        $q = mb_strtolower(trim((string) $request->input('q')));
        $alleenTekort = $request->boolean('tekort');
        $regels = array_values(array_filter($data['regels'], function ($r) use ($q, $alleenTekort) {
            if ($alleenTekort && $r['tekort'] === 0) {
                return false;
            }
            if ($q === '') {
                return true;
            }
            $hooi = mb_strtolower(($r['regel']->subgroep_nr ?? '').' '.($r['regel']->omschrijving ?? '').' '.($r['regel']->contract_nr ?? ''));

            return str_contains($hooi, $q);
        }));

        return view('beschikbaarheid.toon', [
            'upload' => $upload,
            'materieel' => $materieel,
            'depots' => $depots,
            'eigen' => $eigen,
            'eigenNaam' => $data['depotNamen'][$eigen] ?? null,
            'regels' => $regels,
            'perDepot' => $data['perDepot'],
            'tekorten' => $data['tekorten'],
            'totaalNodig' => $data['totaalNodig'],
            'totaalGevonden' => $data['totaalGevonden'],
            'aantalRegels' => count($data['regels']),
            'depotNamen' => $data['depotNamen'],
            'zoekToegekend' => (bool) (int) setting('zoek_toegekend_zonder_nummer', 1),
        ]);
    }
}
