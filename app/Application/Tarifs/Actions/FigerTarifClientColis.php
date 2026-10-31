<?php

declare(strict_types=1);

namespace App\Application\Tarifs\Actions;

use App\Domain\Tarifs\Commun\Enums\TypeTarif;
use App\Domain\Tarifs\Commun\Services\FeeResolutionService;
use App\Domain\Tarifs\Commun\Support\TarifResolu;
use App\Models\Command;
use Illuminate\Validation\ValidationException;

/**
 * SNAPSHOT tarifaire d'un colis : `frais_livraison_client` + `type_tarif_client`.
 *
 * Point métier UNIQUE appelé par les workflows d'écriture de colis (création et
 * modification dans ColisController). Choix d'une Action explicite plutôt que
 * d'un Observer (CommandObserver de WinningExpress) : Zouhir écrit ses colis
 * depuis un seul contrôleur et les backfills (`upsert`, sans événements) ne
 * doivent JAMAIS déclencher de recalcul.
 *
 * Écarts volontaires avec WinningExpress :
 *  - WinningExpress résolvait quand `frais_livraison_client <= 0` : un tarif à 0.00
 *    était donc pris pour une absence. Ici la décision repose sur la PROVENANCE
 *    (`type_tarif_client`) et sur les champs modifiés, jamais sur le montant.
 *  - Un montant manuel n'est accepté que de l'administration (paramètre explicite).
 *  - Un colis finalisé (livré, retour, annulé) n'est jamais recalculé.
 */
final class FigerTarifClientColis
{
    /** Groupes d'états où le tarif peut encore être réaligné. */
    public const ETATS_MODIFIABLES = ['nouveau', 'en_cours'];

    public function __construct(private readonly FeeResolutionService $fees) {}

    /**
     * À la création (avant save). `$montantManuel` non null = saisie manuelle autorisée.
     */
    public function aLaCreation(Command $colis, ?string $montantManuel = null): TarifResolu
    {
        if ($montantManuel !== null) {
            return $this->poserManuel($colis, $montantManuel);
        }

        return $this->poserResolu($colis);
    }

    /**
     * À la modification (avant save, attributs déjà remplis).
     *
     * `$montantManuel` : montant saisi par l'administration, ou null si aucun droit / aucune saisie.
     * Retourne null si le snapshot est laissé intact.
     */
    public function aLaModification(Command $colis, ?string $montantManuel = null): ?TarifResolu
    {
        $finalise = ! in_array((string) $colis->getOriginal('state_group'), self::ETATS_MODIFIABLES, true);
        $actuel = TarifResolu::montant($colis->getOriginal('frais_livraison_client') ?? 0);
        $saisieDifferente = $montantManuel !== null
            && TarifResolu::montant($montantManuel) !== $actuel;

        if ($finalise) {
            if ($saisieDifferente) {
                throw ValidationException::withMessages([
                    'frais_livraison_client' => 'Colis finalisé : ses frais ne peuvent plus être modifiés sans procédure de correction.',
                ]);
            }

            return null;
        }

        if ($saisieDifferente) {
            return $this->poserManuel($colis, $montantManuel);
        }

        $changementTarifaire = $colis->isDirty(['client_id', 'destinataire_ville_id']);
        $manuel = TypeTarif::depuis($colis->getOriginal('type_tarif_client'))?->estManuel() ?? false;

        if (! $changementTarifaire || $manuel) {
            return null;
        }

        return $this->poserResolu($colis);
    }

    private function poserManuel(Command $colis, string $montant): TarifResolu
    {
        $colis->frais_livraison_client = TarifResolu::montant($montant);
        $colis->type_tarif_client = TypeTarif::Manuel->value;

        return new TarifResolu(TypeTarif::Manuel, TarifResolu::montant($montant), null, null, null, (int) $colis->client_id, (int) $colis->destinataire_ville_id);
    }

    private function poserResolu(Command $colis): TarifResolu
    {
        $tarif = $this->fees->clientFees(
            ((int) $colis->client_id) ?: null,
            ((int) $colis->destinataire_ville_id) ?: ($colis->destinataire_ville ?: null),
        );

        // Missing : la colonne est NOT NULL, le montant reste 0.00 MAIS la provenance
        // `missing` dit explicitement qu'aucun tarif n'a été trouvé (ce n'est pas une gratuité).
        $colis->frais_livraison_client = $tarif->livraison ?? '0.00';
        $colis->type_tarif_client = $tarif->type->value;

        return $tarif;
    }
}
