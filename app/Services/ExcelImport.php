<?php

namespace App\Services;

use App\Models\Materieel;
use App\Models\OrderRegel;
use App\Models\Setting;
use App\Models\Upload;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel-uploads inlezen. Welke kolom wat betekent staat per type in de
 * instellingen (Beheer → Kolomindeling) met de standaard van Wim (17-09-2026).
 * Statussen worden vertaald naar vaste codes (NL en EN door elkaar).
 */
class ExcelImport
{
    /** Standaard kolomindeling per type: veld => kolomletter. */
    public const KOLOMMEN = [
        'materieel' => [
            'uniek_nr' => 'A', 'subgroep' => 'D', 'omschrijving' => 'E', 'depot' => 'J', 'area' => 'K',
            'status' => 'M', 'laatste_uithuur' => 'N',
        ],
        'contract' => [
            'subgroep' => 'B', 'artikel_nr' => 'C', 'omschrijving' => 'D', 'status' => 'G',
            'afleverdatum' => 'H', 'verhuurdatum' => 'I', 'aantal' => 'J', 'vestiging' => 'P',
        ],
        'project' => [
            'contract_nr' => 'A', 'project_nr' => 'B', 'project_omschrijving' => 'C', 'subgroep' => 'F',
            'omschrijving' => 'G', 'aantal' => 'J', 'verhuurdatum' => 'R', 'status' => 'Z',
        ],
    ];

    public const VELD_LABELS = [
        'uniek_nr' => 'Uniek nummer (machinenummer)', 'subgroep' => 'Subgroep (nummer)', 'omschrijving' => 'Omschrijving',
        'depot' => 'Depot (nummer + locatie)', 'area' => 'Area', 'status' => 'Status', 'laatste_uithuur' => 'Laatste uit-huur datum',
        'artikel_nr' => 'Artikelnummer', 'afleverdatum' => 'Afleverdatum', 'verhuurdatum' => 'Verhuur-/startdatum',
        'aantal' => 'Aantal', 'vestiging' => 'Vestiging (depotnummer)', 'contract_nr' => 'Contractnummer',
        'project_nr' => 'Projectnummer', 'project_omschrijving' => 'Projectomschrijving',
    ];

    /** Statusvertaling: genormaliseerde tekst => code. */
    public const STATUS_MATERIEEL = [
        'available' => 'available', 'beschikbaar' => 'available',
        'in service' => 'in_service', 'service' => 'in_service',
        'in repair' => 'in_repair', 'repair' => 'in_repair', 'reparatie' => 'in_repair', 'in reparatie' => 'in_repair',
        'on hire' => 'on_hire', 'in huur' => 'on_hire', 'verhuurd' => 'on_hire',
        'own use' => 'own_use', 'own user' => 'own_use', 'eigen gebruik' => 'own_use',
        'in transfer' => 'in_transfer', 'transfer' => 'in_transfer', 'onderweg' => 'in_transfer',
    ];

    public const STATUS_ORDER = [
        'niet toegekend' => 'not_allocated', 'not allocated' => 'not_allocated', 'unallocated' => 'not_allocated',
        'toegekend' => 'allocated', 'allocated' => 'allocated',
        'inhuur' => 'on_hire', 'in huur' => 'on_hire', 'on hire' => 'on_hire',
        'uit-verhuur' => 'off_hire', 'uit verhuur' => 'off_hire', 'off hire' => 'off_hire', 'off-hire' => 'off_hire',
        'goederen in' => 'goods_in', 'goods in' => 'goods_in',
    ];

    /** Kolomindeling voor een type (instelling gaat vóór de standaard). */
    public static function kolommen(string $type): array
    {
        $std = self::KOLOMMEN[$type] ?? [];
        $eigen = json_decode((string) Setting::get("kolommen.$type", ''), true);

        return is_array($eigen) ? array_merge($std, array_filter(array_map('strtoupper', $eigen))) : $std;
    }

    public static function kopRij(string $type): int
    {
        return max(1, (int) Setting::get("koprij.$type", 1));
    }

