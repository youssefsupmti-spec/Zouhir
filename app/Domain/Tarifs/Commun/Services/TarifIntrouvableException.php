<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Commun\Services;

use RuntimeException;

/** Aucun barème (négocié ni standard) pour ce couple : la facturation doit être bloquée, pas mise à 0. */
final class TarifIntrouvableException extends RuntimeException
{
    public function __construct(public readonly ?int $clientId, public readonly int|string|null $ville)
    {
        parent::__construct(sprintf('Aucun tarif client ni standard (client=%s, ville=%s).', $clientId ?? '—', $ville ?? '—'));
    }
}
