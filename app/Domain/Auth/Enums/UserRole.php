<?php

namespace App\Domain\Auth\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case CLIENT = 'client';
    case LIVREUR = 'livreur';
    case AGENCE = 'agence';
    case OPERATEUR = 'operateur';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrateur',
            self::CLIENT => 'Client Expéditeur',
            self::LIVREUR => 'Livreur',
            self::AGENCE => 'Responsable Agence',
            self::OPERATEUR => 'Opérateur de Tri',
        };
    }
}
