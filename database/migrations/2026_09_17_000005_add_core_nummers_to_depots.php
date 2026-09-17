<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Depotnummers uit CORE (leidend); extra nummers = aliassen die naar het hoofdnummer worden herschreven. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depots', function (Blueprint $table) {
            $table->string('nummer_core', 60)->nullable()->after('depot_nummer');   // exact zoals in CORE, bv. "384, 769"
            $table->json('extra_nummers')->nullable()->after('nummer_core');
        });
    }

    public function down(): void
    {
        Schema::table('depots', fn (Blueprint $table) => $table->dropColumn(['nummer_core', 'extra_nummers']));
    }
};
