<?php

namespace App\Http\Controllers;

use App\Models\Materieel;
use App\Models\OrderRegel;
use App\Models\Upload;
use App\Services\ExcelImport;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function index()
    {
        $materieel = Upload::where('type', 'materieel')->where('actueel', true)->latest()->first();

        return view('uploads.index', [
            'materieel' => $materieel,
            'materieelAantal' => $materieel ? Materieel::where('upload_id', $materieel->id)->count() : 0,
            'uploads' => Upload::whereIn('type', ['contract', 'project'])->latest()->limit(50)->get(),
            'eerdereMaterieel' => Upload::where('type', 'materieel')->latest()->limit(5)->get(),
        ]);
    }

    public function opslaan(Request $request, ExcelImport $import)
    {
        if ($request->server('CONTENT_LENGTH') > 0 && ! $request->hasFile('bestand') && empty($request->all())) {
            return back()->with('fout', 'Het bestand is groter dan de server toestaat ('.ini_get('post_max_size').'). Neem contact op met de beheerder.');
        }
        $data = $request->validate([
            'type' => ['required', 'in:materieel,contract,project'],
            'bestand' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:65536'],
        ]);
        $bestand = $request->file('bestand');
        try {
            $upload = $import->importeer(
                $data['type'],
                $bestand->getRealPath(),
                $bestand->getClientOriginalName(),
                session('app_user_id'),
                core_gebruiker()['name'] ?? null,
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('fout', 'Inlezen mislukt: '.$e->getMessage());
        }
        $msg = $upload->typeNaam().' ingelezen: '.$upload->aantal_rijen.' regels'
            .($upload->aantal_overgeslagen ? ' ('.$upload->aantal_overgeslagen.' lege regels overgeslagen)' : '').'.';

        return redirect()->route('uploads.toon', $upload)->with('ok', $msg);
    }

    public function toon(Upload $upload, Request $request)
    {
        if ($upload->type === 'materieel') {
            $q = Materieel::where('upload_id', $upload->id);
            if ($z = trim((string) $request->input('q'))) {
                $q->where(fn ($w) => $w->where('uniek_nr', 'like', "%$z%")->orWhere('subgroep_nr', 'like', "%$z%")
                    ->orWhere('omschrijving', 'like', "%$z%")->orWhere('depot_naam', 'like', "%$z%")->orWhere('depot_nummer', 'like', "%$z%"));
            }
            if ($s = $request->input('status')) {
                $q->where('status_code', $s);
            }
            $statussen = Materieel::where('upload_id', $upload->id)->selectRaw('status_code, count(*) as n')->groupBy('status_code')->pluck('n', 'status_code');
            $depots = Materieel::where('upload_id', $upload->id)->selectRaw('depot_nummer, depot_naam, count(*) as n')->groupBy('depot_nummer', 'depot_naam')->orderByDesc('n')->get();

            return view('uploads.materieel', [
                'upload' => $upload, 'regels' => $q->orderBy('depot_nummer')->orderBy('subgroep_nr')->paginate(100)->withQueryString(),
                'statussen' => $statussen, 'depots' => $depots,
            ]);
        }

        $q = OrderRegel::where('upload_id', $upload->id);
        if ($z = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('subgroep_nr', 'like', "%$z%")->orWhere('artikel_nr', 'like', "%$z%")
                ->orWhere('omschrijving', 'like', "%$z%")->orWhere('contract_nr', 'like', "%$z%"));
        }
        if ($s = $request->input('status')) {
            $q->where('status_code', $s);
        }
        $statussen = OrderRegel::where('upload_id', $upload->id)->selectRaw('status_code, count(*) as n')->groupBy('status_code')->pluck('n', 'status_code');

        return view('uploads.order', [
            'upload' => $upload, 'regels' => $q->orderBy('regel_nr')->get(), 'statussen' => $statussen,
            'teZoeken' => $statussen['not_allocated'] ?? 0,
        ]);
    }

    public function verwijder(Upload $upload)
    {
        if ($upload->type === 'materieel' && $upload->actueel) {
            return back()->with('fout', 'De actuele materieellijst kun je niet verwijderen; upload een nieuwe lijst om hem te vervangen.');
        }
        OrderRegel::where('upload_id', $upload->id)->delete();
        Materieel::where('upload_id', $upload->id)->delete();
        $upload->delete();

        return redirect()->route('uploads.index')->with('ok', 'Upload verwijderd.');
    }
}
