<?php

declare(strict_types=1);

namespace App\Domain\Villes\Support;

/**
 * Résultat d'une traduction « libellé ou id → ville » par {@see VilleRef}.
 *
 * `methode` dit COMMENT la ville a été trouvée : c'est ce qui permet à un
 * backfill d'afficher le niveau de confiance de chaque rapprochement.
 */
final readonly class VilleResolution
{
    public const ID = 'id';
    public const EXACTE = 'exacte';
    public const NORMALISEE = 'normalisee';
    public const ALIAS = 'alias';
    public const AMBIGUE = 'ambigue';
    public const INTROUVABLE = 'introuvable';

    /**
     * @param  list<int>  $candidats  identifiants concurrents en cas d'ambiguïté
     */
    public function __construct(
        public ?int $villeId,
        public string $methode,
        public array $candidats = [],
    ) {}

    public function estResolue(): bool
    {
        return $this->villeId !== null;
    }
}
