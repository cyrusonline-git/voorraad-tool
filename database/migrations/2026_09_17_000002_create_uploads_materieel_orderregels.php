<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * uploads       = elk geüpload Excel-bestand (materieel, contract, project)
 * materieel     = regels van de laatste materieel-upload (waar staat wat, met status)
 * order_regels  = regels van contract- en project-uploads (wat is er nodig)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();            // materieel | contract | project
            $table->string('bestandsnaam');
            $table->string('pad')->nullable();
            $table->string('referentie')->nullable()->index(); // contractnr / projectnr
            $table->string('omschrijving')->nullable();
            $table->string('depot_nummer', 20)->nullable();   // vestiging uit het bestand (contract kolom P)
            $table->unsignedInteger('aantal_rijen')->default(0);
            $table->unsignedInteger('aantal_overgeslagen')->default(0);
            $table->json('meldingen')->nullable();
            $table->boolean('actueel')->default(true);        // materieel: alleen de laatste is actueel
            $table->foreignId('user_id')->nullable();
            $table->string('gebruiker_naam')->nullable();
            $table->timestamps();
        });

        Schema::create('materieel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->index();
            $table->string('uniek_nr', 60)->index();
            $table->string('subgroep_nr', 30)->nullable()->index();
            $table->string('subgroep_naam')->nullable();
            $table->string('omschrijving')->nullable();
            $table->string('depot_raw')->nullable();
            $table->string('depot_nummer', 20)->nullable()->index();
            $table->string('depot_naam')->nullable();
            $table->string('area_raw')->nullable();
            $table->string('status_raw', 60)->nullable();
            $table->string('status_code', 20)->nullable()->index(); // available in_service in_repair on_hire own_use in_transfer onbekend
            $table->date('laatste_uithuur')->nullable();
            $table->json('extra')->nullable();
        });

        Schema::create('order_regels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->index();
            $table->string('bron', 10)->index();              // contract | project
            $table->string('contract_nr', 40)->nullable()->index();
            $table->string('project_nr', 40)->nullable()->index();
            $table->string('project_omschrijving')->nullable();
            $table->unsignedInteger('regel_nr')->nullable();
            $table->string('subgroep_nr', 30)->nullable()->index();
            $table->string('artikel_nr', 60)->nullable()->index();
            $table->string('omschrijving')->nullable();
            $table->string('status_raw', 60)->nullable();
            $table->string('status_code', 20)->nullable()->index(); // not_allocated allocated on_hire off_hire goods_in onbekend
            $table->date('afleverdatum')->nullable();
            $table->date('verhuurdatum')->nullable();
            $table->decimal('aantal', 10, 2)->default(1);
            $table->string('vestiging_nr', 20)->nullable();
            $table->json('extra')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_regels');
        Schema::dropIfExists('materieel');
        Schema::dropIfExists('uploads');
    }
};
