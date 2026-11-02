<?php

declare(strict_types=1);

namespace App\Domain\Zones\Enums;

/** Régime logistique d'une zone : régional (flotte de l'Agence Témara) ou national (réseau partenaire). */
enum TypeZone: string
{
    case LOCALE = 'locale';
    case NATIONALE = 'nationale';

    public function label(): string
    {
        return match ($this) {
            self::LOCALE => 'Régional',
            self::NATIONALE => 'National',
        };
    }

    /** @return list<string> */
    public static function valeurs(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
