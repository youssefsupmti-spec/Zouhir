<?php

declare(strict_types=1);

namespace App\Domain\Zones\Support;

use Illuminate\Support\Str;

/**
 * Référentiel cible des zones OUAISS EXPRESS (rapports #31 et #39, structure WinningExpress).
 *
 *  - Pôle LOCAL (Agence Témara, flotte propre, BL) : un secteur = une zone (rapport #31) ;
 *    Salé regroupe ses communes riveraines (Sala Al Jadida, Bouknadel).
 *  - Pôle NATIONAL (partenaire, BE) : hubs régionaux.
 *
 * Le rattachement d'une ville est déduit UNIQUEMENT des données Zouhir (nom de la ville,
 * suffixe régional du libellé legacy « Ville-region », ancienne macro-zone). Aucune donnée
 * WinningExpress n'est lue. Une ville qu'aucune règle ne classe reste « sans zone » :
 * elle se classe ensuite depuis la page Zones.
 */
final class ReferentielZones
{
    /**
     * @var array<string, array{name: string, type: string, description: string, villes: list<string>}>
     */
    public const ZONES = [
        // ── Pôle local direct — Agence Témara (BL) ─────────────────────────────
        'TM' => ['name' => 'Zone Témara', 'type' => 'locale', 'description' => 'Témara centre — flotte propre (BL)',
            'villes' => ['temara']],
        'TMS' => ['name' => 'Zone Tamesna', 'type' => 'locale', 'description' => 'Tamesna ville nouvelle — flotte propre (BL)',
            'villes' => ['tamesna', 'tamessna']],
        'HAR' => ['name' => 'Zone Harhoura', 'type' => 'locale', 'description' => 'Bande côtière Harhoura — flotte propre (BL)',
            'villes' => ['harhoura']],
        'SKH' => ['name' => 'Zone Skhirate', 'type' => 'locale', 'description' => 'Skhirate centre et littoral — flotte propre (BL)',
            'villes' => ['skhirate', 'skhirat']],
        'AAT' => ['name' => 'Zone Ain Attig', 'type' => 'locale', 'description' => 'Ain Attig et zone industrielle — flotte propre (BL)',
            'villes' => ['ain attig', 'ain atig', 'ain atiq']],
        'AOU' => ['name' => 'Zone Ain Aouda', 'type' => 'locale', 'description' => 'Ain Aouda et Zaër Est — flotte propre (BL)',
            'villes' => ['ain el aouda', 'ain aouda']],
        'MEK' => ['name' => 'Zone Mers El Kheir', 'type' => 'locale', 'description' => 'Mers El Kheir — flotte propre (BL)',
            'villes' => ['mers el kheir', 'merss el khir', 'mrss lkhir']],
        'SYZ' => ['name' => 'Zone Sidi Yahya Zaër', 'type' => 'locale', 'description' => 'Sidi Yahya Zaër et Zaër Sud — flotte propre (BL)',
            'villes' => ['sidi yahia zaers', 'sidi yahya zaer', 'sidi yahya zaers', 'sidi yahia zaer']],
        'RB' => ['name' => 'Zone Rabat', 'type' => 'locale', 'description' => 'Rabat intra-muros — flotte propre (BL)',
            'villes' => ['rabat']],
        'SALE' => ['name' => 'Zone Salé', 'type' => 'locale', 'description' => 'Salé, Sala Al Jadida, Bouknadel — flotte propre (BL)',
            'villes' => ['sale', 'sala jadida', 'sala al jadida', 'bouknadel', 'sidi bouknadel']],

        // ── Pôle national partenaire (BE) ──────────────────────────────────────
        'CASA' => ['name' => 'Zone Grand Casablanca', 'type' => 'nationale', 'description' => 'Casablanca, Mohammedia, Bouskoura, Bouznika — BE',
            'villes' => ['casablanca', 'mohammedia', 'mouhammedia', 'mouhammadia', 'bouskoura', 'bouznika', 'dar bouazza', 'nouaceur',
                'nouaccer', 'benslimane', 'ben slimane', 'had soualem', 'tit mellil', 'ain harrouda', 'ain harouda', 'lahraouyine', 'mediouna',
                // Variantes d'orthographe des libellés legacy Zouhir (portent des tarifs).
                'el mansouria', 'hed soualem', 'tit melil sbit']],
        'KECH' => ['name' => 'Zone Marrakech', 'type' => 'nationale', 'description' => 'Marrakech, Al Haouz, El Kelaa, Essaouira — BE',
            'villes' => ['marrakech', 'marrakeche', 'ben guerir', 'benguerir', 'chichaoua', 'el kelaa des sraghna', 'kelaa des sraghna',
                'essaouira', 'ait ourir', 'tahanaout', 'amizmiz', 'ourika',
                // Variantes d'orthographe des libellés legacy Zouhir (portent des tarifs).
                'amezmiz', 'el kelaa sraghna', 'imentanout', 'skhour rhamna']],
        'JD' => ['name' => 'Zone El Jadida & Safi', 'type' => 'nationale', 'description' => 'El Jadida, Safi, Settat, Berrechid — BE',
            'villes' => ['el jadida', 'safi', 'asfi', 'settat', 'berrechid', 'ben ahmed', 'azemmour', 'sidi bennour', 'youssoufia', 'oualidia',
                // Variantes d'orthographe des libellés legacy Zouhir (portent des tarifs).
                'benhmed', 'chmaaiya', 'daroua', 'khemiss zmamra', 'sidi benour', 'sidi rahal', 'bir el jadid', 'berrchid', 'gara', 'hed oulad frej', 'tnin chtouka']],
        'FES' => ['name' => 'Zone Fès-Meknès', 'type' => 'nationale', 'description' => 'Fès, Meknès, Sefrou, Ifrane, Taza — BE',
            'villes' => ['fes', 'meknes', 'sefrou', 'azrou', 'ifrane', 'el hajeb', 'taza', 'taounat', 'boulemane', 'missour', 'moulay yacoub',
                // Variantes d'orthographe des libellés legacy Zouhir (portent des tarifs).
                'ain cheggag', 'ain chkef', 'boufkrane', 'boulmane', 'ghafsai', 'ifran', 'mkansa', 'tahla']],
        'TG' => ['name' => 'Zone Tanger-Tétouan', 'type' => 'nationale', 'description' => 'Tanger, Tétouan, Larache, Chefchaouen — BE',
            'villes' => ['tanger', 'tetouan', 'chefchaouen', 'larache', 'asilah', 'ksar el kebir', 'mdiq', 'm diq', 'fnideq', 'martil', 'ouazzane',
                // Variantes d'orthographe des libellés legacy Zouhir (portent des tarifs).
                'bab berred', 'fnidaq', 'madiq', 'ouadlaou', 'ouezzane', 'ksar lkbir']],
        'AG' => ['name' => 'Zone Agadir & Sud', 'type' => 'nationale', 'description' => 'Agadir, Souss, Ouarzazate et provinces du Sud — BE',
            'villes' => ['agadir', 'inezgane', 'ait melloul', 'taroudant', 'tiznit', 'guelmim', 'tan tan', 'laayoune', 'dakhla', 'smara',
                'boujdour', 'sidi ifni', 'ouarzazate', 'zagora', 'tata', 'biougra',
                // Variantes d'orthographe des libellés legacy Zouhir (portent des tarifs).
                'agdz', 'daoura', 'inzgane', 'ouarzazat', 'oulad taima', 'tarfaya']],
        'OR' => ['name' => 'Zone Oriental', 'type' => 'nationale', 'description' => 'Oujda, Nador, Berkane, Al Hoceima — BE',
            'villes' => ['oujda', 'nador', 'berkane', 'al hoceima', 'taourirt', 'jerada', 'driouch', 'saidia', 'ahfir', 'figuig', 'guercif', 'zaio',
                // Variantes d'orthographe des libellés legacy Zouhir (portent des tarifs).
                'berkan', 'beni enser', 'bouarfa', 'issegane', 'midar', 'selouane', 'hociema']],
        'BM' => ['name' => 'Zone Béni Mellal-Khénifra', 'type' => 'nationale', 'description' => 'Béni Mellal, Khouribga, Khénifra, Errachidia — BE',
            'villes' => ['beni mellal', 'khouribga', 'khoribga', 'fquih ben salah', 'fkih ben salah', 'khenifra', 'azilal', 'kasba tadla',
                'kesba tadla', 'oued zem', 'errachidia', 'midelt', 'tinghir', 'goulmima', 'arfoud', 'rissani',
                // Variantes d'orthographe des libellés legacy Zouhir (portent des tarifs).
                'bejaad', 'kelaat magouna', 'tinjdad', 'souk sebt oulad nemma']],
        'KENI' => ['name' => 'Zone Kénitra-Gharb', 'type' => 'nationale', 'description' => 'Kénitra, Gharb, Khémisset, Tiflet — BE',
            'villes' => ['kenitra', 'sidi slimane', 'sidi kacem', 'souk el arbaa', 'souk larbaa', 'mechra bel ksiri', 'sidi yahya el gharb',
                'sidi yahya lgharb', 'khemisset', 'tiflet', 'tifelt', 'sidi allal el bahraoui', 'mehdia', 'sidi taibi', 'moulay bousselham',
                'moulay bousalham',
                // Variantes d'orthographe des libellés legacy Zouhir (portent des tarifs).
                'aarbaoua', 'jerf el malha', 'khemissat', 'khnichat', 'machroua belkssiri', 'sidi allal bahraoui', 'sidi taybi']],
    ];

