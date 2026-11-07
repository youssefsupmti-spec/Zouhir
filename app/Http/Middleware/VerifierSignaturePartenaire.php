<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DeliveryPartner;
use App\Models\PartnerWebhookLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie la signature HMAC-SHA256 du corps brut de la requête :
 *   X-Signature-SHA256 (ou X-Partner-Signature, en-tête de Zakaria2) = hash_hmac('sha256', corps, secret)
 *
 * Amélioration sur Zakaria2 : la signature est OBLIGATOIRE (un partenaire sans secret configuré
 * est refusé) et le secret est distinct du jeton d'API. Comparaison en temps constant.
 * Le partenaire vient soit du jeton (attribut « partenaire »), soit de la route /webhooks/{code}.
 */
final class VerifierSignaturePartenaire
{
    public function handle(Request $request, Closure $next): Response
    {
        $partenaire = $request->attributes->get('partenaire') ?? $request->route('partner');
        if (! $partenaire instanceof DeliveryPartner) {
            return response()->json(['error' => 'unknown_partner'], 404);
        }
        if (! $partenaire->is_active) {
            return response()->json(['error' => 'partner_inactive', 'message' => 'Partenaire désactivé.'], 403);
        }

        $signature = (string) ($request->header('X-Signature-SHA256') ?? $request->header('X-Partner-Signature') ?? '');
        $secret = (string) ($partenaire->webhook_secret ?? '');
        $valide = $secret !== '' && $signature !== '' && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);

        if (! $valide) {
            PartnerWebhookLog::create([
                'delivery_partner_id' => $partenaire->id,
                'direction' => 'entrant',
                'endpoint' => mb_substr($request->path(), 0, 120),
                'payload' => $request->json()->all() ?: ['brut' => mb_substr($request->getContent(), 0, 2000)],
                'processed' => false,
                'signature_valide' => false,
                'http_status' => 401,
                'error_message' => $secret === '' ? 'Secret de signature non configuré.' : 'Signature absente ou invalide.',
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'invalid_signature', 'message' => 'Signature X-Signature-SHA256 absente ou invalide.'], 401);
        }

        $request->attributes->set('partenaire', $partenaire);
        $request->attributes->set('signature_valide', true);

        return $next($request);
    }
}
