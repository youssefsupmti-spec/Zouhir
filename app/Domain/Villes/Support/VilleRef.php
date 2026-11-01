<?php

declare(strict_types=1);

namespace App\Domain\Villes\Support;

use App\Models\Ville;

/**
 * Traduit un libellé de ville (ou un id) en `villes.id` — POLITIQUE UNIQUE du projet.
 *
 * Règle de WinningExpress (BackfillTarifCles::rattacherVille, VilleRef::chercher),
 * validée par le propriétaire (rapport #35, décision 1) :
 *   ① ville ACTIVE d'abord ;
 *   ② parmi plusieurs correspondances actives (homonymes), le PLUS PETIT id ;
 *   ③ ville archivée : acceptée UNIQUEMENT en mode historique (`$historique = true`, backfills)
 *      et seulement si aucune ville active ne correspond — même départage par le plus petit id.
 *
 * Ordre des tentatives de libellé : exact (casse/espaces ignorés) → normalisé (accents, tirets,
 * apostrophes) → dictionnaire explicite des variantes historiques. Aucun rapprochement approximatif.
 *
 * Utilisée par : FeeResolutionService (libellés), RapprochementClientFees (tarifs legacy) et
 * app:backfill-commands-from-stockout (colis legacy) — les trois rattachent donc un même libellé
 * au MÊME identifiant.
 *
 * Le référentiel (≈ 1 350 villes, corbeille comprise) est chargé en UNE requête, au premier besoin.
 */
final class VilleRef
{
    /**
     * Variantes historiques constatées dans le legacy (rapport #27 §4.3) → libellé cible.
     * La cible est résolue par son NOM dans le référentiel courant, jamais par un id figé.
     *
     * @var array<string, string>
     */
    public const ALIAS = [
        'tamessna' => 'Tamesna',
        'ain atig' => 'Ain attig',
        'ain aouda' => 'Ain el aouda',
        'sekhirat' => 'Skhirate',
        'sidi yahya zair' => 'Sidi yahia zaers',
        'mers lkhir' => 'Mers el kheir',
    ];

    private bool $charge = false;

    /** @var array<string, list<int>> clé exacte → ids actifs */
    private array $exact = [];

    /** @var array<string, list<int>> clé normalisée → ids actifs */
    private array $normalise = [];

    /** @var array<string, list<int>> clé exacte → ids archivés */
    private array $exactArchivees = [];

    /** @var array<string, list<int>> clé normalisée → ids archivés */
    private array $normaliseArchivees = [];

    /** @var array<int, string> id → nom (corbeille comprise) */
    private array $noms = [];

    /** @var array<int, bool> id → archivée ? */
    private array $archivee = [];

    /**
     * @param  bool  $historique  autoriser une ville archivée si aucune ville active ne correspond
     *                            (reprises de données historiques uniquement).
     */
    public function resoudre(int|string|null $ville, bool $historique = false): VilleResolution
    {
        if ($ville === null || $ville === '') {
            return new VilleResolution(null, VilleResolution::INTROUVABLE);
        }

        $this->charger();

        if (is_int($ville) || ctype_digit((string) $ville)) {
            $id = (int) $ville;
            $existe = array_key_exists($id, $this->archivee);

            if ($existe && (! $this->archivee[$id] || $historique)) {
                return new VilleResolution($id, $this->archivee[$id] ? VilleResolution::ARCHIVEE : VilleResolution::ID);
            }

            return new VilleResolution(null, VilleResolution::INTROUVABLE);
        }

        $libelle = (string) $ville;
        $cleExacte = self::cleExacte($libelle);
        $cleNormalisee = self::cleNormalisee($libelle);
        $alias = self::ALIAS[$cleNormalisee] ?? null;
        $cleAlias = $alias === null ? null : self::cleNormalisee($alias);

        // ① Villes actives : exact → normalisé → alias.
        foreach ([
            [$this->exact[$cleExacte] ?? [], VilleResolution::EXACTE],
            [$this->normalise[$cleNormalisee] ?? [], VilleResolution::NORMALISEE],
            [$cleAlias === null ? [] : ($this->normalise[$cleAlias] ?? []), VilleResolution::ALIAS],
        ] as [$ids, $methode]) {
            if ($ids !== []) {
                return $this->retenir($ids, $methode);
            }
        }

        // ③ Villes archivées : historique seulement, même ordre de tentatives.
        if ($historique) {
            foreach ([
                $this->exactArchivees[$cleExacte] ?? [],
                $this->normaliseArchivees[$cleNormalisee] ?? [],
                $cleAlias === null ? [] : ($this->normaliseArchivees[$cleAlias] ?? []),
            ] as $ids) {
                if ($ids !== []) {
                    return $this->retenir($ids, VilleResolution::ARCHIVEE);
                }
            }
        }

        return new VilleResolution(null, VilleResolution::INTROUVABLE);
    }

    /** `villes.id` d'un libellé ou d'un id (villes actives) — `null` si introuvable. */
    public function id(int|string|null $ville): ?int
    {
        return $this->resoudre($ville)->villeId;
    }

    public function nom(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $this->charger();

        return $this->noms[$id] ?? null;
    }

    /** Vide le référentiel mémorisé (après une écriture sur `villes`, ou entre deux tests). */
    public function oublier(): void
    {
        $this->charge = false;
        $this->exact = [];
        $this->normalise = [];
        $this->exactArchivees = [];
        $this->normaliseArchivees = [];
        $this->noms = [];
        $this->archivee = [];
    }

    /** Casse et espaces ignorés, rien d'autre. */
    public static function cleExacte(string $libelle): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($libelle)) ?? '');
    }

    /** Accents retirés, tirets / apostrophes / points traités comme des espaces. */
    public static function cleNormalisee(string $libelle): string
    {
        $s = mb_strtolower(trim($libelle));
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
        $s = preg_replace("/[-_'’.,\/]+/u", ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /**
     * ② Départage déterministe : le plus petit id. Les homonymes sont signalés
     * (méthode `homonymes`, candidats conservés) mais jamais laissés sans réponse.
     *
     * @param  list<int>  $ids
     */
    private function retenir(array $ids, string $methode): VilleResolution
    {
        $ids = array_values(array_unique($ids));
        sort($ids);

        if (count($ids) > 1) {
            return new VilleResolution(
                $ids[0],
                $methode === VilleResolution::ARCHIVEE ? VilleResolution::ARCHIVEE : VilleResolution::HOMONYMES,
                $ids,
            );
        }

        return new VilleResolution($ids[0], $methode);
    }

    private function charger(): void
    {
        if ($this->charge) {
            return;
        }

        $this->charge = true;

        foreach (Ville::withTrashed()->orderBy('id')->get(['id', 'name_fr', 'deleted_at']) as $ville) {
            $id = (int) $ville->id;
            $nom = (string) $ville->name_fr;
            $archivee = $ville->deleted_at !== null;

            $this->noms[$id] = $nom;
            $this->archivee[$id] = $archivee;

            if ($archivee) {
                $this->exactArchivees[self::cleExacte($nom)][] = $id;
                $this->normaliseArchivees[self::cleNormalisee($nom)][] = $id;
            } else {
                $this->exact[self::cleExacte($nom)][] = $id;
                $this->normalise[self::cleNormalisee($nom)][] = $id;
            }
        }
    }
}
