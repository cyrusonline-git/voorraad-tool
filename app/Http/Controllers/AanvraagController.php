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
        $machines = $data['perDepot'][$depotNr]['machines'] ?? [];
        $eigenDepot = $eigenNr ? Depot::where('depot_nummer', $eigenNr)->first() : null;
        $verhuurdatum = $upload->regels()->whereNotNull('verhuurdatum')->min('verhuurdatum');

        return view('aanvragen.nieuw', [
            'upload' => $upload, 'depot' => $depot, 'eigenDepot' => $eigenDepot, 'eigenNr' => $eigenNr,
            'machines' => $machines, 'verhuurdatum' => $verhuurdatum,
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
            'machines' => ['required', 'array', 'min:1'],
            'machines.*' => ['string'],
            'opmerking' => ['nullable', 'string', 'max:2000'],
            'reactie_voor' => ['nullable', 'date'],
            'verhuurdatum' => ['nullable', 'date'],
        ], ['machines.required' => 'Vink minstens één machine aan.']);

        $depot = Depot::where('depot_nummer', $data['depot_nr'])->firstOrFail();
        $eigenDepot = ! empty($data['eigen']) ? Depot::where('depot_nummer', $data['eigen'])->first() : null;
        $machines = Materieel::actueel()->whereIn('uniek_nr', $data['machines'])->where('depot_nummer', $depot->depot_nummer)->get()->all();
        if (! $machines) {
            return back()->with('fout', 'De gekozen machines staan niet (meer) op dit depot in de actuele materieellijst.');
        }
        // Omschrijving van de orderregel bij elke machine (op subgroep)
        $regelInfo = [];
        $perSub = $upload->regels()->whereNotNull('subgroep_nr')->get()->keyBy('subgroep_nr');
        foreach ($machines as $m) {
            if (isset($perSub[$m->subgroep_nr])) {
                $regelInfo[$m->uniek_nr] = $perSub[$m->subgroep_nr]->omschrijving;
            }
        }

        $aanvraag = $mailer->verstuur($upload, $depot, $eigenDepot, $machines, $regelInfo,
            (string) ($data['opmerking'] ?? ''), $data['reactie_voor'] ?? null, $data['verhuurdatum'] ?? null);

        if ($aanvraag->status === 'mislukt') {
            return redirect()->route('aanvragen.toon', $aanvraag)->with('fout', 'De mail kon niet worden verstuurd: '.$aanvraag->fout);
        }

        return redirect()->route('aanvragen.toon', $aanvraag)->with('ok', 'Aanvraag verstuurd aan '.$depot->naam.' ('.$aanvraag->aan_email.').');
    }
}
