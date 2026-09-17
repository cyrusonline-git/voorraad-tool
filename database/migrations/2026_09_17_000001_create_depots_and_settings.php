<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Depots/areas komen uit Boels CORE (GET /api/infrastructure) en worden hier
 * gespiegeld; per depot bewaren we extra het depotnummer zoals dat in de
 * materieel-Excel staat (kolom J) en een eventueel afwijkend mailadres.
 * settings = losse instellingen (sleutel/waarde) voor de beheerder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depots', function (Blueprint $table) {
            $table->id();
            $table->string('naam')->unique();          // exact zoals in CORE
            $table->string('area')->nullable()->index();
            $table->string('business_unit')->nullable();
            $table->string('land', 5)->nullable();
            $table->string('plaats')->nullable();
            $table->string('email_core')->nullable();  // uit CORE
            $table->string('email')->nullable();       // hier overschrijfbaar (aanvraagmail)
            $table->string('depot_nummer', 20)->nullable()->index(); // nummer in de materieel-Excel
            $table->boolean('actief')->default(true);
            $table->unsignedInteger('volgorde')->default(0);
            $table->timestamp('gesynct_op')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('depots');
    }
};
