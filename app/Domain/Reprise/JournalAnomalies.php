<?php

declare(strict_types=1);

namespace App\Domain\Reprise;

use Illuminate\Support\Facades\DB;

/**
 * Journal des écarts de la reprise (table `reprise_anomalies`).
 *
 * Rejouable : chaque anomalie a une empreinte stable (domaine, code, ligne legacy, valeur). Un
 * nouveau passage met à jour les anomalies encore présentes SANS toucher à leur statut ni à la
 * décision prise ; les anomalies ouvertes qui n'apparaissent plus (corrigées entre deux passages)
 * sont retirées à la fin de l'étape. En simulation, rien n'est écrit : seuls les compteurs servent.
 */
final class JournalAnomalies
{
    /** @var array<string, array<string, mixed>> */
    private array $tampon = [];

    /** @var array<string, array<string, int>> domaine => code => nombre */
    private array $compteurs = [];

    /** @var array<string, true> empreintes vues pendant ce passage */
    private array $vues = [];

    public function __construct(private readonly bool $simulation = false) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function signaler(
        string $domaine,
        string $code,
        string $gravite,
        string $libelle,
        ?string $legacyTable = null,
        int|string|null $legacyId = null,
        int|string|null $valeur = null,
        array $details = [],
        ?string $cibleTable = null,
        ?int $cibleId = null,
    ): void {
        $this->compteurs[$domaine][$code] = ($this->compteurs[$domaine][$code] ?? 0) + 1;

        if ($this->simulation) {
            return;
        }

        $legacyId = $legacyId === null ? null : (string) $legacyId;
        // Les clés de tableau PHP numériques (code « 73876284110426 », libellé « 3 »…) arrivent en entier.
        $valeur = $valeur === null ? null : (string) $valeur;
        $empreinte = sha1(implode('|', [$domaine, $code, $legacyTable ?? '', $legacyId ?? '', $valeur ?? '']));

        $this->vues[$empreinte] = true;
        $this->tampon[$empreinte] = [
            'domaine' => $domaine,
            'code' => $code,
            'gravite' => $gravite,
            'libelle' => mb_substr($libelle, 0, 250),
            'legacy_table' => $legacyTable,
            'legacy_id' => $legacyId,
            'cible_table' => $cibleTable,
            'cible_id' => $cibleId,
            'valeur_legacy' => $valeur,
            'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'empreinte' => $empreinte,
            'statut' => 'ouverte',
            'vue_le' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (count($this->tampon) >= 500) {
            $this->vider();
        }
    }

    public function vider(): void
    {
        if ($this->tampon === []) {
            return;
        }

        foreach (array_chunk(array_values($this->tampon), 250) as $lot) {
            DB::table('reprise_anomalies')->upsert(
                $lot,
                ['empreinte'],
                ['gravite', 'libelle', 'cible_table', 'cible_id', 'details', 'vue_le', 'updated_at'],
            );
        }

        $this->tampon = [];
    }

    /**
     * Fin d'étape : retire les anomalies ouvertes de ces domaines que ce passage n'a pas revues.
     *
     * @param  list<string>  $domaines
     * @param  list<string>|null  $codes  limiter aux codes produits par l'étape
     */
    public function cloturer(array $domaines, ?array $codes = null): void
    {
        $this->vider();

        if ($this->simulation) {
            return;
        }

        $disparues = DB::table('reprise_anomalies')
            ->whereIn('domaine', $domaines)
            ->when($codes !== null, fn ($q) => $q->whereIn('code', $codes))
            ->where('statut', 'ouverte')
            ->pluck('empreinte', 'id')
            ->reject(fn (string $empreinte) => isset($this->vues[$empreinte]))
            ->keys();

        foreach ($disparues->chunk(500) as $ids) {
            DB::table('reprise_anomalies')->whereIn('id', $ids->all())->delete();
        }
    }

    /** @return array<string, array<string, int>> */
    public function compteurs(): array
    {
        return $this->compteurs;
    }
}
