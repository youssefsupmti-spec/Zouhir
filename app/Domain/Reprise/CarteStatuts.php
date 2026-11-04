<?php

declare(strict_types=1);

namespace App\Domain\Reprise;

/**
 * Groupe (nouveau | en_cours | planifie | livre | echec | retour_agence | retour_stock | retour_client) d'un libellé de statut legacy —
 * découpage de « Tous les colis » de WinningExpress / Zakaria2 (voir GroupesColis).
 *
 * Table EXPLICITE, libellé par libellé (casse et accents ignorés) — pas de recherche de mots-clés.
 * Seule règle de motif : les relances « pas de réponse … » (≈ 15 variantes saisies à la main) sont
 * des échecs de livraison, comme dans WinningExpress. Un libellé absent de la table renvoie null : la reprise le classe « en_cours »
 * et le signale pour décision.
 */
final class CarteStatuts
{
    /** @var array<string, string> */
    private const GROUPES = [
        'ajoute' => 'nouveau',

        'ramasse' => 'en_cours',
        'recu par livreur' => 'en_cours',
        'mise en distribution' => 'en_cours',
        'pret pour expedition' => 'en_cours',
        'expedie' => 'en_cours',
        'en voyage' => 'en_cours',
        'interesse' => 'en_cours',
        'changement adresse' => 'en_cours',
        'changement numero' => 'en_cours',
        'demande de suivi' => 'en_cours',
        'change' => 'en_cours',

        'programme' => 'planifie',
        'reporte' => 'planifie',

        'livre' => 'livre',
        'echange' => 'livre',

        'retourne' => 'retour_agence',
        'retourne vers agence casablanca' => 'retour_agence',
        'demande de retour' => 'retour_agence',
        'routourne hub temara' => 'retour_agence',
        'retour recu agence casablanca' => 'retour_agence',
        'retour recu agence casa' => 'retour_agence',
        'retour recu agence sale' => 'retour_agence',

        'retourne vers stock' => 'retour_stock',

        'retour client recu' => 'retour_client',
        'retourne client' => 'retour_client',

        'annule' => 'echec',
        'refuse' => 'echec',
        'injoignable' => 'echec',
        'hors zone' => 'echec',
        'manque de stock' => 'echec',
        'perdu' => 'echec',
        'pas interesse' => 'echec',
    ];

    /** Événements d'historique qui datent un jalon du colis (libellés normalisés). */
    public const JALON_LIVRE = ['livre'];

    public const JALON_RAMASSE = ['ramasse'];

    public const JALON_EXPEDIE = ['mise en distribution', 'pret pour expedition'];

    public const JALON_RETOUR_CLIENT = ['retour client recu'];

    public const JALON_RETOUR_AGENCE = ['routourne hub temara', 'retour recu agence casablanca', 'retour recu agence casa', 'retour recu agence sale'];

    public static function groupe(string $libelle): ?string
    {
        $cle = self::cle($libelle);

        if (isset(self::GROUPES[$cle])) {
            return self::GROUPES[$cle];
        }

        if (preg_match('/^pas de reponse\b/', $cle) === 1) {
            return 'echec';
        }

        return null;
    }

    public static function cle(string $libelle): string
    {
        $s = mb_strtolower(trim($libelle));
        $s = strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'û' => 'u', 'ù' => 'u', 'ç' => 'c']);

        return trim((string) preg_replace('/\s+/', ' ', $s));
    }
}
