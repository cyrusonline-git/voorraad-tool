<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ExcelImport;
use Illuminate\Http\Request;

/** Beheer → Kolomindeling: welke kolom wat is per uploadtype + statusvertalingen. */
class KolomController extends Controller
{
    public function index()
    {
        $types = [];
        foreach (ExcelImport::KOLOMMEN as $type => $std) {
            $types[$type] = ['kolommen' => ExcelImport::kolommen($type), 'standaard' => $std, 'koprij' => ExcelImport::kopRij($type)];
        }
        $eigenM = json_decode((string) Setting::get('status_materieel', ''), true) ?: [];
        $eigenO = json_decode((string) Setting::get('status_order', ''), true) ?: [];

        return view('admin.kolommen', [
            'types' => $types,
            'letters' => ExcelImport::kolomLetters(),
            'labels' => ExcelImport::VELD_LABELS,
            'statusMaterieel' => array_merge(ExcelImport::STATUS_MATERIEEL, $eigenM),
            'statusOrder' => array_merge(ExcelImport::STATUS_ORDER, $eigenO),
            'codesMaterieel' => \App\Models\Materieel::STATUSSEN,
            'codesOrder' => \App\Models\OrderRegel::STATUSSEN,
        ]);
    }

    public function opslaan(Request $request)
    {
        foreach (array_keys(ExcelImport::KOLOMMEN) as $type) {
            $k = array_map(fn ($v) => strtoupper(trim((string) $v)), (array) $request->input("kolommen.$type", []));
            Setting::set("kolommen.$type", json_encode(array_filter($k)));
            Setting::set("koprij.$type", max(1, (int) $request->input("koprij.$type", 1)));
        }
        foreach (['status_materieel', 'status_order'] as $key) {
            $tabel = [];
            foreach ((array) $request->input($key, []) as $rij) {
                $tekst = mb_strtolower(trim((string) ($rij['tekst'] ?? '')));
                $code = trim((string) ($rij['code'] ?? ''));
                if ($tekst !== '' && $code !== '') {
                    $tabel[$tekst] = $code;
                }
            }
            Setting::set($key, json_encode($tabel));
        }

        return redirect()->route('admin.kolommen')->with('ok', 'Kolomindeling en statusvertalingen opgeslagen.');
    }
}
