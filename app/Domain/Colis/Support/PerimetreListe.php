<?php

declare(strict_types=1);

namespace App\Domain\Colis\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Qui apparaît dans quelle liste de colis (même règle que l'ancien site :
 * « state NOT IN('à ramasser') AND collected = 1 », reprise par WinningExpress / Zakaria2).
 *
 *  · « Colis »          : colis RAMASSÉS (is_ramassee = 1) et sortis du groupe « nouveau ».
 *  · « Nouveaux colis » : colis du groupe « nouveau » PAS ENCORE ramassés.
 *
 * Chaque liste a sa propre corbeille (les colis supprimés avant ramassage relèvent des
 * « Nouveaux colis »). Filtre de LISTE uniquement : la fiche, la modification et le suivi d'un
 * colis restent accessibles quelle que soit sa liste.
 *
 * Un colis ramassé encore « nouveau », ou non ramassé mais déjà livré/en cours, n'appartient à
 * aucune des deux listes : c'est une incohérence de données, signalée dans « Reprise des données »
 * (contrôle colis_hors_listes), jamais masquée en silence.
 */
final class PerimetreListe
{
    public static function colis(Builder $q): Builder
    {
        return $q->where($q->qualifyColumn('is_ramassee'), true)
            ->where($q->qualifyColumn('state_group'), '<>', 'nouveau');
    }

    public static function nouveaux(Builder $q): Builder
    {
        return $q->where($q->qualifyColumn('state_group'), 'nouveau')
            ->where($q->qualifyColumn('is_ramassee'), false);
    }

    /** Colis qui ne figurent dans aucune des deux listes (données à corriger). */
    public static function horsListes(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w
            ->where(fn ($x) => $x->where($q->qualifyColumn('is_ramassee'), true)->where($q->qualifyColumn('state_group'), 'nouveau'))
            ->orWhere(fn ($x) => $x->where($q->qualifyColumn('is_ramassee'), false)->where($q->qualifyColumn('state_group'), '<>', 'nouveau')));
    }
}
