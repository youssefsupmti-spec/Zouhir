<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    /**
     * Villes principales desservies par le réseau Ouais Express
     */
    protected array $cities = [
        'Casablanca',
        'Rabat',
        'Marrakech',
        'Tanger',
        'Fès',
        'Agadir',
        'Meknès',
        'Oujda',
        'Kénitra',
        'Tétouan',
        'Safi',
        'El Jadida',
        'Nador',
        'Béni Mellal',
        'Mohammédia',
        'Taza',
        'Khouribga',
        'Settat',
        'Laâyoune',
        'Dakhla',
    ];

    /**
     * Affiche la landing page premium internationale de Ouais Express.
     */
    public function index(): Response
    {
        return Inertia::render('Landing', [
            'cities' => $this->cities,
            'statistics' => [
                ['value' => '99.2%', 'label' => 'Taux de livraison réussi', 'description' => 'Ponctualité et respect des délais'],
                ['value' => '48h', 'label' => 'Délai virement CRBT', 'description' => 'Reversement des fonds garanti'],
                ['value' => '+50 000', 'label' => 'Colis distribués / mois', 'description' => 'Sur tout le territoire marocain'],
                ['value' => '58+', 'label' => 'Villes & Régions couvertes', 'description' => 'Agences et hubs dédiés'],
            ],
            'testimonials' => [
                [
                    'name' => 'Youssef El Amrani',
                    'role' => 'Fondateur @ AtlasWear Maroc',
                    'city' => 'Casablanca',
                    'quote' => 'Depuis qu’on travaille avec Ouais Express, nos retours ont baissé de 30% grâce à la réactivité de leurs livreurs et aux confirmations d’adresse WhatsApp.',
                    'avatar' => 'YA',
                    'rating' => 5,
                ],
                [
                    'name' => 'Sarah Benjelloun',
                    'role' => 'Directrice E-commerce @ BioCosmetics',
                    'city' => 'Rabat',
                    'quote' => 'Le versement CRBT à J+2 sans retard est vital pour notre trésorerie. C’est la première fois qu’on a un partenaire logistique aussi transparent.',
                    'avatar' => 'SB',
                    'rating' => 5,
                ],
                [
                    'name' => 'Karim Tazi',
                    'role' => 'CEO @ TechStore Marrakech',
                    'city' => 'Marrakech',
                    'quote' => 'Le ramassage quotidien gratuit et le suivi en temps réel nous font gagner des heures chaque semaine. Plateforme au top !',
                    'avatar' => 'KT',
                    'rating' => 5,
                ],
            ],
        ]);
    }

    /**
     * Calculateur de tarif en temps réel pour le simulateur de livraison.
     */
    public function calculateRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'departure' => 'required|string',
            'destination' => 'required|string',
            'weight' => 'required|numeric|min:0.1|max:50',
            'has_crbt' => 'nullable|boolean',
            'crbt_amount' => 'nullable|numeric|min:0',
        ]);

        $departure = $validated['departure'];
        $destination = $validated['destination'];
        $weight = (float) $validated['weight'];
        $hasCrbt = (bool) ($validated['has_crbt'] ?? false);
        $crbtAmount = (float) ($validated['crbt_amount'] ?? 0);

        // Tarification logique de base
        $isLocal = strtolower(trim($departure)) === strtolower(trim($destination));
        $baseRate = $isLocal ? 25.0 : 35.0;

        // Supplément poids au-delà de 5kg
        $weightSurcharge = 0;
        if ($weight > 5.0) {
            $weightSurcharge = ($weight - 5.0) * 3.0;
        }

        // Frais d'encaissement CRBT (1% du montant ou min 5 MAD)
        $crbtFee = 0;
        if ($hasCrbt && $crbtAmount > 0) {
            $crbtFee = max(5.0, round($crbtAmount * 0.01, 2));
        }

        $totalRate = round($baseRate + $weightSurcharge + $crbtFee, 2);
        $deliveryDelay = $isLocal ? '24 heures (Même jour possible)' : '24 à 48 heures';

        return response()->json([
            'success' => true,
            'base_rate' => $baseRate,
            'weight_surcharge' => $weightSurcharge,
            'crbt_fee' => $crbtFee,
            'total_rate' => $totalRate,
            'estimated_delay' => $deliveryDelay,
            'currency' => 'MAD',
        ]);
    }

    /**
     * Recherche / Simulation de suivi de colis en direct.
     */
    public function trackParcel(Request $request): JsonResponse
    {
        $request->validate([
            'tracking_code' => 'required|string|min:4|max:50',
        ]);

        $code = strtoupper(trim($request->input('tracking_code')));

        // Simulation réaliste et interactive pour la démo / client
        $now = now();
        $timeline = [
            [
                'status' => 'Colis Réceptionné au Hub',
                'description' => 'Votre colis a été collecté auprès de l’expéditeur et scanné au centre de tri Ouais Express.',
                'location' => 'Hub Central - Casablanca',
                'date' => $now->copy()->subHours(18)->format('d/m/Y H:i'),
                'completed' => true,
            ],
            [
                'status' => 'En Transit Inter-Agences',
                'description' => 'Le colis est chargé dans la navette sécurisée en direction de l’agence de destination.',
                'location' => 'Navette Nationale Ouais Express',
                'date' => $now->copy()->subHours(8)->format('d/m/Y H:i'),
                'completed' => true,
            ],
            [
                'status' => 'Arrivé à l’Agence Régionale',
                'description' => 'Prise en charge par l’équipe locale, vérification des informations destinataire.',
                'location' => 'Agence Régionale',
                'date' => $now->copy()->subHours(3)->format('d/m/Y H:i'),
                'completed' => true,
            ],
            [
                'status' => 'En Cours de Distribution',
                'description' => 'Le livreur dédié est en route. Un SMS de confirmation avec code de sécurité a été envoyé.',
                'location' => 'Secteur Destination',
                'date' => $now->copy()->subMinutes(45)->format('d/m/Y H:i'),
                'completed' => true,
                'current' => true,
            ],
            [
                'status' => 'Livré & Encaissé (CRBT)',
                'description' => 'Remise finale au destinataire contre signature et encaissement des fonds.',
                'location' => 'Adresse Destinataire',
                'date' => 'Prévu aujourd’hui avant 18:00',
                'completed' => false,
            ],
        ];

        return response()->json([
            'success' => true,
            'tracking_code' => $code,
            'current_status' => 'En cours de distribution',
            'status_badge' => 'en_cours_livraison',
            'recipient_city' => 'Casablanca / Quartier Maarif',
            'eta' => 'Aujourd’hui avant 18:00',
            'timeline' => $timeline,
        ]);
    }
}
