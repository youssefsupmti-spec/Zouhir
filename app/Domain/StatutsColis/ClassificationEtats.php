<?php

declare(strict_types=1);

namespace App\Domain\StatutsColis;

use App\Models\EtatGroupe;
use App\Models\EtatGroupeEtat;
use App\Models\Statut;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Source de vérité de la classification des états : tables etat_groupes / etat_groupe_etats.
 *
 * Copies dénormalisées tenues à jour ICI seulement (filtrage indexé, comme WinningExpress) :
 *   statuts.groupe          = code du groupe de l'état (axe flux)
 *   commands.state_group    = code du groupe du statut du colis
 */
final class ClassificationEtats
{
    /** Groupes de l'axe flux, dans l'ordre d'affichage. @return Collection<int, EtatGroupe> */
    public static function groupes(bool $actifsSeulement = false): Collection
    {
        return EtatGroupe::query()->where('axe', EtatGroupe::AXE_FLUX)
            ->when($actifsSeulement, fn ($q) => $q->where('actif', true))
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    /** Code du groupe d'un état (statut), ou null s'il n'est rattaché à aucun groupe. */
    public static function groupePour(int $statutId): ?string
    {
        return EtatGroupeEtat::query()->where('etat_groupe_etats.axe', EtatGroupe::AXE_FLUX)->where('etat_groupe_etats.etat_id', $statutId)
            ->join('etat_groupes', 'etat_groupes.id', '=', 'etat_groupe_etats.groupe_id')
            ->value('etat_groupes.code');
    }

    /**
     * Rattache des états à un groupe (un état n'appartient qu'à UN groupe par axe : il quitte
     * l'ancien). Recopie le groupe sur les statuts et sur leurs colis.
     *
     * @param  list<int>  $statutIds
     * @return int nombre de colis reclassés
     */
    public static function rattacher(EtatGroupe $groupe, array $statutIds): int
    {
        $statutIds = array_values(array_unique(array_map('intval', $statutIds)));
        if ($statutIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($groupe, $statutIds) {
            foreach ($statutIds as $id) {
                EtatGroupeEtat::query()->updateOrCreate(
                    ['axe' => $groupe->axe, 'etat_id' => $id],
                    ['groupe_id' => $groupe->id],
                );
            }

            if ($groupe->axe !== EtatGroupe::AXE_FLUX) {
                return 0;
            }

            Statut::query()->whereIn('id', $statutIds)->update(['groupe' => $groupe->code, 'updated_at' => now()]);

            return DB::table('commands')->whereIn('statut_id', $statutIds)->where('state_group', '<>', $groupe->code)
                ->update(['state_group' => $groupe->code]);
        });
    }

    /** Rattache un état à un groupe par son CODE (reprise, création de statut). */
    public static function rattacherParCode(string $code, int $statutId): void
    {
        $groupe = EtatGroupe::query()->where('axe', EtatGroupe::AXE_FLUX)->where('code', $code)->first();
        if ($groupe !== null) {
            self::rattacher($groupe, [$statutId]);
        }
    }
}
