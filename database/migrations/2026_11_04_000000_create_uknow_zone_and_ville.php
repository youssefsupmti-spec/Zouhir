<?php

use App\Domain\Villes\Support\VilleInconnue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zone « Uknow-zone » et ville « Uknow-ville » (rapport 56) pour les colis sans ville.
 * Uniquement sur une base dont le référentiel des villes est déjà chargé : une base vide
 * (installation neuve, tests) n'en a pas besoin — la reprise des colis la crée au besoin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zones') || ! Schema::hasTable('villes') || DB::table('villes')->count() === 0) {
            return;
        }

        VilleInconnue::assurer();
    }

    public function down(): void
    {
        // Données de référence : conservées (des colis peuvent y être rattachés).
    }
};
