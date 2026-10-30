<?php

namespace App\Domain\Colis\Enums;

enum ColisStatus: string
{
    case NOUVEAU = 'nouveau';
    case RAMASSE = 'ramasse';
    case EXPEDIE = 'expedie';
    case EN_TRANSIT = 'en_transit';
    case EN_COURS_LIVRAISON = 'en_cours_livraison';
    case LIVRE = 'livre';
    case REPORTE = 'reporte';
    case RETOUR_EN_COURS = 'retour_en_cours';
    case RETOURNE = 'retourne';
    case ANNULE = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::NOUVEAU => 'Créé / En attente',
            self::RAMASSE => 'Ramassé au hub',
            self::EXPEDIE => 'Expédié vers agence',
            self::EN_TRANSIT => 'En transit inter-villes',
            self::EN_COURS_LIVRAISON => 'En cours de distribution',
            self::LIVRE => 'Colis Livré & Encaissé',
            self::REPORTE => 'Livraison reportée',
            self::RETOUR_EN_COURS => 'Retour en cours',
            self::RETOURNE => 'Colis retourné',
            self::ANNULE => 'Commande annulée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LIVRE => 'emerald',
            self::EN_COURS_LIVRAISON, self::EN_TRANSIT => 'amber',
            self::RETOURNE, self::ANNULE => 'red',
            default => 'blue',
        };
    }
}
