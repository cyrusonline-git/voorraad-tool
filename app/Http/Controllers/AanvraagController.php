<?php

namespace App\Http\Controllers;

use App\Models\Aanvraag;
use App\Models\Depot;
use App\Models\Materieel;
use App\Models\Upload;
use App\Services\AanvraagMail;
use App\Services\Beschikbaarheid;
use Illuminate\Http\Request;

class AanvraagController extends Controller
{
    /** Overzicht van verstuurde aanvragen (aparte pagina, met filters). */
    public function index(Request $request)
    {
        $q = Aanvraag::query()->latest();
        if ($z = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('referentie', 'like', "%$z%")->orWhere('depot_naam', 'like', "%$z%")
                ->orWhere('aanvrager_naam', 'like', "%$z%")->orWhere('machines', 'like', "%$z%"));
        }
        if ($d = $request->input('depot')) {
            $q->where('depot_nummer', $d);
        }
        if ($s = $request->input('status')) {
            $q->where('status', $s);
        }
        $depots = Aanvraag::selectRaw('depot_nummer, max(depot_naam) as naam')->groupBy('depot_nummer')->orderBy('naam')->get();

        return view('aanvragen.index', ['aanvragen' => $q->paginate(50)->withQueryString(), 'depots' => $depots]);
    }

    public function toon(Aanvraag $aanvraag)
    {
        return view('aanvragen.toon', ['aanvraag' => $aanvraag]);
    }

    /** Formulier: machines van dit depot uit het beschikbaarheidsoverzicht, aan te vinken. */
    public function nieuw(Upload $upload, Request $request, Beschikbaarheid $service)
    {
        $depotNr = (string) $request->input('depot_nr');
        $eigenNr = (string) $request->input('eigen', '');
        $depot = Depot::where('depot_nummer', $depotNr)->first();
        if (! $depot) {
            return back()->with('fout', 'Depot '.$depotNr.' is niet gekoppeld aan een CORE-depot (Beheer → Depots).');
        }
        $data = $service->bereken($upload, $eigenNr ?: null);
        $regels = $data['perDepot'][$depotNr]['regels'] ?? [];
        $eigenDepot = $eigenNr ? Depot::where('depot_nummer', $eigenNr)->first() : null;
        $verhuurdatum = $upload->regels()->whereNotNull('verhuurdatum')->min('verhuurdatum');

        return view('aanvragen.nieuw', [
            'upload' => $upload, 'depot' => $depot, 'eigenDepot' => $eigenDepot, 'eigenNr' => $eigenNr,
            'regels' => $regels, 'verhuurdatum' => $verhuurdatum,
            'aan' => $depot->mailadres(), 'replyTo' => $eigenDepot?->mailadres() ?: (core_gebruiker()['email'] ?? null),
            'voorbeeldOnderwerp' => AanvraagMail::vul(AanvraagMail::template('mail_onderwerp'), [
                'type' => $upload->typeNaam(), 'referentie' => $upload->referentie, 'eigen_depot' => $eigenDepot?->naam ?? '',
            ]),
        ]);
    }

    public function verstuur(Upload $upload, Request $request, AanvraagMail $mailer)
    {
        $data = $request->validate([
            'depot_nr' => ['required', 'string'],
            'eigen' => ['nullable', 'string'],
            'regels' => ['required', 'array'],
            'regels.*.subgroep_nr' => ['required', 'string'],
            'regels.*.omschrijving' => ['nullable', 'string', 'max:200'],
            'regels.*.aantal' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'opmerking' => ['nullable', 'string', 'max:2000'],
            'reactie_voor' => ['nullable', 'date'],
            'verhuurdatum' => ['nullable', 'date'],
        ]);

        $depot = Depot::where('depot_nummer', $data['depot_nr'])->firstOrFail();
        $eigenDepot = ! empty($data['eigen']) ? Depot::where('depot_nummer', $data['eigen'])->first() : null;

        // Alleen aangevinkte regels met aantal > 0; voorraad van dit depot erbij ter info
        $regels = [];
        foreach ($data['regels'] as $r) {
            $aantal = (int) ($r['aantal'] ?? 0);
            if (empty($r['gekozen']) || $aantal <= 0) {
                continue;
            }
            $sub = preg_replace('/\D+/', '', $r['subgroep_nr']);
            $voorraad = Materieel::actueel()->where('depot_nummer', $depot->depot_nummer)->where('subgroep_nr', $sub)
                ->selectRaw('status_code, count(*) as n')->groupBy('status_code')->pluck('n', 'status_code');
            $regels[] = [
                'subgroep_nr' => $sub, 'omschrijving' => trim((string) ($r['omschrijving'] ?? '')), 'aantal' => $aantal,
                'available' => (int) ($voorraad['available'] ?? 0), 'in_service' => (int) ($voorraad['in_service'] ?? 0), 'in_repair' => (int) ($voorraad['in_repair'] ?? 0),
            ];
        }
        if (! $regels) {
            return back()->withInput()->with('fout', 'Vink minstens één subgroep aan met een aantal groter dan 0.');
        }

        $aanvraag = $mailer->verstuur($upload, $depot, $eigenDepot, $regels,
            (string) ($data['opmerking'] ?? ''), $data['reactie_voor'] ?? null, $data['verhuurdatum'] ?? null);

        if ($aanvraag->status === 'mislukt') {
            return redirect()->route('aanvragen.toon', $aanvraag)->with('fout', 'De mail kon niet worden verstuurd: '.$aanvraag->fout);
        }

        return redirect()->route('aanvragen.toon', $aanvraag)->with('ok', 'Aanvraag verstuurd aan '.$depot->naam.' ('.$aanvraag->aan_email.').');
    }
}
