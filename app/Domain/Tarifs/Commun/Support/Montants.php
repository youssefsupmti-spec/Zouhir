<?php

declare(strict_types=1);

namespace App\Domain\Tarifs\Commun\Support;

use Illuminate\Validation\ValidationException;

/**
 * Lecture stricte des montants tarifaires (point unique pour les écritures du domaine Tarifs) :
 * obligatoire, numérique, ≥ 0, 2 décimales max, ≤ 99 999 999.99. 0.00 est un montant valide.
 */
final class Montants
{
    /**
     * @param  array<string, mixed>  $source  valeurs saisies (clés métier : livraison, refus…)
     * @param  array<string, string>  $colonnes  clé métier => colonne en base
     * @return array<string, string> colonne => montant normalisé « 12.50 »
     *
     * @throws ValidationException clé `{prefixe}{cle}`
     */
    public static function lire(array $source, array $colonnes, string $prefixe = ''): array
    {
        $sortie = [];

        foreach ($colonnes as $cle => $colonne) {
            $valeur = $source[$cle] ?? null;
            $texte = is_string($valeur) ? trim(str_replace(',', '.', $valeur)) : $valeur;

            if ($texte === null || $texte === '' || ! is_numeric($texte)) {
                throw ValidationException::withMessages([$prefixe.$cle => 'Montant obligatoire (0 accepté).']);
            }

            if ((float) $texte < 0) {
                throw ValidationException::withMessages([$prefixe.$cle => 'Un montant ne peut pas être négatif.']);
            }

            if (! preg_match('/^\d{1,8}(\.\d{1,2})?$/', (string) $texte)) {
                throw ValidationException::withMessages([$prefixe.$cle => 'Montant invalide : nombre positif, 2 décimales maximum.']);
            }

            $sortie[$colonne] = number_format((float) $texte, 2, '.', '');
        }

        return $sortie;
    }

    /** superieur | inferieur | egal | indisponible — comparaison en centimes entiers. */
    public static function comparer(?string $valeur, ?string $reference): string
    {
        if ($valeur === null || $reference === null) {
            return 'indisponible';
        }

        $centimes = fn (string $m): int => (int) round(((float) $m) * 100);

        return match ($centimes($valeur) <=> $centimes($reference)) {
            1 => 'superieur',
            -1 => 'inferieur',
            default => 'egal',
        };
    }

    /** Écart signé « +2.00 » / « -1.50 » / « 0.00 » ; null si une valeur manque. */
    public static function ecart(?string $valeur, ?string $reference): ?string
    {
        if ($valeur === null || $reference === null) {
            return null;
        }

        $diff = (int) round(((float) $valeur) * 100) - (int) round(((float) $reference) * 100);

        return ($diff > 0 ? '+' : ($diff < 0 ? '-' : '')).number_format(abs($diff) / 100, 2, '.', '');
    }
}
