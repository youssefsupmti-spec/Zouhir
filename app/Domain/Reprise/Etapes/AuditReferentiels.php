<?php

declare(strict_types=1);

namespace App\Domain\Reprise\Etapes;

use Illuminate\Support\Facades\DB;

/**
 * Contrôles de la base du nouveau projet (aucune lecture legacy, aucune écriture hors anomalies) :
 * enseignes, zones vides, RIB. Signale, ne corrige jamais.
 */
final class AuditReferentiels extends EtapeReprise
{
    private const CODES = ['enseigne_url', 'client_sans_enseigne_compte', 'zone_sans_ville', 'rib_longueur', 'rib_code_banque', 'rib_sans_banque'];

    public static function nom(): string
    {
        return 'controles';
    }

    public static function domaines(): array
    {
        return ['clients', 'zones', 'banques'];
    }

    public function executer(): array
    {
        $n = 0;

        foreach (DB::table('businesses')->whereNull('deleted_at')->get(['id', 'user_id', 'name']) as $b) {
            if (preg_match('~^(https?://|www\.)~i', trim((string) $b->name)) === 1) {
                $n++;
                $this->journal->signaler('clients', 'enseigne_url', 'decision', "Enseigne #{$b->id} : le nom est une adresse web (« ".mb_substr((string) $b->name, 0, 80).' ») — quel nom commercial ?', 'businesses', $b->id, (string) $b->name, [], 'businesses', (int) $b->id);
            }
        }

        $clientsSansEnseigne = DB::table('users')->where('role', 'client')->whereNull('deleted_at')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('businesses')->whereColumn('businesses.user_id', 'users.id'))
            ->get(['id', 'nom_complet']);
        foreach ($clientsSansEnseigne as $u) {
            $n++;
            $this->journal->signaler('clients', 'client_sans_enseigne_compte', 'attention', "Client #{$u->id} « {$u->nom_complet} » sans enseigne", 'users', $u->id, null, [], 'users', (int) $u->id);
        }

        $zonesVides = DB::table('zones')->whereNull('zones.deleted_at')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('villes')->whereColumn('villes.zone_id', 'zones.id')->whereNull('villes.deleted_at'))
            ->get(['id', 'name', 'code']);
        foreach ($zonesVides as $z) {
            $n++;
            $this->journal->signaler('zones', 'zone_sans_ville', 'attention', "Zone « {$z->name} » ({$z->code}) sans aucune ville", 'zones', $z->id, null, [], 'zones', (int) $z->id);
        }

        if (DB::getSchemaBuilder()->hasTable('comptes_bancaires')) {
            $banques = DB::table('banks')->pluck('code_etablissement', 'id');
            foreach (DB::table('comptes_bancaires')->whereNull('deleted_at')->get(['id', 'owner_id', 'titulaire_nom', 'bank_id', 'bank_name', 'rib']) as $c) {
                $chiffres = preg_replace('/\D/', '', (string) $c->rib);
                $libelle = "RIB de « {$c->titulaire_nom} » (compte #{$c->id}, {$c->bank_name})";
                if (strlen((string) $chiffres) !== 24) {
                    $n++;
                    $this->journal->signaler('banques', 'rib_longueur', 'attention', "{$libelle} : ".strlen((string) $chiffres).' chiffres au lieu de 24', 'comptes_bancaires', $c->id, null, ['longueur' => strlen((string) $chiffres)], 'comptes_bancaires', (int) $c->id);
                }
                if ($c->bank_id === null) {
                    $n++;
                    $this->journal->signaler('banques', 'rib_sans_banque', 'attention', "{$libelle} : aucune banque du référentiel", 'comptes_bancaires', $c->id, null, [], 'comptes_bancaires', (int) $c->id);
                } elseif (($code = $banques[$c->bank_id] ?? null) && strlen((string) $chiffres) >= 3 && substr((string) $chiffres, 0, 3) !== $code) {
                    $n++;
                    $this->journal->signaler('banques', 'rib_code_banque', 'attention', "{$libelle} : le RIB commence par ".substr((string) $chiffres, 0, 3)." alors que le code de la banque est {$code}", 'comptes_bancaires', $c->id, null, [], 'comptes_bancaires', (int) $c->id);
                }
            }
        }

        $this->journal->cloturer(self::domaines(), self::CODES);

        return ['controles_en_ecart' => $n];
    }
}
