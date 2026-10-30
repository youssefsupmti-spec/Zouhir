<?php

namespace App\Http\Controllers;

use App\Domain\Auth\Enums\Permission as PermissionEnum;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Affiche le tableau de bord principal de l'espace marchand OUAISS EXPRESS.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Statistiques globales du compte client
        $stats = [
            'total_shipments' => 128,
            'in_transit' => 14,
            'delivered' => 112,
            'success_rate' => 98.2,
            'crbt_balance' => 14850.00,
            'crbt_pending' => 3200.00,
        ];

        // Dernières expéditions récentes (format prêt pour le module colis)
        $recentShipments = [
            [
                'id' => 1,
                'code' => 'OUA-782941',
                'recipient_name' => 'Amine Tazi',
                'recipient_phone' => '0661234567',
                'destination_city' => 'Casablanca',
                'crbt_amount' => 450.00,
                'status' => 'en_cours_livraison',
                'status_label' => 'En cours de livraison',
                'created_at' => '30/10/2026 09:30',
            ],
            [
                'id' => 2,
                'code' => 'OUA-782938',
                'recipient_name' => 'Khadija Berrada',
                'recipient_phone' => '0663891245',
                'destination_city' => 'Rabat',
                'crbt_amount' => 620.00,
                'status' => 'livre',
                'status_label' => 'Livré & Encaissé',
                'created_at' => '29/10/2026 14:15',
            ],
            [
                'id' => 3,
                'code' => 'OUA-782924',
                'recipient_name' => 'Mehdi El Alami',
                'recipient_phone' => '0670984512',
                'destination_city' => 'Marrakech',
                'crbt_amount' => 310.00,
                'status' => 'livre',
                'status_label' => 'Livré & Encaissé',
                'created_at' => '29/10/2026 11:00',
            ],
            [
                'id' => 4,
                'code' => 'OUA-782910',
                'recipient_name' => 'Salma Mansouri',
                'recipient_phone' => '0661123489',
                'destination_city' => 'Tanger',
                'crbt_amount' => 890.00,
                'status' => 'en_transit',
                'status_label' => 'En transit inter-agences',
                'created_at' => '28/10/2026 17:45',
            ],
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentShipments' => $recentShipments,
            'canCreateColis' => $user->can(PermissionEnum::CREATE_COLIS->value) || $user->isClient(),
            'canViewFinances' => $user->can(PermissionEnum::VIEW_FINANCES->value) || $user->isClient(),
        ]);
    }
}
