<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    /**
     * Liste paginée des enseignes commerciales avec recherche et filtres côté serveur.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $perPage = (int) $request->input('per_page', 25);

        // Validation des colonnes autorisées pour le tri
        $allowedSorts = ['id', 'name', 'status', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $query = Business::query()
            ->with(['user:id,nom_complet,email,telephone'])
            ->withCount('commands')
            ->when($search, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhereHas('user', function ($u) use ($term) {
                            $u->where('nom_complet', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                });
            })
            ->when($status && $status !== 'all', function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->orderBy($sort, $direction);

        $businesses = $query->paginate($perPage)->withQueryString();

        // Statistiques réelles calculées côté serveur (zéro valeur en dur)
        $totalBusinesses = Business::count();
        $activeBusinesses = Business::where('status', 'active')->count();
        $clientsWithBusiness = Business::distinct('user_id')->count('user_id');
        $totalClients = User::where('role', 'client')->count();

        return Inertia::render('Businesses/Index', [
            'businesses' => $businesses,
            'stats' => [
                'total' => $totalBusinesses,
                'active' => $activeBusinesses,
                'clients_with_business' => $clientsWithBusiness,
                'total_clients' => $totalClients,
                'coverage_rate' => $totalClients > 0 ? round(($clientsWithBusiness / $totalClients) * 100, 1) : 0,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status ?? 'all',
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }
}
