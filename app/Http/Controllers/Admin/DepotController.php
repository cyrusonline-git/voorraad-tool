<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Depot;
use App\Services\DepotSync;
use Illuminate\Http\Request;

class DepotController extends Controller
{
    public function index()
    {
        $depots = Depot::orderBy('actief', 'desc')->orderBy('area')->orderBy('volgorde')->get();

        return view('admin.depots', [
            'depots' => $depots, 'gesynct' => $depots->max('gesynct_op'),
            'materieelDepots' => \App\Services\DepotKoppeling::uitMaterieel(),
        ]);
    }

    /** Depotnummers automatisch koppelen op naam (materieellijst ↔ CORE). */
    public function koppel(\App\Services\DepotKoppeling $koppeling)
    {
        $meldingen = [];
        $n = $koppeling->koppelAutomatisch($meldingen);

        return redirect()->route('admin.depots')->with('ok', "$n depot(s) automatisch gekoppeld.".($meldingen ? ' '.implode(' ', $meldingen) : ''));
    }

    /** Opnieuw ophalen uit CORE. */
    public function sync(DepotSync $sync)
    {
        $n = $sync->sync();

        return redirect()->route('admin.depots')->with(
            $n === null ? 'fout' : 'ok',
            $n === null ? 'Kon de depots niet ophalen uit Boels CORE.' : "$n depots bijgewerkt uit Boels CORE."
        );
    }

    /** Depotnummer (materieel-Excel) en aanvraag-mailadres per depot opslaan. */
    public function opslaan(Request $request)
    {
        $rijen = (array) $request->input('depot', []);
        foreach (Depot::all() as $depot) {
            if (! isset($rijen[$depot->id])) {
                continue;
            }
            $r = $rijen[$depot->id];
            $depot->update([
                'depot_nummer' => trim((string) ($r['depot_nummer'] ?? '')) ?: null,
                'email' => trim((string) ($r['email'] ?? '')) ?: null,
            ]);
        }

        return redirect()->route('admin.depots')->with('ok', 'Depotgegevens opgeslagen.');
    }
}
