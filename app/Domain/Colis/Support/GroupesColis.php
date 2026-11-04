<?php

declare(strict_types=1);

namespace App\Domain\Colis\Support;

/**
 * Groupes d'état des colis (commands.state_group) — onglets de la page Colis.
 *
 * Même découpage que « Tous les colis » de WinningExpress / Zakaria2 :
 *   nouveau  : pas encore pris en charge (Ajouté)
 *   en_cours : ramassé, en distribution, en voyage…
 *   planifie : date convenue avec le destinataire (Programmé, Reporté)
 *   livre    : livré (ou échangé)
 *   echec    : échec de livraison (annulé, refusé, pas de réponse, injoignable, hors zone…)
 *   retour_agence : colis en retour, arrivé ou attendu à l'agence / au hub
 *   retour_stock  : colis remis en stock (entrepôt du marchand)
 *   retour_client : colis rendu au marchand (« Retour client reçu »)
 * La corbeille n'est PAS un groupe : c'est la suppression logique (deleted_at), onglet à part.
 */
final class GroupesColis
{
    public const LIBELLES = [
        'nouveau' => 'Nouveau',
        'en_cours' => 'En cours',
        'planifie' => 'Planifié',
        'livre' => 'Livré',
        'echec' => 'Échoué',
        'retour_agence' => 'Retour agence',
        'retour_stock' => 'Retour stock',
        'retour_client' => 'Retour client',
    ];

    /** Les trois groupes de retour (statistiques « retours »). */
    public const RETOURS = ['retour_agence', 'retour_stock', 'retour_client'];

    /** Statut posé par un changement d'état depuis la plateforme (libellé du référentiel). */
    public const STATUT_PAR_DEFAUT = [
        'nouveau' => 'Ajouté',
        'en_cours' => 'Mise en distribution',
        'planifie' => 'Reporté',
        'livre' => 'Livré',
        'echec' => 'Annulé',
        'retour_agence' => 'Retourné',
        'retour_stock' => 'Retourné vers stock',
        'retour_client' => 'Retour client reçu',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::LIBELLES);
    }
}
