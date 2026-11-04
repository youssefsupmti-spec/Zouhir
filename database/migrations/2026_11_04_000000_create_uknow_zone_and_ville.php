<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Crée la zone 'Uknow-zone' et la ville 'Uknow-ville' pour rattacher
     * les colis sans ville renseignée (city null, vide ou '=') afin qu'ils
     * soient modifiables et adaptables ultérieurement sans violer les clés étrangères.
     */
    public function up(): void
    {
        if (! Schema::hasTable('zones') || ! Schema::hasTable('villes')) {
            return;
        }

        // 1. Création idempotente de la zone 'Uknow-zone'
        $zone = DB::table('zones')
            ->where('code', 'UKN')
            ->orWhere('name', 'Uknow-zone')
            ->first();

        if (! $zone) {
            $hasType = Schema::hasColumn('zones', 'type');
            $data = [
                'name' => 'Uknow-zone',
                'code' => 'UKN',
                'description' => 'Zone d\'attente pour colis sans ville ou destination inconnue (à adapter/corriger)',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasType) {
                $data['type'] = 'en_attente';
            }
            $zoneId = DB::table('zones')->insertGetId($data);
        } else {
            $zoneId = $zone->id;
        }

        // 2. Création idempotente de la ville 'Uknow-ville' rattachée à 'Uknow-zone'
        $ville = DB::table('villes')
            ->where('name_fr', 'Uknow-ville')
            ->first();

        if (! $ville) {
            DB::table('villes')->insert([
                'name_fr' => 'Uknow-ville',
                'name_ar' => 'مدينة غير محددة',
                'zone_id' => $zoneId,
                'code_zone' => 'UKN',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // S'assurer de la liaison avec la zone
            DB::table('villes')
                ->where('id', $ville->id)
                ->update([
                    'zone_id' => $zoneId,
                    'code_zone' => 'UKN',
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('villes')) {
            DB::table('villes')->where('name_fr', 'Uknow-ville')->delete();
        }
        if (Schema::hasTable('zones')) {
            DB::table('zones')->where('code', 'UKN')->orWhere('name', 'Uknow-zone')->delete();
        }
    }
};

