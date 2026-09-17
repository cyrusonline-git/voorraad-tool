<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Minimale voorraad per depot per subgroep (ingesteld door werkplaats/manager/beheer). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('min_voorraad', function (Blueprint $table) {
            $table->id();
            $table->string('depot_nummer', 20)->index();
            $table->string('subgroep_nr', 30)->index();
            $table->string('subgroep_naam')->nullable();
            $table->unsignedInteger('minimum')->default(0);
            $table->string('gewijzigd_door')->nullable();
            $table->timestamps();
            $table->unique(['depot_nummer', 'subgroep_nr']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_voorraad');
    }
};
