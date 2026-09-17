<?php

namespace App\Services;

use App\Models\Depot;
use App\Models\Materieel;

/**
 * Depotnummers uit de materieellijst ("759 - Industrial Rotterdam") koppelen
 * aan de CORE-depots (naam uit Beheer → Infrastructuur). Automatisch op naam
 * waar dat eenduidig kan; de beheerder kan het altijd overschrijven.
 */
class DepotKoppeling
{
    /** Depots zoals ze in de actuele materieellijst voorkomen: nummer => [naam, aantal]. */
    public static function uitMaterieel(): array
    {
        $uit = [];
        $rijen = Materieel::actueel()->whereNotNull('depot_nummer')
            ->selectRaw('depot_nummer, max(depot_naam) as depot_naam, count(*) as n')
            ->groupBy('depot_nummer')->orderByDesc('n')->get();
        foreach ($rijen as $r) {
            $uit[$r->depot_nummer] = ['naam' => $r->depot_naam, 'aantal' => (int) $r->n];
        }

        return $uit;
    }

    public function koppelAutomatisch(array &$meldingen = []): int
    {
        $gezien = self::uitMaterieel();
        $depots = Depot::all();
        $bezet = $depots->whereNotNull('depot_nummer')->pluck('depot_nummer')->all();
        $gekoppeld = 0;
        foreach ($gezien as $nr => $info) {
            if (in_array($nr, $bezet, true)) {
                continue;
            }
            $kandidaten = $depots->filter(fn ($d) => $d->depot_nummer === null && ! $d->nummerUitCore() && self::naamMatch($d->naam, (string) $info['naam']));
            if ($kandidaten->count() === 1) {
                $kandidaten->first()->update(['depot_nummer' => (string) $nr]);
                $bezet[] = (string) $nr;
                $gekoppeld++;
            }
        }
        $ongekoppeld = array_diff(array_keys($gezien), $bezet);
        if ($ongekoppeld) {
            $meldingen[] = 'Depotnummers zonder CORE-depot: '.implode(', ', array_map(fn ($n) => "$n ({$gezien[$n]['naam']})", $ongekoppeld)).' — vul het nummer in bij Boels CORE → Beheer → Infrastructuur (of als terugval bij Beheer → Depots).';
        }

        return $gekoppeld;
    }

    /** "Rotterdam-Europoort" ~ "Industrial Rotterdam", "Geleen - Chemelot" ~ "Industrial Chemelot" */
    public static function naamMatch(string $coreNaam, string $excelNaam): bool
    {
        $a = self::normaliseer($coreNaam);
        $b = self::normaliseer($excelNaam);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b || str_contains($a, $b) || str_contains($b, $a)) {
            return true;
        }
        // Woord-overlap: elk woord van de Excel-naam komt voor in de CORE-naam
        $woordenB = array_filter(explode(' ', $b), fn ($w) => strlen($w) > 3);
        if (! $woordenB) {
            return false;
        }
        foreach ($woordenB as $w) {
            if (! str_contains($a, $w)) {
                return false;
            }
        }

        return true;
    }

    private static function normaliseer(string $s): string
    {
        $s = mb_strtolower($s);
        $s = str_replace(['*', 'industrial', 'boels', '-', '_', '/', '(', ')', "'", '’'], ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }
}
