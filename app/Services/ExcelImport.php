<?php

namespace App\Services;

use App\Models\Materieel;
use App\Models\OrderRegel;
use App\Models\Setting;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;

/**
 * Excel-uploads inlezen (streaming via XlsxLezer — de materieellijst heeft
 * 50.000+ regels en moet op shared hosting passen). Welke kolom wat betekent
 * staat per type in de instellingen (Beheer → Kolomindeling) met de standaard
 * van Wim (17-09-2026). Statussen worden vertaald naar vaste codes (NL/EN).
 */
class ExcelImport
{
    /** Standaard kolomindeling per type: veld => kolomletter. */
    public const KOLOMMEN = [
        'materieel' => [
            'uniek_nr' => 'A', 'subgroep' => 'D', 'omschrijving' => 'E', 'merk' => 'F', 'model' => 'G',
            'serienummer' => 'H', 'depot' => 'J', 'area' => 'K', 'status' => 'M', 'laatste_uithuur' => 'N',
            'ontvangen_op_depot' => 'O', 'vorig_depot' => 'P',
        ],
        'contract' => [
            'subgroep' => 'B', 'artikel_nr' => 'C', 'omschrijving' => 'D', 'type' => 'F', 'status' => 'G',
            'afleverdatum' => 'H', 'verhuurdatum' => 'I', 'aantal' => 'J', 'vestiging' => 'P',
        ],
        'project' => [
            'contract_nr' => 'A', 'project_nr' => 'B', 'project_omschrijving' => 'C', 'type' => 'E', 'subgroep' => 'F',
            'omschrijving' => 'G', 'aantal' => 'J', 'artikel_nr' => 'K', 'verhuurdatum' => 'R', 'status' => 'Z',
        ],
    ];

    public const VELD_LABELS = [
        'uniek_nr' => 'Uniek nummer (machinenummer)', 'subgroep' => 'Subgroep (nummer)', 'omschrijving' => 'Omschrijving',
        'merk' => 'Merk', 'model' => 'Model', 'serienummer' => 'Serienummer',
        'depot' => 'Depot (nummer + locatie)', 'area' => 'Area', 'status' => 'Status', 'laatste_uithuur' => 'Laatste uit-huur datum',
        'ontvangen_op_depot' => 'Ontvangen op depot', 'vorig_depot' => 'Vorig depot',
        'artikel_nr' => 'Artikelnummer', 'afleverdatum' => 'Afleverdatum', 'verhuurdatum' => 'Verhuur-/startdatum',
        'aantal' => 'Aantal', 'vestiging' => 'Vestiging (depotnummer)', 'contract_nr' => 'Contractnummer',
        'project_nr' => 'Projectnummer', 'project_omschrijving' => 'Projectomschrijving', 'type' => 'Regeltype (Hire / Sub Group booking)',
    ];

    /** Statusvertaling: genormaliseerde tekst => code. */
    public const STATUS_MATERIEEL = [
        'available' => 'available', 'beschikbaar' => 'available',
        'in service' => 'in_service', 'service' => 'in_service',
        'in repair' => 'in_repair', 'repair' => 'in_repair', 'reparatie' => 'in_repair', 'in reparatie' => 'in_repair',
        'on hire' => 'on_hire', 'in huur' => 'on_hire', 'verhuurd' => 'on_hire',
        'own use' => 'own_use', 'own user' => 'own_use', 'eigen gebruik' => 'own_use',
        'in transfer' => 'in_transfer', 'transfer' => 'in_transfer', 'onderweg' => 'in_transfer',
        'in composed item' => 'composed', 'samenstel' => 'composed',
    ];

