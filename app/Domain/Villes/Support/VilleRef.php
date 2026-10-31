<?php

declare(strict_types=1);

namespace App\Domain\Villes\Support;

use App\Models\Ville;

/**
 * Traduit un libellé de ville (ou un id) en `villes.id` — de façon DÉTERMINISTE.
 *
 * Adapté de WinningExpress (App\Domain\Villes\Support\VilleRef) avec trois
 * différences voulues :
 *  1. instance « scoped » (une par requête / commande) au lieu d'un état statique ;
 *  2. un dictionnaire EXPLICITE des variantes historiques connues ;
 *  3. la détection des HOMONYMES : si plusieurs villes actives partagent la même
 *     clé, la résolution est déclarée ambiguë et ne choisit JAMAIS au hasard.
 *
 * Ordre des tentatives : id → libellé exact (casse/espaces ignorés) → libellé
 * normalisé (accents, tirets, apostrophes) → alias du dictionnaire. Aucun
 * rapprochement approximatif (distance d'édition, LIKE) n'est fait.
 *
 * Le référentiel actif (≈ 1 350 villes) est chargé en UNE requête, au premier besoin.
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

    /** @var array<string, list<int>>|null clé exacte → ids */
    private ?array $exact = null;

    /** @var array<string, list<int>> clé normalisée → ids */
    private array $normalise = [];

    /** @var array<int, string> id → nom */
    private array $noms = [];

    /** @var array<int, true> */
    private array $actives = [];

    public function resoudre(int|string|null $ville): VilleResolution
    {
        if ($ville === null || $ville === '') {
            return new VilleResolution(null, VilleResolution::INTROUVABLE);
        }

        $this->charger();

        if (is_int($ville) || ctype_digit((string) $ville)) {
            $id = (int) $ville;

            return isset($this->actives[$id])
                ? new VilleResolution($id, VilleResolution::ID)
                : new VilleResolution(null, VilleResolution::INTROUVABLE);
        }

        $libelle = (string) $ville;

        $parExact = $this->exact[self::cleExacte($libelle)] ?? [];
        if ($parExact !== []) {
            return $this->depuisCandidats($parExact, VilleResolution::EXACTE);
        }

        $cle = self::cleNormalisee($libelle);
        $parNormalise = $this->normalise[$cle] ?? [];
        if ($parNormalise !== []) {
            return $this->depuisCandidats($parNormalise, VilleResolution::NORMALISEE);
        }

        $cible = self::ALIAS[$cle] ?? null;
        if ($cible !== null) {
            $parAlias = $this->normalise[self::cleNormalisee($cible)] ?? [];
            if ($parAlias !== []) {
                return $this->depuisCandidats($parAlias, VilleResolution::ALIAS);
            }
        }

        return new VilleResolution(null, VilleResolution::INTROUVABLE);
    }

    /** `villes.id` d'un libellé ou d'un id — `null` si introuvable OU ambigu. */
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
        $this->exact = null;
        $this->normalise = [];
        $this->noms = [];
        $this->actives = [];
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

    /** @param  list<int>  $ids */
    private function depuisCandidats(array $ids, string $methode): VilleResolution
    {
        $ids = array_values(array_unique($ids));

        return count($ids) === 1
            ? new VilleResolution($ids[0], $methode)
            : new VilleResolution(null, VilleResolution::AMBIGUE, $ids);
    }

    private function charger(): void
    {
        if ($this->exact !== null) {
            return;
        }

        $this->exact = [];

        foreach (Ville::query()->orderBy('id')->get(['id', 'name_fr']) as $ville) {
            $id = (int) $ville->id;
            $nom = (string) $ville->name_fr;

            $this->actives[$id] = true;
            $this->noms[$id] = $nom;
            $this->exact[self::cleExacte($nom)][] = $id;
            $this->normalise[self::cleNormalisee($nom)][] = $id;
        }
    }
}
