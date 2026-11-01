<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 29 — Délai estimé du barème standard (`gshippingfees.delay` legacy,
 * `standard_tarifs.delay` WinningExpress). NULLABLE, sans défaut : les 8 standards
 * déjà migrés ne sont pas réécrits (leur délai legacy était vide).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('standard_tarifs', 'delai')) {
            return;
        }

        Schema::table('standard_tarifs', function (Blueprint $table) {
            $table->string('delai', 50)->nullable()->after('frais_retour');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('standard_tarifs', 'delai')) {
            Schema::table('standard_tarifs', fn (Blueprint $table) => $table->dropColumn('delai'));
        }
    }
};
