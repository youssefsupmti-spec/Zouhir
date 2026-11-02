<?php

declare(strict_types=1);

namespace App\Domain\Zones\Services;

use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lectures de l'écran « Zones » (structure WinningExpress : liste, aperçu des villes,
 * page d'édition à deux panneaux). Nombre de requêtes constant quel que soit le volume.
 */
final class ZoneQuery
{
    public const ONGLETS = ['toutes', 'locale', 'nationale', 'vides', 'corbeille'];

    private const APERCU = 5;

    /** @return array{zones: int, locales: int, nationales: int, vides: int, corbeille: int, villes: int, villes_couvertes: int, sans_zone: int} */
    public function statistiques(): array
    {
        $z = DB::table('zones')
            ->selectRaw('SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as zones')
            ->selectRaw("SUM(CASE WHEN deleted_at IS NULL AND type = 'locale' THEN 1 ELSE 0 END) as locales")
            ->selectRaw("SUM(CASE WHEN deleted_at IS NULL AND type = 'nationale' THEN 1 ELSE 0 END) as nationales")
            ->selectRaw('SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as corbeille')
            ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM villes v WHERE v.zone_id = zones.id AND v.deleted_at IS NULL) THEN 1 ELSE 0 END) as vides')
            ->first();

        $v = DB::table('villes')->whereNull('villes.deleted_at')
            ->leftJoin('zones', fn ($j) => $j->on('zones.id', '=', 'villes.zone_id')->whereNull('zones.deleted_at'))
            ->selectRaw('COUNT(*) as villes')
            ->selectRaw('SUM(CASE WHEN zones.id IS NULL THEN 1 ELSE 0 END) as sans_zone')
            ->first();

        return [
            'zones' => (int) ($z->zones ?? 0),
            'locales' => (int) ($z->locales ?? 0),
            'nationales' => (int) ($z->nationales ?? 0),
            'vides' => (int) ($z->vides ?? 0),
            'corbeille' => (int) ($z->corbeille ?? 0),
            'villes' => (int) ($v->villes ?? 0),
            'villes_couvertes' => (int) ($v->villes ?? 0) - (int) ($v->sans_zone ?? 0),
            'sans_zone' => (int) ($v->sans_zone ?? 0),
        ];
    }

    /**
     * Zones paginées avec leur nombre de villes actives et un aperçu des premières (2 requêtes + count).
     *
     * @param  array{onglet: string, search: string}  $filtres
     */
    public function zones(array $filtres, int $parPage): LengthAwarePaginator
    {
        $onglet = $filtres['onglet'];
        $search = $filtres['search'];

        $page = Zone::query()
            ->when($onglet === 'corbeille', fn ($q) => $q->onlyTrashed())
            ->withCount(['villes' => fn ($q) => $q->whereNull('villes.deleted_at')])
            ->when(in_array($onglet, ['locale', 'nationale'], true), fn ($q) => $q->where('type', $onglet))
            ->when($onglet === 'vides', fn ($q) => $q->whereDoesntHave('villes'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhereHas('villes', fn ($v) => $v->where('name_fr', 'like', "%{$search}%")->orWhere('name_ar', 'like', "%{$search}%"))))
            ->orderByRaw("CASE WHEN type = 'locale' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate($parPage)
            ->withQueryString();

        $ids = $page->getCollection()->pluck('id')->all();
        $apercus = $ids === [] ? collect() : Ville::query()
            ->whereIn('zone_id', $ids)
            ->orderBy('name_fr')
            ->get(['id', 'zone_id', 'name_fr', 'name_ar'])
            ->groupBy('zone_id');

        $page->setCollection($page->getCollection()->map(fn (Zone $z) => [
            'id' => (int) $z->id,
            'name' => (string) $z->name,
            'code' => (string) $z->code,
            'type' => $z->type->value,
            'description' => $z->description,
            'villes_count' => (int) $z->villes_count,
            'apercu' => ($apercus->get($z->id) ?? collect())->take(self::APERCU)
                ->map(fn ($v) => ['id' => (int) $v->id, 'nom' => (string) $v->name_fr, 'nom_ar' => $v->name_ar])->values()->all(),
            'deleted_at' => $z->deleted_at?->toIso8601String(),
        ]));

        return $page;
    }

    /**
     * Villes d'un panneau de la page d'édition, paginées côté serveur.
     *
     * panneau `zone` : villes de la zone ; `disponibles` : toutes les autres villes actives
     * (filtre `sans_zone` pour ne voir que celles à classer), avec leur zone actuelle.
     *
     * @param  array{panneau: string, search: string, sans_zone: bool}  $filtres
     */
    public function villes(Zone $zone, array $filtres, int $parPage): LengthAwarePaginator
    {
        $search = $filtres['search'];

        return Ville::query()
            ->leftJoin('zones', 'zones.id', '=', 'villes.zone_id')
            ->when($filtres['panneau'] === 'zone',
                fn ($q) => $q->where('villes.zone_id', $zone->id),
                fn ($q) => $q->where(fn ($w) => $w->whereNull('villes.zone_id')->orWhere('villes.zone_id', '!=', $zone->id))
                    ->when($filtres['sans_zone'], fn ($w) => $w->where(fn ($s) => $s->whereNull('villes.zone_id')->orWhereNotNull('zones.deleted_at'))))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('villes.name_fr', 'like', "%{$search}%")
                ->orWhere('villes.name_ar', 'like', "%{$search}%")
                ->orWhere('zones.code', 'like', "%{$search}%")))
            ->orderBy('villes.name_fr')
            ->select(['villes.id', 'villes.name_fr', 'villes.name_ar', 'zones.code as zone_code', 'zones.name as zone_nom', 'zones.deleted_at as zone_archivee'])
            ->paginate($parPage)
            ->through(fn ($v) => [
                'id' => (int) $v->id,
                'nom' => (string) $v->name_fr,
                'nom_ar' => $v->name_ar,
                'zone_code' => $v->zone_archivee === null ? $v->zone_code : null,
                'zone_nom' => $v->zone_archivee === null ? $v->zone_nom : null,
            ]);
    }
}
