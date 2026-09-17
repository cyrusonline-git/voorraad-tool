<?php

namespace App\Http\Controllers;

use App\Models\Depot;
use App\Models\User;
use App\Services\DepotSync;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Startpagina: het dashboard van de actieve rol. */
    public function index(Request $request, DepotSync $depotSync)
    {
        $depotSync->syncIndienNodig();
        $rol = actieve_rol();
        if ($rol === 'werkplaats') {
            return redirect()->route('voorraad.werkplaats');
        }
        if (in_array($rol, ['manager', 'fleet'], true)) {
            return redirect()->route('voorraad.depots');
        }
        $view = 'dashboard.'.$rol;
        if (! view()->exists($view)) {
            abort(500, "Geen dashboard voor rol $rol");
        }

        $eigenDepot = Depot::where('naam', session('eigen_depot'))->first();
        $depots = Depot::actief()->orderBy('area')->orderBy('volgorde')->get();

        return view($view, [
            'eigenDepot' => $eigenDepot,
            'depots' => $depots,
            'areas' => $depots->groupBy('area'),
            'aantalGebruikers' => User::count(),
        ]);
    }
}
