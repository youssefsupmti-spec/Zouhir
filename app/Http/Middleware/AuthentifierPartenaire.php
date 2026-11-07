<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DeliveryPartner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentifie un partenaire du réseau national par jeton Bearer.
 * Le jeton n'est jamais stocké en clair : seule son empreinte SHA-256 (delivery_partners.api_key_hash).
 */
final class AuthentifierPartenaire
{
    public function handle(Request $request, Closure $next): Response
    {
        $partenaire = DeliveryPartner::parJeton($request->bearerToken());

        if ($partenaire === null) {
            return response()->json(['error' => 'unauthenticated', 'message' => 'Jeton d’API absent ou invalide.'], 401);
        }
        if (! $partenaire->is_active) {
            return response()->json(['error' => 'partner_inactive', 'message' => 'Partenaire désactivé.'], 403);
        }

        $request->attributes->set('partenaire', $partenaire);

        return $next($request);
    }
}
