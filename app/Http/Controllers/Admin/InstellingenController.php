<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

class InstellingenController extends Controller
{
    /** Instellingen die de beheerder kan zetten (sleutel => [label, standaard, uitleg]). */
    public static function velden(): array
    {
        return [
            'app_titel' => ['Naam in de kop', 'Voorraad tool', 'Wordt bovenin elke pagina getoond.'],
            'mail_van_naam' => ['Afzendernaam aanvraagmails', 'Boels Industrial — Voorraad tool', 'Naam waarmee aanvraagmails naar depots worden verstuurd.'],
            'mail_cc' => ['CC bij aanvraagmails', '', 'Optioneel: één of meer adressen, gescheiden door komma.'],
            'zoek_toegekend_zonder_nummer' => ['Ook "Toegekend" zonder uniek nummer zoeken (1 = ja, 0 = nee)', '1', 'Projecten: regels met status Allocated waarbij Item No alleen het subgroepnummer is, worden dan ook gezocht.'],
            'orders_horizon_dagen' => ['Horizon aankomende orders (dagen)', '14', 'Werkplaats: orders met ingangsdatum binnen dit aantal dagen worden meegenomen.'],
        ];
    }

    public function index()
    {
        $waarden = [];
        foreach (self::velden() as $key => [$label, $standaard]) {
            $waarden[$key] = setting($key, $standaard);
        }

        return view('admin.instellingen', [
            'velden' => self::velden(),
            'waarden' => $waarden,
            'gebruikers' => User::orderByDesc('last_seen_at')->limit(50)->get(),
        ]);
    }

    public function opslaan(Request $request)
    {
        foreach (array_keys(self::velden()) as $key) {
            Setting::set($key, trim((string) $request->input($key, '')));
        }

        return redirect()->route('admin.instellingen')->with('ok', 'Instellingen opgeslagen.');
    }
}
