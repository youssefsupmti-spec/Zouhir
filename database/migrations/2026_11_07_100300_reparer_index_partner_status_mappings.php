<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réparation : sous MySQL, le nom auto-généré de l'index unique (66 caractères) dépassait la
 * limite de 64 et l'index n'avait pas été créé. On le pose sous un nom court s'il manque.
 */
return new class extends Migration
{
    private const NOM = 'partner_status_mappings_unique';

    public function up(): void
    {
        // Un index unique sur ces deux colonnes existe déjà (base SQLite, ou installation neuve) : rien à faire.
        foreach (Schema::getIndexes('partner_status_mappings') as $index) {
            if ($index['unique'] && $index['columns'] === ['delivery_partner_id', 'partner_status']) {
                return;
            }
        }
        {
            Schema::table('partner_status_mappings', function (Blueprint $table) {
                $table->unique(['delivery_partner_id', 'partner_status'], self::NOM);
            });
        }
    }

    public function down(): void
    {
        // Rien à défaire : l'index fait partie de la structure voulue par la migration d'origine.
    }
};