    /**
     * Bestand inlezen en opslaan. Geeft de Upload terug (met meldingen).
     */
    public function importeer(string $type, string $pad, string $bestandsnaam, ?int $userId, ?string $userNaam): Upload
    {
        if (! isset(self::KOLOMMEN[$type])) {
            throw new \InvalidArgumentException("Onbekend uploadtype: $type");
        }
        $reader = IOFactory::createReaderForFile($pad);
        $reader->setReadDataOnly(true);
        $wb = $reader->load($pad);
        $ws = $wb->getSheet(0);

        $upload = Upload::create([
            'type' => $type, 'bestandsnaam' => $bestandsnaam, 'pad' => null,
            'user_id' => $userId, 'gebruiker_naam' => $userNaam, 'actueel' => true,
            'omschrijving' => $ws->getTitle(),
        ]);

        $meldingen = [];
        $n = match ($type) {
            'materieel' => $this->leesMaterieel($ws, $upload, $meldingen),
            'contract' => $this->leesOrder($ws, $upload, 'contract', $meldingen),
            'project' => $this->leesOrder($ws, $upload, 'project', $meldingen),
        };

        if ($type === 'materieel') {
            // Alleen de laatste materieellijst is actueel; oude regels opruimen
            Upload::where('type', 'materieel')->where('id', '!=', $upload->id)->update(['actueel' => false]);
            Materieel::whereIn('upload_id', Upload::where('type', 'materieel')->where('actueel', false)->select('id'))->delete();
        }

        $upload->update(['aantal_rijen' => $n[0], 'aantal_overgeslagen' => $n[1], 'meldingen' => array_slice($meldingen, 0, 50)]);

        return $upload;
    }

    // ------------------------------------------------------------------

    private function leesMaterieel(Worksheet $ws, Upload $upload, array &$meldingen): array
    {
        $k = self::kolommen('materieel');
        $start = self::kopRij('materieel') + 1;
        $hoogste = $ws->getHighestDataRow();
        $rijen = [];
        $overgeslagen = 0;
        $statusOnbekend = [];
        for ($r = $start; $r <= $hoogste; $r++) {
            $uniek = $this->tekst($ws, $k['uniek_nr'], $r);
            if ($uniek === '') {
                $overgeslagen++;
                continue;
            }
            $depotRaw = $this->tekst($ws, $k['depot'], $r);
            [$depotNr, $depotNaam] = $this->splitsDepot($depotRaw);
            $statusRaw = $this->tekst($ws, $k['status'], $r);
            $code = $this->statusCode($statusRaw, self::STATUS_MATERIEEL, 'status_materieel');
            if ($code === 'onbekend' && $statusRaw !== '') {
                $statusOnbekend[$statusRaw] = true;
            }
            [$subNr, $subNaam] = $this->splitsSubgroep($this->tekst($ws, $k['subgroep'], $r));
            $rijen[] = [
                'upload_id' => $upload->id,
                'uniek_nr' => $uniek,
                'subgroep_nr' => $subNr,
                'subgroep_naam' => $subNaam,
                'omschrijving' => $this->tekst($ws, $k['omschrijving'] ?? '', $r) ?: null,
                'depot_raw' => $depotRaw ?: null,
                'depot_nummer' => $depotNr,
                'depot_naam' => $depotNaam,
                'area_raw' => $this->tekst($ws, $k['area'], $r) ?: null,
                'status_raw' => $statusRaw ?: null,
                'status_code' => $code,
                'laatste_uithuur' => $this->datum($ws, $k['laatste_uithuur'], $r),
                'extra' => null,
            ];
            if (count($rijen) >= 500) {
                DB::table('materieel')->insert($rijen);
                $rijen = [];
            }
        }
        if ($rijen) {
            DB::table('materieel')->insert($rijen);
        }
        foreach (array_keys($statusOnbekend) as $s) {
            $meldingen[] = "Onbekende status '$s' — voeg een vertaling toe bij Beheer → Kolomindeling.";
        }
        $aantal = Materieel::where('upload_id', $upload->id)->count();
        $zonderDepot = Materieel::where('upload_id', $upload->id)->whereNull('depot_nummer')->count();
        if ($zonderDepot > 0) {
            $meldingen[] = "$zonderDepot regels zonder herkenbaar depotnummer in kolom {$k['depot']}.";
        }

        return [$aantal, $overgeslagen];
    }