    /** Suffixe régional des libellés legacy « Ville-region » (ex. « Ait melloul-agadir »). */
    public const SUFFIXES = [
        'agadir' => 'AG', 'ag' => 'AG', 'sahara' => 'AG',
        'marrakeche' => 'KECH', 'marrakech' => 'KECH', 'kech' => 'KECH', 'el kelaa sraghna' => 'KECH',
        'errachidia' => 'BM', 'err' => 'BM', 'khenifra' => 'BM', 'beni mellal' => 'BM', 'bn' => 'BM',
        'meknes' => 'FES', 'fes' => 'FES',
        'gharb' => 'KENI', 'kacem' => 'KENI', 'kenitra' => 'KENI',
        'nd' => 'OR', 'or' => 'OR', 'nador' => 'OR',
        'casa' => 'CASA', 'media' => 'CASA',
        'tanger' => 'TG', 'tg' => 'TG',
        'jd' => 'JD',
    ];

    /** Anciennes macro-zones (legacy, avant découpage) et leur zone de repli. CENTRE = fourre-tout, sans repli. */
    public const ANCIENNES = ['RBT' => null, 'CENTRE' => null, 'CAS' => 'CASA', 'NORD' => 'TG', 'ORIENTAL' => 'OR', 'SUD' => 'AG'];

