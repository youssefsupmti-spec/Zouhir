<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capacité journalière d'une zone (colis en cours acceptables) : base de la jauge de charge
 * du Coverage Board. Vide tant qu'elle n'est pas saisie : la jauge affiche alors le seul volume.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('zone_couvertures', 'capacite_journaliere')) {
            Schema::table('zone_couvertures', function (Blueprint $table) {
                $table->unsignedInteger('capacite_journaliere')->nullable()->after('statut_couverture');
                $table->timestamp('bascule_at')->nullable()->after('capacite_journaliere');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('zone_couvertures', 'capacite_journaliere')) {
            Schema::table('zone_couvertures', function (Blueprint $table) {
                $table->dropColumn(['capacite_journaliere', 'bascule_at']);
            });
        }
    }
};
