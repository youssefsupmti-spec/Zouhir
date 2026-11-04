<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index de la page Colis (93 000 lignes) :
 *  · (deleted_at, updated_at)              tri par défaut « dernière mise à jour », onglet Tous / Corbeille
 *  · (deleted_at, state_group, updated_at) onglet d'un groupe, trié
 *  · (deleted_at, state_group, prix_brut)  compteurs des onglets et CRBT agrégés (index couvrant)
 */
return new class extends Migration
{
    private const INDEX = [
        'commands_page_maj_index' => ['deleted_at', 'updated_at'],
        'commands_page_groupe_maj_index' => ['deleted_at', 'state_group', 'updated_at'],
        'commands_page_groupe_crbt_index' => ['deleted_at', 'state_group', 'prix_brut'],
    ];

    public function up(): void
    {
        $existants = collect(Schema::getIndexes('commands'))->pluck('name')->all();
        Schema::table('commands', function (Blueprint $table) use ($existants) {
            foreach (self::INDEX as $nom => $colonnes) {
                if (! in_array($nom, $existants, true)) {
                    $table->index($colonnes, $nom);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            foreach (array_keys(self::INDEX) as $nom) {
                $table->dropIndex($nom);
            }
        });
    }
};
