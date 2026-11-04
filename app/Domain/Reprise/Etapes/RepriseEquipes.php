<?php

declare(strict_types=1);

namespace App\Domain\Reprise\Etapes;

use App\Models\Agence;
use Illuminate\Support\Facades\DB;

/**
 * Livreurs rattachés à l'agence principale (agence_membres) et comptes partenaires (partenaires).
 * Les livreurs sont les comptes `role = livreur` déjà repris (mêmes ids que l'ancien site).
 */
final class RepriseEquipes extends EtapeReprise
{
    public static function nom(): string
    {
        return 'equipes';
    }

    public static function domaines(): array
    {
        return ['livreurs'];
    }

    public function executer(): array
    {
        $agence = Agence::principale();
        $partenairesIds = config('reprise.comptes_partenaires', []);
        $legacyUsers = $this->legacy->table('users')->get()->keyBy('id');
        $bonsParCompte = $this->legacy->table('bls')->where('dlm', '>', 0)->selectRaw('dlm, COUNT(*) AS n')->groupBy('dlm')->pluck('n', 'dlm');

        $partenaires = 0;
        foreach ($partenairesIds as $id) {
            $user = DB::table('users')->where('id', $id)->first();
            if ($user === null) {
                $this->journal->signaler('livreurs', 'compte_partenaire_absent', 'bloquant', "Compte partenaire #{$id} introuvable dans la plateforme", 'users', $id);

                continue;
            }
            $partenaires++;
            $this->journal->signaler('livreurs', 'compte_partenaire', 'decision',
                "Compte « {$user->nom_complet} » (#{$id}) traité comme partenaire national : ses ".((int) ($bonsParCompte[$id] ?? 0)).' bons BL deviennent des bons d’envoi',
                'users', $id, null, ['bons' => (int) ($bonsParCompte[$id] ?? 0)], 'users', (int) $id);

            if (! $this->simulation) {
                DB::table('partenaires')->updateOrInsert(['user_id' => $id], [
                    'code' => 'PART-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT),
                    'raison_sociale' => $user->nom_complet,
                    'telephone' => $user->telephone,
                    'actif' => $user->deleted_at === null,
                    'notes' => 'Compte de l’ancien site utilisé pour les envois au réseau national (reprise C140).',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }

        $livreurs = DB::table('users')->where('role', 'livreur')->whereNotIn('id', $partenairesIds)->orderBy('id')->get();
        $membres = 0;
        foreach ($livreurs as $u) {
            $membres++;
            $nom = mb_strtolower((string) $u->nom_complet);
            if (str_contains($nom, 'agence') || str_contains($nom, 'ramassage') || str_contains($nom, 'test')) {
                $this->journal->signaler('livreurs', 'compte_livreur_atypique', 'decision',
                    "Compte livreur « {$u->nom_complet} » (#{$u->id}) : nom d’agence, de fonction ou de test — vrai livreur ?",
                    'users', $u->id, $u->nom_complet, ['bons' => (int) ($bonsParCompte[$u->id] ?? 0)], 'users', (int) $u->id);
            }
            if (! isset($legacyUsers[$u->id])) {
                continue; // compte créé dans la plateforme : hors reprise
            }
            if (! $this->simulation && $agence !== null) {
                DB::table('agence_membres')->updateOrInsert(
                    ['agence_id' => $agence->id, 'user_id' => $u->id, 'role' => 'livreur'],
                    ['actif' => $u->deleted_at === null && (bool) $u->active, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        $this->journal->cloturer(self::domaines(), ['compte_partenaire_absent', 'compte_partenaire', 'compte_livreur_atypique']);

        return ['partenaires' => $partenaires, 'livreurs_rattaches' => $membres];
    }
}
