<?php

namespace App\Domain\Auth\Enums;

enum Permission: string
{
    case VIEW_DASHBOARD = 'view_dashboard';
    case MANAGE_COLIS = 'manage_colis';
    case CREATE_COLIS = 'create_colis';
    case TRACK_COLIS = 'track_colis';
    case VIEW_FINANCES = 'view_finances';
    case EXPORT_EXCEL = 'export_excel';
    case MANAGE_USERS = 'manage_users';
    case ACCESS_ADMIN = 'access_admin';

    // Permissions comportementales / périmètres acteurs (compatibilité rôles personnalisés)
    case ACT_AS_CLIENT = 'ActAsClient:Command';
    case ACT_AS_DRIVER = 'ActAsDriver:Command';
    case ACT_AS_AGENCY = 'ActAsAgency:Command';
    case ACT_AS_ADMIN = 'ActAsAdmin:Command';

    // Module Tarifs Clients (Sprint 28) — vérifiées par ClientTarifPolicy.
    case TARIFS_CLIENT_VOIR = 'tarifs_client:voir';
    case TARIFS_CLIENT_CREER = 'tarifs_client:creer';
    case TARIFS_CLIENT_MODIFIER = 'tarifs_client:modifier';
    case TARIFS_CLIENT_SUPPRIMER = 'tarifs_client:supprimer';

    // Domaine Tarifs (Sprint 29) — StandardTarifPolicy / LivreurTarifPolicy.
    case TARIFS_STANDARD_VOIR = 'tarifs_standard:voir';
    case TARIFS_STANDARD_GERER = 'tarifs_standard:gerer';
    case TARIFS_LIVREUR_VOIR = 'tarifs_livreur:voir';
    case TARIFS_LIVREUR_CREER = 'tarifs_livreur:creer';
    case TARIFS_LIVREUR_MODIFIER = 'tarifs_livreur:modifier';
    case TARIFS_LIVREUR_SUPPRIMER = 'tarifs_livreur:supprimer';

    public function label(): string
    {
        return match ($this) {
            self::VIEW_DASHBOARD => 'Accéder au Tableau de Bord',
            self::MANAGE_COLIS => 'Gérer les expéditions',
            self::CREATE_COLIS => 'Créer de nouveaux colis',
            self::TRACK_COLIS => 'Suivi en direct des colis',
            self::VIEW_FINANCES => 'Consulter les relevés CRBT',
            self::EXPORT_EXCEL => 'Exporter les données Excel',
            self::MANAGE_USERS => 'Gestion des comptes utilisateurs',
            self::ACCESS_ADMIN => 'Administration système',
            self::ACT_AS_CLIENT => 'Périmètre Données Marchand (propre compte)',
            self::ACT_AS_DRIVER => 'Périmètre Données Livreur (colis affectés)',
            self::ACT_AS_AGENCY => 'Périmètre Données Agence (hub régional)',
            self::ACT_AS_ADMIN => 'Périmètre Données Administrateur',
            self::TARIFS_CLIENT_VOIR => 'Consulter les tarifs clients',
            self::TARIFS_CLIENT_CREER => 'Négocier de nouveaux tarifs clients',
            self::TARIFS_CLIENT_MODIFIER => 'Modifier les tarifs clients',
            self::TARIFS_CLIENT_SUPPRIMER => 'Supprimer les tarifs clients',
            self::TARIFS_STANDARD_VOIR => 'Consulter les tarifs de livraison standards',
            self::TARIFS_STANDARD_GERER => 'Créer et modifier les tarifs de livraison standards',
            self::TARIFS_LIVREUR_VOIR => 'Consulter les commissions livreurs',
            self::TARIFS_LIVREUR_CREER => 'Affecter des commissions livreurs',
            self::TARIFS_LIVREUR_MODIFIER => 'Modifier les commissions livreurs et leur barème standard',
            self::TARIFS_LIVREUR_SUPPRIMER => 'Supprimer / restaurer des commissions livreurs',
        };
    }

    /** @return list<self> Permissions du module Tarifs Clients. */
    public static function tarifsClient(): array
    {
        return [
            self::TARIFS_CLIENT_VOIR,
            self::TARIFS_CLIENT_CREER,
            self::TARIFS_CLIENT_MODIFIER,
            self::TARIFS_CLIENT_SUPPRIMER,
        ];
    }

    /** @return list<self> Permissions du domaine Tarifs ajoutées au Sprint 29. */
    public static function tarifsSprint29(): array
    {
        return [
            self::TARIFS_STANDARD_VOIR,
            self::TARIFS_STANDARD_GERER,
            self::TARIFS_LIVREUR_VOIR,
            self::TARIFS_LIVREUR_CREER,
            self::TARIFS_LIVREUR_MODIFIER,
            self::TARIFS_LIVREUR_SUPPRIMER,
        ];
    }
}
