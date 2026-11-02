<?php

declare(strict_types=1);

namespace App\Domain\Zones\Enums;

/** Régime logistique d'une zone : il détermine le type de bon émis pour ses colis. */
enum TypeZone: string
{
    case LOCALE = 'locale';
    case NATIONALE = 'nationale';

    public function label(): string
    {
        return match ($this) {
            self::LOCALE => 'Local direct',
            self::NATIONALE => 'National partenaire',
        };
    }

    /** Bon logistique associé : BL (flotte propre) ou BE (expédition partenaire). */
    public function bon(): string
    {
        return match ($this) {
            self::LOCALE => 'BL',
            self::NATIONALE => 'BE',
        };
    }

    /** @return list<string> */
    public static function valeurs(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
