<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Aankomende reserveringen (quotes) per depot per subgroep, uit de reserveringen-Excel. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserveringen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->index();
            $table->string('contract_nr', 40)->nullable()->index();
            $table->string('status_raw', 60)->nullable();
            $table->string('district')->nullable();
            $table->string('depot_raw')->nullable();
            $table->string('depot_nummer', 20)->nullable()->index();
            $table->string('depot_naam')->nullable();
            $table->string('subgroep_nr', 30)->nullable()->index();
            $table->string('omschrijving')->nullable();
            $table->date('startdatum')->nullable()->index();
            $table->decimal('aantal', 10, 2)->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserveringen');
    }
};