    public const STATUS_ORDER = [
        'niet toegekend' => 'not_allocated', 'not allocated' => 'not_allocated', 'unallocated' => 'not_allocated',
        'toegekend' => 'allocated', 'allocated' => 'allocated',
        'inhuur' => 'on_hire', 'in huur' => 'on_hire', 'on hire' => 'on_hire', 'on-hire' => 'on_hire',
        'on-hire/delivered' => 'on_hire', 'on hire/delivered' => 'on_hire', 'delivered' => 'on_hire',
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

    /** Bestand inlezen en opslaan. Geeft de Upload terug (met meldingen). */
    public function importeer(string $type, string $pad, string $bestandsnaam, ?int $userId, ?string $userNaam): Upload
    {
        if (! isset(self::KOLOMMEN[$type])) {
            throw new \InvalidArgumentException("Onbekend uploadtype: $type");
        }
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $upload = Upload::create([
            'type' => $type, 'bestandsnaam' => $bestandsnaam, 'pad' => null,
            'user_id' => $userId, 'gebruiker_naam' => $userNaam, 'actueel' => true,
        ]);

        $meldingen = [];
        try {
            $n = match ($type) {
                'materieel' => $this->leesMaterieel($pad, $bestandsnaam, $upload, $meldingen),
                'contract' => $this->leesOrder($pad, $bestandsnaam, $upload, 'contract', $meldingen),
                'project' => $this->leesOrder($pad, $bestandsnaam, $upload, 'project', $meldingen),
            };
        } catch (\Throwable $e) {
            Materieel::where('upload_id', $upload->id)->delete();
            OrderRegel::where('upload_id', $upload->id)->delete();
            $upload->delete();
            throw $e;
        }

        if ($type === 'materieel') {
            if ($n[0] === 0) {
                $upload->delete();
                throw new \RuntimeException('Geen regels met een uniek nummer gevonden in kolom '.self::kolommen('materieel')['uniek_nr'].'. Klopt de kolomindeling?');
            }
            // Alleen de laatste materieellijst is actueel; oude regels opruimen
            Upload::where('type', 'materieel')->where('id', '!=', $upload->id)->update(['actueel' => false]);
            Materieel::whereIn('upload_id', Upload::where('type', 'materieel')->where('actueel', false)->select('id'))->delete();
            self::herschrijfAliassen($upload->id);
            app(DepotKoppeling::class)->koppelAutomatisch($meldingen);
        }

        $upload->update(['aantal_rijen' => $n[0], 'aantal_overgeslagen' => $n[1], 'meldingen' => array_slice($meldingen, 0, 50)]);

        return $upload;
    }

    // ------------------------------------------------------------------

    /**
     * Rijen van het eerste werkblad streamen: [rijnummer, [kolomletter => waarde]].
     * Geeft ook de bladnaam terug via $bladnaam.
     */
    private function rijen(string $pad, string $bestandsnaam, ?string &$bladnaam): \Generator
    {
        $ext = strtolower(pathinfo($bestandsnaam, PATHINFO_EXTENSION) ?: pathinfo($pad, PATHINFO_EXTENSION));
        if ($ext === 'csv' || $ext === 'txt') {
            $bladnaam = pathinfo($bestandsnaam, PATHINFO_FILENAME);
            $fh = fopen($pad, 'r');
            if (! $fh) {
                throw new \RuntimeException('Kan het CSV-bestand niet openen.');
            }
            $kop = (string) fgets($fh);
            rewind($fh);
            $scheiding = substr_count($kop, ';') > substr_count($kop, ',') ? ';' : ',';
            $rijNr = 0;
            while (($cellen = fgetcsv($fh, 0, $scheiding, '"', '\\')) !== false) {
                $rijNr++;
                if ($rijNr === 1 && isset($cellen[0])) {
                    $cellen[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cellen[0]); // BOM
                }
                $uit = [];
                foreach ($cellen as $i => $waarde) {
                    $uit[self::letter($i + 1)] = $waarde;
                }
                yield $rijNr => $uit;
            }
            fclose($fh);

            return;
        }
        $lezer = new XlsxLezer($pad);
        $bladnaam = $lezer->bladnaam();
        yield from $lezer->rijen();
    }

    private function leesMaterieel(string $pad, string $bestandsnaam, Upload $upload, array &$meldingen): array
    {
        $k = self::kolommen('materieel');
        $start = self::kopRij('materieel') + 1;
        $buffer = [];
        $aantal = 0;
        $overgeslagen = 0;
        $zonderDepot = 0;
        $statusOnbekend = [];
        $bladnaam = null;
        DB::beginTransaction();
        try {
            foreach ($this->rijen($pad, $bestandsnaam, $bladnaam) as $rijNr => $rij) {
                if ($rijNr < $start) {
                    continue;
                }
                $uniek = $this->tekst($rij, $k['uniek_nr']);
                if ($uniek === '') {
                    $overgeslagen++;
                    continue;
                }
                $depotRaw = $this->tekst($rij, $k['depot']);
                [$depotNr, $depotNaam] = self::splitsDepot($depotRaw);
                if ($depotNr === null) {
                    $zonderDepot++;
                }
                $statusRaw = $this->tekst($rij, $k['status']);
                $code = $this->statusCode($statusRaw, self::STATUS_MATERIEEL, 'status_materieel');
                if ($code === 'onbekend' && $statusRaw !== '') {
                    $statusOnbekend[$statusRaw] = true;
                }
                [$subNr, $subNaam] = self::splitsSubgroep($this->tekst($rij, $k['subgroep']));
                $omschrijving = $this->tekst($rij, $k['omschrijving'] ?? '');
                if ($subNaam === null && $omschrijving !== '') {
                    $subNaam = self::splitsSubgroep($omschrijving)[1] ?? $omschrijving;
                }
                $extra = array_filter([
                    'merk' => $this->tekst($rij, $k['merk'] ?? ''),
                    'model' => $this->tekst($rij, $k['model'] ?? ''),
                    'serienummer' => $this->tekst($rij, $k['serienummer'] ?? ''),
                    'ontvangen_op_depot' => $this->datum($rij, $k['ontvangen_op_depot'] ?? ''),
                    'vorig_depot' => $this->tekst($rij, $k['vorig_depot'] ?? ''),
                ], fn ($v) => $v !== '' && $v !== null);
                $buffer[] = [
                    'upload_id' => $upload->id,
                    'uniek_nr' => $uniek,
                    'subgroep_nr' => $subNr,
                    'subgroep_naam' => $subNaam,
                    'omschrijving' => $omschrijving ?: null,
                    'depot_raw' => $depotRaw ?: null,
                    'depot_nummer' => $depotNr,
                    'depot_naam' => $depotNaam,
                    'area_raw' => $this->tekst($rij, $k['area']) ?: null,
                    'status_raw' => $statusRaw ?: null,
                    'status_code' => $code,
                    'laatste_uithuur' => $this->datum($rij, $k['laatste_uithuur']),
                    'extra' => $extra ? json_encode($extra) : null,
                ];
                $aantal++;
                if (count($buffer) >= 500) {
                    DB::table('materieel')->insert($buffer);
                    $buffer = [];
                }
            }
            if ($buffer) {
                DB::table('materieel')->insert($buffer);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $upload->omschrijving = $bladnaam;
        $upload->save();
        foreach (array_keys($statusOnbekend) as $s) {
            $meldingen[] = "Onbekende status '$s' — voeg een vertaling toe bij Beheer → Kolomindeling.";
        }
        if ($zonderDepot > 0) {
            $meldingen[] = "$zonderDepot regels zonder herkenbaar depotnummer in kolom {$k['depot']}.";
        }

        return [$aantal, $overgeslagen];
    }

    private function leesOrder(string $pad, string $bestandsnaam, Upload $upload, string $bron, array &$meldingen): array
    {
        $k = self::kolommen($bron);
        $start = self::kopRij($bron) + 1;
        $buffer = [];
        $aantal = 0;
        $overgeslagen = 0;
        $statusOnbekend = [];
        $contractNrs = [];
        $projectNrs = [];
        $vestigingen = [];
        $regelNr = 0;
        $bladnaam = null;
        DB::beginTransaction();
        try {
            foreach ($this->rijen($pad, $bestandsnaam, $bladnaam) as $rijNr => $rij) {
                if ($rijNr < $start) {
                    continue;
                }
                [$subNr, $subNaam] = self::splitsSubgroep($this->tekst($rij, $k['subgroep']));
                $omschrijving = $this->tekst($rij, $k['omschrijving']);
                $statusRaw = $this->tekst($rij, $k['status']);
                if ($subNr === null && $omschrijving === '' && $statusRaw === '') {
                    $overgeslagen++;
                    continue;
                }
                $regelNr++;
                $code = $this->statusCode($statusRaw, self::STATUS_ORDER, 'status_order');
                if ($code === 'onbekend' && $statusRaw !== '') {
                    $statusOnbekend[$statusRaw] = true;
                }
                $contractNr = $bron === 'project' ? $this->tekst($rij, $k['contract_nr']) : null;
                $projectNr = $bron === 'project' ? $this->tekst($rij, $k['project_nr']) : null;
                $vestiging = $bron === 'contract' ? preg_replace('/\D+/', '', $this->tekst($rij, $k['vestiging'])) : null;
                if ($contractNr) {
                    $contractNrs[$contractNr] = true;
                }
                if ($projectNr) {
                    $projectNrs[$projectNr] = true;
                }
                if ($vestiging) {
                    $vestigingen[$vestiging] = ($vestigingen[$vestiging] ?? 0) + 1;
                }
                $buffer[] = [
                    'upload_id' => $upload->id,
                    'bron' => $bron,
                    'contract_nr' => $contractNr ?: null,
                    'project_nr' => $projectNr ?: null,
                    'project_omschrijving' => $bron === 'project' ? ($this->tekst($rij, $k['project_omschrijving']) ?: null) : null,
                    'regel_nr' => $regelNr,
                    'subgroep_nr' => $subNr,
                    'artikel_nr' => $this->tekst($rij, $k['artikel_nr'] ?? '') ?: null,
                    'omschrijving' => $omschrijving ?: ($subNaam ?: null),
                    'status_raw' => $statusRaw ?: null,
                    'status_code' => $code,
                    'afleverdatum' => $bron === 'contract' ? $this->datum($rij, $k['afleverdatum']) : null,
                    'verhuurdatum' => $this->datum($rij, $k['verhuurdatum']),
                    'aantal' => $this->getal($rij, $k['aantal'], 1),
                    'vestiging_nr' => $vestiging ?: null,
                    'extra' => json_encode(['type' => $this->tekst($rij, $k['type'] ?? '') ?: null]),
                ];
                $aantal++;
                if (count($buffer) >= 500) {
                    DB::table('order_regels')->insert($buffer);
                    $buffer = [];
                }
            }
            if ($buffer) {
                DB::table('order_regels')->insert($buffer);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Referentie: contractnummer uit de bladnaam/bestandsnaam (contract) of projectnummer(s)
        if ($bron === 'contract') {
            $ref = $this->nummerUit((string) $bladnaam) ?: $this->nummerUit($bestandsnaam);
            OrderRegel::where('upload_id', $upload->id)->update(['contract_nr' => $ref]);
            arsort($vestigingen);
            $upload->depot_nummer = $vestigingen ? (string) array_key_first($vestigingen) : null;
        } else {
            $ref = implode(', ', array_keys($projectNrs));
        }
        $upload->referentie = $ref ?: null;
        $upload->omschrijving = $bladnaam;
        $upload->save();

        foreach (array_keys($statusOnbekend) as $s) {
            $meldingen[] = "Onbekende status '$s' — voeg een vertaling toe bij Beheer → Kolomindeling.";
        }
        if ($bron === 'project' && count($contractNrs) > 1) {
            $meldingen[] = count($contractNrs).' contracten in dit project: '.implode(', ', array_slice(array_keys($contractNrs), 0, 10));
        }

        return [$aantal, $overgeslagen];
    }

    /** Depot-aliassen (bv. 769 *Industrial Chemelot) herschrijven naar het hoofdnummer (384) van het CORE-depot. */
    public static function herschrijfAliassen(int $uploadId): void
    {
        foreach (\App\Models\Depot::aliasKaart() as $alias => $hoofd) {
            Materieel::where('upload_id', $uploadId)->where('depot_nummer', $alias)->update(['depot_nummer' => $hoofd]);
        }
    }

    // ---- hulpfuncties -------------------------------------------------

    private function tekst(array $rij, string $kolom): string
    {
        if ($kolom === '' || ! array_key_exists($kolom, $rij)) {
            return '';
        }
        $v = $rij[$kolom];
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
        }
        if (is_float($v) && floor($v) == $v && abs($v) < 1e15) {
            $v = (string) (int) $v; // 99060288.0 → 99060288
        }
        $t = trim((string) $v);

        return $t === '-' ? '' : $t;
    }

    private function getal(array $rij, string $kolom, float $standaard): float
    {
        $t = str_replace(',', '.', $this->tekst($rij, $kolom));

        return is_numeric($t) ? (float) $t : $standaard;
    }

    private function datum(array $rij, string $kolom): ?string
    {
        if ($kolom === '' || ! array_key_exists($kolom, $rij)) {
            return null;
        }
        $v = $rij[$kolom];
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y') > 1990 ? $v->format('Y-m-d') : null;
        }
        if ($v === null || $v === '' || $v === '-') {
            return null;
        }
        if (is_numeric($v)) {
            if ((float) $v < 1000) {
                return null; // alleen een tijd of onzin
            }
            $d = \DateTime::createFromFormat('!Y-m-d', '1899-12-30')->modify('+'.(int) $v.' days');

            return $d->format('Y-m-d');
        }
        $t = trim((string) $v);
        $t = preg_replace('/\.\d+$/', '', $t); // 2025-10-06 14:39:43.190000
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'd-m-y', 'd.m.Y', 'd-m-Y H:i:s', 'Y-m-d H:i:s', 'd/m/Y H:i:s'] as $f) {
            $d = \DateTime::createFromFormat('!'.$f, $t) ?: \DateTime::createFromFormat($f, $t);
            if ($d && $d->format('Y') > 1990) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    /** "759 - Industrial Rotterdam" / "759 Rotterdam" / "Rotterdam (759)" → [759, 'Industrial Rotterdam'] */
    public static function splitsDepot(string $raw): array
    {
        $raw = trim($raw);
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

    /** "72108" / "72108 - Brandstoftanks" / "Brandstoftanks (72108)" → [72108, naam|null] */
    public static function splitsSubgroep(string $raw): array
    {
        $raw = trim($raw);
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

    private array $statusTabellen = [];

    private function statusCode(string $raw, array $standaard, string $settingKey): string
    {
        $t = mb_strtolower(trim(preg_replace('/\s+/', ' ', $raw)));
        if ($t === '') {
            return 'onbekend';
        }
        if (! isset($this->statusTabellen[$settingKey])) {
            $eigen = json_decode((string) Setting::get($settingKey, ''), true);
            $this->statusTabellen[$settingKey] = is_array($eigen) ? array_merge($standaard, array_change_key_case($eigen, CASE_LOWER)) : $standaard;
        }

        return $this->statusTabellen[$settingKey][$t] ?? 'onbekend';
    }

    private function nummerUit(string $s): ?string
    {
        return preg_match('/(\d{7,})/', $s, $m) ? $m[1] : null;
    }

    /** 1 → A, 27 → AA */
    public static function letter(int $index): string
    {
        $s = '';
        while ($index > 0) {
            $index--;
            $s = chr(65 + ($index % 26)).$s;
            $index = intdiv($index, 26);
        }

        return $s;
    }

    /** Alle kolomletters A..AZ voor de keuzelijst. */
    public static function kolomLetters(): array
    {
        return array_map([self::class, 'letter'], range(1, 52));
    }
}
