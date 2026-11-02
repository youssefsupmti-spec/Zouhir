<?php

namespace App\Http\Controllers;

use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

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

        // Zones actives pour les filtres et le formulaire
        $zones = Zone::query()->orderBy('name')->get(['id', 'name', 'code']);

        // Statistiques territoriales MariaDB
        $totalVilles = Ville::count();
        $totalZones = Zone::count();

        return Inertia::render('Villes/Index', [
            'villes' => $villes,
            'canManage' => $request->user()->can('create', Ville::class),
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

    /**
     * Enregistrer une nouvelle ville dans le référentiel.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Ville::class);

        $validated = $request->validate([
            'name_fr' => ['required', 'string', 'max:100', 'unique:villes,name_fr'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'zone_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('zones', 'id')->whereNull('deleted_at')],
            'code_zone' => ['nullable', 'string', 'max:20'],
        ]);

        // code_zone est dérivé de zone_id par le modèle Ville (jamais saisi).
        unset($validated['code_zone']);

        Ville::create($validated);

        return back()->with('success', 'Ville ajoutée au référentiel.');
    }

    /**
     * Mettre à jour une ville.
     */
    public function update(Request $request, Ville $ville)
    {
        Gate::authorize('update', $ville);

        $validated = $request->validate([
            'name_fr' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('villes')->ignore($ville->id)],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'zone_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('zones', 'id')->whereNull('deleted_at')],
            'code_zone' => ['nullable', 'string', 'max:20'],
        ]);

        // code_zone est dérivé de zone_id par le modèle Ville (jamais saisi).
        unset($validated['code_zone']);

        $ville->fill($validated);
        if ($ville->isDirty('zone_id')) {
            $ville->code_zone = null; // recalculé par Ville::saving
        }
        $ville->save();

        return back()->with('success', 'Ville mise à jour avec succès.');
    }

    /**
     * Supprimer une ville.
     */
    public function destroy(Ville $ville)
    {
        Gate::authorize('delete', $ville);

        $ville->delete();

        return back()->with('success', 'Ville retirée du référentiel.');
    }
}

