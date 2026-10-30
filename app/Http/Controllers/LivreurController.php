<?php

namespace App\Http\Controllers;

use App\Models\Command;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LivreurController extends Controller
{
    /**
     * Liste paginée des livreurs (coursiers DLM) avec métriques de tournées réelles.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $perPage = (int) $request->input('per_page', 25);

        $sortMap = [
            'ville' => 'ville_name',
            'status' => 'active',
        ];
        $sortColumn = $sortMap[$sort] ?? $sort;

        $allowedSorts = ['id', 'nom_complet', 'telephone', 'ville_name', 'active', 'created_at'];
        if (! in_array($sortColumn, $allowedSorts, true)) {
            $sortColumn = 'created_at';
        }
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $query = User::query()
            ->where('role', 'livreur')
            ->withCount([
                'driverCommands as total_colis_count',
                'driverCommands as colis_livres_count' => function ($q) {
                    $q->where('state_group', 'livre');
                },
                'driverCommands as colis_en_cours_count' => function ($q) {
                    $q->where('state_group', 'en_cours');
                },
            ])
            ->when($search, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('nom_complet', 'like', "%{$term}%")
                        ->orWhere('telephone', 'like', "%{$term}%")
                        ->orWhere('ville_name', 'like', "%{$term}%");
                });
            })
            ->when($status && $status !== 'all', function ($q) use ($status) {
                $isActive = in_array($status, ['active', '1', true], true);
                $q->where('active', $isActive);
            })
            ->orderBy($sortColumn, $direction);

        $livreurs = $query->paginate($perPage)->withQueryString();

        // Calcul du taux de succès et de la progression de tournée
        $livreurs->through(function ($livreur) {
            $total = (int) $livreur->total_colis_count;
            $livres = (int) $livreur->colis_livres_count;
            $livreur->taux_succes = $total > 0 ? round(($livres / $total) * 100, 1) : 0;
            return $livreur;
        });

        // Métriques de pilotage opérationnel MariaDB
        $totalLivreurs = User::where('role', 'livreur')->count();
        $activeLivreurs = User::where('role', 'livreur')->where('active', true)->count();
        $colisInDistribution = Command::where('state_group', 'en_cours')->count();
        $colisDeliveredTotal = Command::where('state_group', 'livre')->count();

        return Inertia::render('Livreurs/Index', [
            'livreurs' => $livreurs,
            'stats' => [
                'total' => $totalLivreurs,
                'active' => $activeLivreurs,
                'colis_en_cours' => $colisInDistribution,
                'colis_livres_total' => $colisDeliveredTotal,
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
