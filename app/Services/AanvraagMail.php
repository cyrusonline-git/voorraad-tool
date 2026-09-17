<?php

namespace App\Services;

use App\Models\Aanvraag;
use App\Models\Depot;
use App\Models\Setting;
use App\Models\Upload;
use Illuminate\Support\Facades\Mail;

/**
 * Aanvraagmail aan een depot: "mogen wij deze machines bij jullie halen?"
 * Templates (onderwerp/inleiding/afsluiting) staan in de instellingen en
 * zijn door de beheerder aan te passen; placeholders worden ingevuld.
 */
class AanvraagMail
{
    public const PLACEHOLDERS = [
        '{type}' => 'Contract of Project', '{referentie}' => 'contract-/projectnummer', '{omschrijving}' => 'omschrijving (project)',
        '{depot}' => 'aangeschreven depot', '{eigen_depot}' => 'depot van de aanvrager', '{aanvrager}' => 'naam aanvrager',
        '{verhuurdatum}' => 'gewenste verhuurdatum', '{aantal}' => 'aantal machines', '{reactie_voor}' => 'gewenste reactiedatum',
        '{opmerking}' => 'opmerking van de aanvrager',
    ];

    public static function standaard(): array
    {
        return [
            'mail_onderwerp' => 'Aanvraag materieel {type} {referentie} — {eigen_depot}',
            'mail_intro' => "Beste collega's van {depot},\n\nVoor {type} {referentie} {omschrijving}(verhuur vanaf {verhuurdatum}) zoeken wij nog materieel dat volgens de materieellijst bij jullie beschikbaar is. Mogen wij onderstaande {aantal} machine(s) bij jullie ophalen of laten overbrengen naar {eigen_depot}?",
            'mail_afsluiting' => "{opmerking}Graag jullie reactie{reactie_voor}. Alvast bedankt!\n\nMet vriendelijke groet,\n{aanvrager}\n{eigen_depot}",
        ];
    }

    public static function template(string $key): string
    {
        return (string) Setting::get($key, self::standaard()[$key] ?? '');
    }

    /** Placeholders invullen. */
    public static function vul(string $tekst, array $w): string
    {
        return strtr($tekst, [
            '{type}' => $w['type'] ?? '', '{referentie}' => $w['referentie'] ?? '', '{omschrijving}' => ($w['omschrijving'] ?? '') !== '' ? '"'.$w['omschrijving'].'" ' : '',
            '{depot}' => $w['depot'] ?? '', '{eigen_depot}' => $w['eigen_depot'] ?? '', '{aanvrager}' => $w['aanvrager'] ?? '',
            '{verhuurdatum}' => $w['verhuurdatum'] ?? 'n.t.b.', '{aantal}' => (string) ($w['aantal'] ?? ''),
            '{reactie_voor}' => ($w['reactie_voor'] ?? '') !== '' ? ' vóór '.$w['reactie_voor'] : '',
            '{opmerking}' => ($w['opmerking'] ?? '') !== '' ? "Opmerking van de aanvrager:\n".$w['opmerking']."\n\n" : '',
        ]);
    }

