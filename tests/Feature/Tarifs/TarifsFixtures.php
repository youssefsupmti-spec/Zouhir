<?php

namespace Tests\Feature\Tarifs;

use App\Domain\Auth\Enums\UserRole;
use App\Models\ClientTarif;
use App\Models\StandardTarif;
use App\Models\User;
use App\Models\Ville;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

/** Données de test isolées du module Tarifs Clients (base SQLite en mémoire). */
trait TarifsFixtures
{
    protected Zone $zoneRabat;
    protected Zone $zoneCasa;
    protected Ville $temara;
    protected Ville $skhirate;
    protected Ville $casablanca;
    protected User $admin;
    protected User $clientA;
    protected User $clientB;

    protected function seedTarifs(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->zoneRabat = Zone::create(['name' => 'Rabat-Salé-Kénitra', 'code' => 'RSK']);
        $this->zoneCasa = Zone::create(['name' => 'Casablanca-Settat', 'code' => 'CS']);
        $this->temara = Ville::create(['name_fr' => 'Temara', 'zone_id' => $this->zoneRabat->id, 'code_zone' => 'RSK']);
        $this->skhirate = Ville::create(['name_fr' => 'Skhirate', 'zone_id' => $this->zoneRabat->id, 'code_zone' => 'RSK']);
        $this->casablanca = Ville::create(['name_fr' => 'Casablanca', 'zone_id' => $this->zoneCasa->id, 'code_zone' => 'CS']);

        $this->admin = $this->compte('admin', UserRole::ADMIN, ['nom_complet' => 'Admin Ouaiss']);
        $this->clientA = $this->compte('client', UserRole::CLIENT, ['nom_complet' => 'Fatiha Ahnini', 'email' => 'fatiha@example.test']);
        $this->clientB = $this->compte('client', UserRole::CLIENT, ['nom_complet' => 'PixaMaroc', 'email' => 'pixa@example.test']);
    }

    protected function compte(string $role, UserRole $legacy, array $attrs = []): User
    {
        $user = User::factory()->create(['role' => $legacy] + $attrs);
        $user->assignRole($role);

        return $user;
    }

    /** Compte délégué portant exactement les permissions données (aucun rôle système). */
    protected function delegue(array $permissions): User
    {
        $role = Role::create(['name' => 'gestionnaire_'.count(Role::all()), 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);
        $user = User::factory()->create(['role' => null]);
        $user->assignRole($role);

        return $user;
    }

    protected function tarif(User $client, Ville $ville, string $livraison, string $refus = '0.00', string $retour = '0.00'): ClientTarif
    {
        return ClientTarif::create([
            'client_id' => $client->id,
            'ville_id' => $ville->id,
            'frais_livraison' => $livraison,
            'frais_refus' => $refus,
            'frais_retour' => $retour,
        ]);
    }

    protected function standard(Ville $ville, string $livraison, string $refus = '0.00', string $retour = '0.00'): StandardTarif
    {
        return StandardTarif::create([
            'ville_id' => $ville->id,
            'frais_livraison' => $livraison,
            'frais_refus' => $refus,
            'frais_retour' => $retour,
        ]);
    }
}
