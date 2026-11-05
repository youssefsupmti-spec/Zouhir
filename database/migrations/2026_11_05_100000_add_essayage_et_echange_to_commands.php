<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Options de livraison (additif) :
 *  · essayage_autorise : le destinataire peut essayer l'article — n'a de sens que si l'ouverture
 *    est autorisée (règle appliquée par l'application).
 *  · parent_echange_id / code_echange : pour un échange, l'ancien colis repris au destinataire
 *    (id + copie de son code au moment de la saisie).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            if (! Schema::hasColumn('commands', 'essayage_autorise')) {
                $table->boolean('essayage_autorise')->default(false)->after('ouverture_autorisee');
            }
            if (! Schema::hasColumn('commands', 'parent_echange_id')) {
                $table->foreignId('parent_echange_id')->nullable()->after('echange')->constrained('commands')->nullOnDelete();
                $table->string('code_echange')->nullable()->after('parent_echange_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_echange_id');
            $table->dropColumn(['code_echange', 'essayage_autorise']);
        });
    }
};