    private function leesOrder(Worksheet $ws, Upload $upload, string $bron, array &$meldingen): array
    {
        $k = self::kolommen($bron);
        $start = self::kopRij($bron) + 1;
        $hoogste = $ws->getHighestDataRow();
        $rijen = [];
        $overgeslagen = 0;
        $statusOnbekend = [];
        $contractNrs = [];
        $projectNrs = [];
        $vestigingen = [];
        $regelNr = 0;
        for ($r = $start; $r <= $hoogste; $r++) {
            [$subNr, $subNaam] = $this->splitsSubgroep($this->tekst($ws, $k['subgroep'], $r));
            $omschrijving = $this->tekst($ws, $k['omschrijving'], $r);
            $statusRaw = $this->tekst($ws, $k['status'], $r);
            if ($subNr === null && $omschrijving === '' && $statusRaw === '') {
                $overgeslagen++;
                continue;
            }
            $regelNr++;
            $code = $this->statusCode($statusRaw, self::STATUS_ORDER, 'status_order');
            if ($code === 'onbekend' && $statusRaw !== '') {
                $statusOnbekend[$statusRaw] = true;
            }
            $contractNr = $bron === 'project' ? $this->tekst($ws, $k['contract_nr'], $r) : null;
            $projectNr = $bron === 'project' ? $this->tekst($ws, $k['project_nr'], $r) : null;
            $vestiging = $bron === 'contract' ? preg_replace('/\D+/', '', $this->tekst($ws, $k['vestiging'], $r)) : null;
            if ($contractNr) $contractNrs[$contractNr] = true;
            if ($projectNr) $projectNrs[$projectNr] = true;
            if ($vestiging) $vestigingen[$vestiging] = ($vestigingen[$vestiging] ?? 0) + 1;
            $rijen[] = [
                'upload_id' => $upload->id,
                'bron' => $bron,
                'contract_nr' => $contractNr ?: null,
                'project_nr' => $projectNr ?: null,
                'project_omschrijving' => $bron === 'project' ? ($this->tekst($ws, $k['project_omschrijving'], $r) ?: null) : null,
                'regel_nr' => $regelNr,
                'subgroep_nr' => $subNr,
                'artikel_nr' => $bron === 'contract' ? ($this->tekst($ws, $k['artikel_nr'], $r) ?: null) : null,
                'omschrijving' => $omschrijving ?: ($subNaam ?: null),
                'status_raw' => $statusRaw ?: null,
                'status_code' => $code,
                'afleverdatum' => $bron === 'contract' ? $this->datum($ws, $k['afleverdatum'], $r) : null,
                'verhuurdatum' => $this->datum($ws, $k['verhuurdatum'], $r),
                'aantal' => $this->getal($ws, $k['aantal'], $r, 1),
                'vestiging_nr' => $vestiging ?: null,
                'extra' => null,
            ];
        }
        if ($rijen) {
            foreach (array_chunk($rijen, 500) as $chunk) {
                DB::table('order_regels')->insert($chunk);
            }
        }
        // Referentie: contractnummer uit de bladnaam/bestandsnaam (contract) of projectnummer(s)
        if ($bron === 'contract') {
            $ref = $this->nummerUit($ws->getTitle()) ?: $this->nummerUit($upload->bestandsnaam);
            OrderRegel::where('upload_id', $upload->id)->update(['contract_nr' => $ref]);
            arsort($vestigingen);
            $upload->depot_nummer = $vestigingen ? (string) array_key_first($vestigingen) : null;
        } else {
            $ref = implode(', ', array_keys($projectNrs));
        }
        $upload->referentie = $ref ?: null;
        $upload->save();

        foreach (array_keys($statusOnbekend) as $s) {
            $meldingen[] = "Onbekende status '$s' — voeg een vertaling toe bij Beheer → Kolomindeling.";
        }
        if ($bron === 'project' && count($contractNrs) > 1) {
            $meldingen[] = count($contractNrs).' contracten in dit project: '.implode(', ', array_slice(array_keys($contractNrs), 0, 10));
        }

        return [count($rijen), $overgeslagen];
    }

