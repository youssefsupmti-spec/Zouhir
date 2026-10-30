<?php

namespace App\Domain\Clients\Enums;

enum ClientType: string
{
    case E_COMMERCE = 'e_commerce';
    case ENTREPRISE = 'entreprise';
    case PARTICULIER = 'particulier';

    public function label(): string
    {
        return match ($this) {
            self::E_COMMERCE => 'E-commerçant',
            self::ENTREPRISE => 'Entreprise / Société',
            self::PARTICULIER => 'Particulier',
        };
    }
}
