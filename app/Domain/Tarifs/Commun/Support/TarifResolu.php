<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Commun\Support;

use App\Domain\Tarifs\Commun\Enums\TypeTarif;

/**
 * Snapshot d'une résolution tarifaire : montants + règle appliquée.
 *
 * Les montants sont des chaînes décimales exactes (« 15.00 ») : aucun calcul
 * flottant. Pour `missing`, les montants sont `null` — une ABSENCE de tarif
 * n'est jamais représentée par 0.00 (qui signifierait « gratuit »).
 */
final readonly class TarifResolu
{
    public function __construct(
        public TypeTarif $type,
        public ?string $livraison,
        public ?string $refus,
        public ?string $retour,
        public ?int $sourceId = null,
        public ?int $clientId = null,
        public ?int $villeId = null,
    ) {}

    public static function manquant(?int $clientId, ?int $villeId): self
    {
        return new self(TypeTarif::Missing, null, null, null, null, $clientId, $villeId);
    }

    public function estManquant(): bool
    {
        return $this->type === TypeTarif::Missing;
    }

    /** @return array{type: string, livraison: ?string, refus: ?string, retour: ?string, source_id: ?int} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'livraison' => $this->livraison,
            'refus' => $this->refus,
            'retour' => $this->retour,
            'source_id' => $this->sourceId,
        ];
    }

    public static function montant(mixed $valeur): string
    {
        return number_format((float) $valeur, 2, '.', '');
    }
}
