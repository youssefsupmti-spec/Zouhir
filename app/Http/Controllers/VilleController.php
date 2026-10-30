<?php

namespace App\Http\Controllers;

use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VilleController extends Controller
{
    /**
     * Référentiel des villes et hubs logistiques avec pagination serveur.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $zoneId = $request->input('zone_id');
        $sort = $request->input('sort', 'name_fr');
        $direction = $request->input('direction', 'asc');
        $perPage = (int) $request->input('per_page', 50);

        $allowedSorts = ['id', 'name_fr', 'name_ar', 'code_zone', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name_fr';
        }
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $query = Ville::query()
            ->with(['zone:id,name,code'])
            ->when($search, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('name_fr', 'like', "%{$term}%")
                        ->orWhere('name_ar', 'like', "%{$term}%")
                        ->orWhere('code_zone', 'like', "%{$term}%");
                });
            })
            ->when($zoneId && $zoneId !== 'all', function ($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            })
            ->orderBy($sort, $direction);

        $villes = $query->paginate($perPage)->withQueryString();

        // Référentiel des 6 zones pour les filtres
        $zones = Zone::select('id', 'name', 'code')->get();

        // Statistiques territoriales MariaDB
        $totalVilles = Ville::count();
        $totalZones = Zone::count();

        return Inertia::render('Villes/Index', [
            'villes' => $villes,
            'zones' => $zones,
            'stats' => [
                'total_villes' => $totalVilles,
                'total_zones' => $totalZones,
            ],
            'filters' => [
                'search' => $search,
                'zone_id' => $zoneId ?? 'all',
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }
}
