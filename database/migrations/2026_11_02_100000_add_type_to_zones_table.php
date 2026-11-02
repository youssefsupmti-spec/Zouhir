<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Régime logistique d'une zone (rapport #39, amélioration 2) :
 *  - `locale`    : flotte propre de l'Agence Témara → Bons de Livraison (BL) ;
 *  - `nationale` : réseau partenaire → Bons d'Envoi (BE).
 * Additif et non destructif : les zones existantes prennent `nationale`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('zones', 'type')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->string('type', 20)->default('nationale')->after('code')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('zones', 'type')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            });
        }
    }
};
