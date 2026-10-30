<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CompteBancaire;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankController extends Controller
{
    /**
     * Référentiel bancaire Bank Al-Maghrib et gestion des coordonnées de reversement CRBT.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $bankId = $request->input('bank_id');
        $tab = $request->input('tab', 'comptes'); // 'comptes' ou 'catalogue'
        $perPage = (int) $request->input('per_page', 25);

        // 1. Comptes bancaires des marchands et livreurs
        $comptesQuery = CompteBancaire::query()
            ->with([
                'bank:id,name,code_etablissement,swift_code,status',
                'owner:id,nom_complet,email,telephone,role',
            ])
            ->when($search, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('titulaire_nom', 'like', "%{$term}%")
                        ->orWhere('bank_name', 'like', "%{$term}%")
                        ->orWhereHas('owner', function ($u) use ($term) {
                            $u->where('nom_complet', 'like', "%{$term}%");
                        });
                });
            })
            ->when($bankId && $bankId !== 'all', function ($q) use ($bankId) {
                $q->where('bank_id', $bankId);
            })
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc');

        $comptes = $comptesQuery->paginate($perPage)->withQueryString();

        // Masquage sécurisé du RIB en liste (Rapport #16)
        $comptes->through(function ($c) {
            $rawRib = (string) $c->rib;
            $len = strlen($rawRib);
            if ($len >= 8) {
                $c->masked_rib = substr($rawRib, 0, 4) . ' •••• •••• ' . substr($rawRib, -4);
            } else {
                $c->masked_rib = '•••• •••• ••••';
            }
            unset($c->rib);
            return $c;
        });

        // 2. Catalogue complet des Banques BAM
        $banksList = Bank::query()
            ->withCount('comptes')
            ->orderBy('name')
            ->get();

        // Statistiques réelles MariaDB
        $totalBanks = Bank::count();
        $totalComptes = CompteBancaire::count();
        $linkedComptes = CompteBancaire::whereNotNull('bank_id')->count();

        return Inertia::render('Banks/Index', [
            'comptes' => $comptes,
            'banks' => $banksList,
            'stats' => [
                'total_banks' => $totalBanks,
                'total_comptes' => $totalComptes,
                'linked_comptes' => $linkedComptes,
                'link_rate' => $totalComptes > 0 ? round(($linkedComptes / $totalComptes) * 100, 1) : 0,
            ],
            'filters' => [
                'search' => $search,
                'bank_id' => $bankId ?? 'all',
                'tab' => $tab,
                'per_page' => $perPage,
            ],
        ]);
    }
}