    /**
     * Mail opbouwen, versturen en loggen. Geeft de Aanvraag terug (status verzonden/mislukt).
     * $machines: lijst van Materieel-modellen; $regelInfo: uniek_nr => omschrijving van de orderregel.
     */
    public function verstuur(Upload $upload, Depot $depot, ?Depot $eigenDepot, array $machines, array $regelInfo, string $opmerking, ?string $reactieVoor, ?string $verhuurdatum): Aanvraag
    {
        $gebruiker = core_gebruiker() ?? [];
        $aan = $depot->mailadres();
        $eigenMail = $eigenDepot?->mailadres();
        $aanvragerMail = $gebruiker['email'] ?? null;
        $replyTo = $eigenMail ?: $aanvragerMail;
        $cc = array_values(array_unique(array_filter([$eigenMail, $aanvragerMail, ...array_map('trim', explode(',', (string) setting('mail_cc', '')))])));
        $cc = array_values(array_filter($cc, fn ($m) => filter_var($m, FILTER_VALIDATE_EMAIL) && strcasecmp($m, (string) $aan) !== 0));

        $waarden = [
            'type' => $upload->typeNaam(), 'referentie' => $upload->referentie, 'omschrijving' => $upload->type === 'project' ? ($upload->regels()->value('project_omschrijving') ?? '') : '',
            'depot' => $depot->naam, 'eigen_depot' => $eigenDepot?->naam ?? ($gebruiker['name'] ?? ''), 'aanvrager' => $gebruiker['name'] ?? 'Binnendienst',
            'verhuurdatum' => $verhuurdatum ? \Carbon\Carbon::parse($verhuurdatum)->format('d-m-Y') : 'n.t.b.',
            'aantal' => count($machines), 'reactie_voor' => $reactieVoor ? \Carbon\Carbon::parse($reactieVoor)->format('d-m-Y') : '', 'opmerking' => trim($opmerking),
        ];
        $onderwerp = self::vul(self::template('mail_onderwerp'), $waarden);
        $intro = self::vul(self::template('mail_intro'), $waarden);
        $afsluiting = self::vul(self::template('mail_afsluiting'), $waarden);

        $lijst = array_map(fn ($m) => [
            'uniek_nr' => $m->uniek_nr, 'subgroep_nr' => $m->subgroep_nr,
            'omschrijving' => $regelInfo[$m->uniek_nr] ?? ($m->omschrijving ?: $m->subgroep_naam),
            'merk_model' => trim(($m->extra['merk'] ?? '').' '.($m->extra['model'] ?? '')),
            'status' => $m->status_raw, 'laatste_uithuur' => $m->laatste_uithuur?->format('d-m-Y'),
        ], $machines);

        $aanvraag = Aanvraag::create([
            'upload_id' => $upload->id, 'upload_type' => $upload->type, 'referentie' => $upload->referentie, 'omschrijving' => $waarden['omschrijving'],
            'depot_nummer' => $depot->depot_nummer, 'depot_naam' => $depot->naam,
            'eigen_depot_nummer' => $eigenDepot?->depot_nummer, 'eigen_depot_naam' => $eigenDepot?->naam,
            'aan_email' => $aan, 'reply_to' => $replyTo, 'cc' => implode(', ', $cc),
            'onderwerp' => $onderwerp, 'body' => $intro."\n\n[machinelijst]\n\n".$afsluiting,
            'machines' => $lijst, 'aantal_machines' => count($lijst), 'opmerking' => trim($opmerking) ?: null,
            'reactie_voor' => $reactieVoor ?: null, 'verhuurdatum' => $verhuurdatum ?: null,
            'status' => 'verzonden', 'user_id' => session('app_user_id'),
            'aanvrager_naam' => $gebruiker['name'] ?? null, 'aanvrager_email' => $aanvragerMail,
        ]);

        if (! $aan || ! filter_var($aan, FILTER_VALIDATE_EMAIL)) {
            $aanvraag->update(['status' => 'mislukt', 'fout' => 'Geen (geldig) mailadres bekend voor depot '.$depot->naam.'. Vul het in Boels CORE (Infrastructuur) of bij Beheer → Depots in.']);

            return $aanvraag;
        }
        try {
            Mail::send('mail.aanvraag', ['intro' => $intro, 'afsluiting' => $afsluiting, 'machines' => $lijst, 'waarden' => $waarden, 'aanvraag' => $aanvraag],
                function ($m) use ($aan, $cc, $replyTo, $onderwerp, $waarden) {
                    $m->to($aan)->subject($onderwerp);
                    $m->from(config('mail.from.address'), setting('mail_van_naam', 'Boels Industrial — Voorraad tool').' namens '.$waarden['eigen_depot']);
                    if ($replyTo) {
                        $m->replyTo($replyTo, $waarden['eigen_depot'] ?: $waarden['aanvrager']);
                    }
                    if ($cc) {
                        $m->cc($cc);
                    }
                });
        } catch (\Throwable $e) {
            report($e);
            $aanvraag->update(['status' => 'mislukt', 'fout' => $e->getMessage()]);
        }

        return $aanvraag;
    }
}
