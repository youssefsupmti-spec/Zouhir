<?php

declare(strict_types=1);

namespace App\Domain\Reprise\Etapes;

use App\Domain\Reprise\JournalAnomalies;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;

/**
 * Étape de la reprise legacy. La connexion `$legacy` n'est utilisée qu'en LECTURE (select/cursor).
 */
abstract class EtapeReprise
{
    public function __construct(
        protected readonly Connection $legacy,
        protected readonly JournalAnomalies $journal,
        protected readonly bool $simulation = false,
        private readonly ?Closure $sortie = null,
    ) {}

    abstract public static function nom(): string;

    /** @return list<string> domaines d'anomalies produits par l'étape */
    abstract public static function domaines(): array;

    /** @return array<string, int|string> chiffres de l'étape */
    abstract public function executer(): array;

    protected function info(string $message): void
    {
        if ($this->sortie !== null) {
            ($this->sortie)($message);
        }
    }

    /** Horodatage Unix legacy (chaîne) → date ; null si absent, non numérique ou négatif. */
    protected static function date(mixed $unix): ?string
    {
        if ($unix === null || ! is_numeric(trim((string) $unix))) {
            return null;
        }
        $n = (int) $unix;

        return $n > 0 ? Carbon::createFromTimestamp($n)->toDateTimeString() : null;
    }

    protected static function texte(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    protected function taille(): int
    {
        return max(100, (int) config('reprise.taille_lot', 1000));
    }
}