    // ---- hulpfuncties -------------------------------------------------

    private function tekst(Worksheet $ws, string $kolom, int $rij): string
    {
        if ($kolom === '') {
            return '';
        }
        try {
            $cel = $ws->getCell($kolom.$rij);
            $v = $cel->getValue();
            if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $v = $v->getPlainText();
            }
            if (is_float($v) && floor($v) == $v && abs($v) < 1e15) {
                $v = (string) (int) $v; // 99060288.0 → 99060288
            }

            return trim((string) $v);
        } catch (\Throwable) {
            return '';
        }
    }

    private function getal(Worksheet $ws, string $kolom, int $rij, float $standaard): float
    {
        $t = str_replace(',', '.', $this->tekst($ws, $kolom, $rij));

        return is_numeric($t) ? (float) $t : $standaard;
    }

    private function datum(Worksheet $ws, string $kolom, int $rij): ?string
    {
        if ($kolom === '') {
            return null;
        }
        try {
            $cel = $ws->getCell($kolom.$rij);
            $v = $cel->getValue();
            if ($v === null || $v === '') {
                return null;
            }
            if (is_numeric($v)) {
                if ((float) $v < 1) {
                    return null; // alleen een tijd (00:00:00)
                }

                return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
            }
            $t = trim((string) $v);
            foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'd-m-y', 'd.m.Y', 'd-m-Y H:i:s', 'Y-m-d H:i:s'] as $f) {
                $d = \DateTime::createFromFormat('!'.$f, $t) ?: \DateTime::createFromFormat($f, $t);
                if ($d && $d->format('Y') > 1990) {
                    return $d->format('Y-m-d');
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** "759 Rotterdam" / "759 - Rotterdam" / "Rotterdam (759)" → [759, 'Rotterdam'] */
    private function splitsDepot(string $raw): array
    {
        if ($raw === '') {
            return [null, null];
        }
        if (preg_match('/^\s*(\d{2,6})\s*[-–:]?\s*(.*)$/u', $raw, $m)) {
            return [$m[1], trim($m[2]) ?: null];
        }
        if (preg_match('/^(.*?)\s*[\(\-–]\s*(\d{2,6})\)?\s*$/u', $raw, $m)) {
            return [$m[2], trim($m[1]) ?: null];
        }

        return [null, $raw];
    }

    /** "72108" / "72108 Brandstoftanks" / "Brandstoftanks (72108)" → [72108, naam|null] */
    private function splitsSubgroep(string $raw): array
    {
        if ($raw === '') {
            return [null, null];
        }
        if (preg_match('/^\s*(\d{3,8})\s*[-–:]?\s*(.*)$/u', $raw, $m)) {
            return [$m[1], trim($m[2]) ?: null];
        }
        if (preg_match('/(\d{3,8})/', $raw, $m)) {
            return [$m[1], trim(str_replace($m[1], '', $raw), " -–:()") ?: null];
        }

        return [null, $raw];
    }

    private function statusCode(string $raw, array $standaard, string $settingKey): string
    {
        $t = mb_strtolower(trim(preg_replace('/\s+/', ' ', $raw)));
        if ($t === '') {
            return 'onbekend';
        }
        $eigen = json_decode((string) Setting::get($settingKey, ''), true);
        $tabel = is_array($eigen) ? array_merge($standaard, array_change_key_case($eigen, CASE_LOWER)) : $standaard;

        return $tabel[$t] ?? 'onbekend';
    }

    private function nummerUit(string $s): ?string
    {
        return preg_match('/(\d{7,})/', $s, $m) ? $m[1] : null;
    }

    /** Alle kolomletters A..AZ voor de keuzelijst. */
    public static function kolomLetters(): array
    {
        $uit = [];
        for ($i = 1; $i <= 52; $i++) {
            $uit[] = Coordinate::stringFromColumnIndex($i);
        }

        return $uit;
    }
}
