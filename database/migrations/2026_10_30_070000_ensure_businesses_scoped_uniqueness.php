<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration d'alignement de l'unicité des Enseignes Marchandes (Businesses).
 *
 * RÈGLE ARCHITECTURALE (Zakaria2 & WinningExpress) :
 * - `name` n'est JAMAIS unique globalement sur toute la table `businesses` (plusieurs
 *   clients différents peuvent posséder une enseigne nommée "Store", "Boutique", etc.).
 * - L'unicité est STRICTEMENT scopée au niveau du même client propriétaire (`user_id` + `name`).
 * - Backfill automatique : chaque client sans enseigne se voit attribuer une enseigne
 *   par défaut inspirée de son nom (`nom_complet` / `name`), garantissant qu'aucun
 *   client ne se retrouve avec un business vide ou nul.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter l'index composite unique ['user_id', 'name'] si absent
        Schema::table('businesses', function (Blueprint $table) {
            $table->unique(['user_id', 'name'], 'businesses_user_id_name_unique');
        });

        // 2. Backfill des clients existants n'ayant aucune enseigne
        // Récupérer tous les clients (rôle client)
        $clients = User::withTrashed()
            ->where(function ($q) {
                $q->where('role', 'client')
                  ->orWhereHas('roles', fn ($r) => $r->where('name', 'client'));
            })
            ->get();

        foreach ($clients as $client) {
            $hasBusiness = Business::withTrashed()->where('user_id', $client->id)->exists();
            if (! $hasBusiness) {
                $businessName = trim($client->store_name ?? '') 
                    ?: trim($client->nom_complet ?? '') 
                    ?: trim($client->name ?? '') 
                    ?: ('Boutique #' . $client->id);

                // Mettre à jour store_name sur l'utilisateur si vide
                if (empty($client->store_name)) {
                    $client->update(['store_name' => $businessName]);
                }

                Business::updateOrCreate(
                    ['user_id' => $client->id, 'name' => $businessName],
                    [
                        'phone' => $client->telephone ?: $client->phone ?: null,
                        'city' => $client->ville_name ?: $client->city ?: null,
                        'status' => 'active',
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropUnique('businesses_user_id_name_unique');
        });
    }
};
