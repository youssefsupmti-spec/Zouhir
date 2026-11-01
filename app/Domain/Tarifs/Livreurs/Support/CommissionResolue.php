<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Livreurs\Support;

use App\Domain\Tarifs\Commun\Support\TarifResolu;
use App\Domain\Tarifs\Livreurs\Enums\TypeCommission;

/**
 * Résolution d'une commission livreur : montants exacts (chaînes « 11.00 ») + provenance.
 * Pour `missing`, les montants sont `null` : une absence n'est jamais 0.00.
 */
final readonly class CommissionResolue
{
    public function __construct(
        public TypeCommission $type,
        public ?string $livraison,
        public ?string $refus,
        public ?int $sourceId = null,
        public ?int $livreurId = null,
        public ?int $villeId = null,
    ) {}

    public static function manquante(?int $livreurId, ?int $villeId): self
    {
        return new self(TypeCommission::Missing, null, null, null, $livreurId, $villeId);
    }

    public function estManquante(): bool
    {
        return $this->type === TypeCommission::Missing;
    }

    /** @return array{type: string, livraison: ?string, refus: ?string, source_id: ?int} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'livraison' => $this->livraison,
            'refus' => $this->refus,
            'source_id' => $this->sourceId,
        ];
    }

    public static function montant(mixed $valeur): string
    {
        return TarifResolu::montant($valeur);
    }
}
