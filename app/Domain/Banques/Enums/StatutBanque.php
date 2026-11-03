<?php

declare(strict_types=1);

namespace App\Domain\Banques\Enums;

/** Statut d'une banque du référentiel (valeurs stockées dans `banks.status`). */
enum StatutBanque: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDUE = 'suspendue';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::SUSPENDUE => 'Suspendue',
        };
    }

    /** @return list<string> */
    public static function valeurs(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
