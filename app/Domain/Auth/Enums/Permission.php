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
        };
    }
}
