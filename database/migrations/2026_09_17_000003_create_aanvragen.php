<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Verstuurde aanvraagmails aan depots (logboek voor binnendienst, manager en fleet). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aanvragen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->nullable()->index();
            $table->string('upload_type', 20)->nullable();
            $table->string('referentie')->nullable()->index();      // contract-/projectnummer
            $table->string('omschrijving')->nullable();
            $table->string('depot_nummer', 20)->nullable()->index(); // aangeschreven depot
            $table->string('depot_naam')->nullable();
            $table->string('eigen_depot_nummer', 20)->nullable();
            $table->string('eigen_depot_naam')->nullable();
            $table->string('aan_email')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('cc')->nullable();
            $table->string('onderwerp')->nullable();
            $table->text('body')->nullable();
            $table->json('machines')->nullable();                    // [{uniek_nr, subgroep_nr, omschrijving, status}]
            $table->unsignedInteger('aantal_machines')->default(0);
            $table->text('opmerking')->nullable();
            $table->date('reactie_voor')->nullable();
            $table->date('verhuurdatum')->nullable();
            $table->string('status', 20)->default('verzonden');     // verzonden | mislukt
            $table->text('fout')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('aanvrager_naam')->nullable();
            $table->string('aanvrager_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aanvragen');
    }
};
