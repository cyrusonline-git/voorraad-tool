<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AanvraagMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Beheer → Mailtemplates: onderwerp/inleiding/afsluiting van de aanvraagmail + testmail. */
class MailController extends Controller
{
    public function index()
    {
        $std = AanvraagMail::standaard();
        $waarden = [];
        foreach ($std as $k => $v) {
            $waarden[$k] = AanvraagMail::template($k);
        }
        $voorbeeld = [
            'type' => 'Contract', 'referentie' => '7590019510', 'omschrijving' => '', 'depot' => 'Industrial Geleen', 'eigen_depot' => 'Industrial Rotterdam',
            'aanvrager' => core_gebruiker()['name'] ?? 'Aanvrager', 'verhuurdatum' => now()->addDays(7)->format('d-m-Y'), 'aantal' => 3,
            'reactie_voor' => now()->addDays(2)->format('d-m-Y'), 'opmerking' => 'Graag voor woensdag, ivm opbouw.',
        ];

        return view('admin.mail', [
            'waarden' => $waarden, 'standaard' => $std, 'placeholders' => AanvraagMail::PLACEHOLDERS,
            'preview' => array_map(fn ($t) => AanvraagMail::vul($t, $voorbeeld), $waarden),
            'mailer' => config('mail.default'), 'van' => config('mail.from.address'),
        ]);
    }

    public function opslaan(Request $request)
    {
        foreach (array_keys(AanvraagMail::standaard()) as $k) {
            Setting::set($k, trim((string) $request->input($k, '')));
        }

        return redirect()->route('admin.mail')->with('ok', 'Mailtemplates opgeslagen.');
    }

    public function herstel()
    {
        foreach (AanvraagMail::standaard() as $k => $v) {
            Setting::set($k, $v);
        }

        return redirect()->route('admin.mail')->with('ok', 'Standaardteksten hersteld.');
    }

    /** Testmail naar het eigen CORE-adres. */
    public function test()
    {
        $aan = core_gebruiker()['email'] ?? null;
        if (! $aan) {
            return back()->with('fout', 'Je CORE-account heeft geen mailadres.');
        }
        try {
            Mail::raw("Dit is een testmail van de Boels Voorraad tool (".config('app.url').").\nMailer: ".config('mail.default'), function ($m) use ($aan) {
                $m->to($aan)->subject('Testmail Voorraad tool')->from(config('mail.from.address'), setting('mail_van_naam', 'Boels Industrial — Voorraad tool'));
            });
        } catch (\Throwable $e) {
            return back()->with('fout', 'Testmail mislukt: '.$e->getMessage());
        }

        return back()->with('ok', "Testmail verstuurd naar $aan (mailer: ".config('mail.default').').');
    }
}