    public static function normaliser(?string $nom): string
    {
        $n = Str::lower(Str::ascii((string) $nom));
        $n = (string) preg_replace("/[-_\/'.()]+/", ' ', $n);

        return trim((string) preg_replace('/\s+/', ' ', $n));
    }

    /**
     * Zone cible d'une ville, et la règle qui l'a décidée.
     *
     * @return array{code: ?string, regle: string}
     */
    public static function zonePour(string $nom, ?string $ancienneZone): array
    {
        $n = self::normaliser($nom);
        $compact = str_replace(' ', '', $n);

        foreach (self::ZONES as $code => $z) {
            foreach ($z['villes'] as $v) {
                if ($n === $v || $compact === str_replace(' ', '', $v)) {
                    return ['code' => $code, 'regle' => 'nom'];
                }
            }
        }

        // « Base-region » : la base seule, puis le suffixe régional.
        if (str_contains((string) $nom, '-')) {
            $parts = explode('-', (string) $nom);
            $suffixe = self::normaliser((string) array_pop($parts));
            $base = self::zonePour(implode('-', $parts), null);
            if ($base['code'] !== null) {
                return $base;
            }
            if (isset(self::SUFFIXES[$suffixe])) {
                return ['code' => self::SUFFIXES[$suffixe], 'regle' => 'suffixe'];
            }
        }

        if ($ancienneZone !== null && (self::ANCIENNES[$ancienneZone] ?? null) !== null) {
            return ['code' => self::ANCIENNES[$ancienneZone], 'regle' => 'ancienne_zone'];
        }

        return ['code' => null, 'regle' => 'sans_zone'];
    }
}
