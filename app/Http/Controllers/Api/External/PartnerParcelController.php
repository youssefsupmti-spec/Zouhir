<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\External;

use App\Domain\Reseau\Api\PasserelleService;
use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use App\Models\PartnerWebhookLog;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * API externe des partenaires (/api/v1/external/…) et webhook de statut au format Zakaria2
 * (/api/v1/webhooks/{code}). Chaque appel qui écrit est journalisé dans partner_webhook_logs.
 */
final class PartnerParcelController extends Controller
{
    public function __construct(private readonly PasserelleService $passerelle) {}

    public function import(Request $request): JsonResponse
    {
        return $this->journaliser($request, function (DeliveryPartner $p) use ($request) {
            $r = $this->passerelle->importer($p, $request->json()->all());

            return [$r, count($r['acceptes']) > 0 || count($r['doublons']) > 0 ? 200 : 422, null];
        });
    }

    public function track(Request $request, string $tracking): JsonResponse
    {
        $r = $this->passerelle->suivre($this->partenaire($request), $tracking);

        return $r === null
            ? response()->json(['error' => 'not_found', 'message' => 'Colis inconnu pour ce partenaire.'], 404)
            : response()->json(['data' => $r]);
    }

    public function status(Request $request): JsonResponse
    {
        return $this->journaliser($request, function (DeliveryPartner $p) use ($request) {
            $r = $this->passerelle->changerStatut($p, $request->json()->all());

            return [['result' => 'processed'] + $r, 200, $r['code']];
        });
    }

    /** Format Zakaria2 : POST /api/v1/webhooks/{code}, authentifié par la seule signature. */
    public function webhook(Request $request, DeliveryPartner $partner): JsonResponse
    {
        return $this->status($request);
    }

    public function receiveBon(Request $request, string $code): JsonResponse
    {
        return $this->journaliser($request, function (DeliveryPartner $p) use ($request, $code) {
            $r = $this->passerelle->recevoirBon($p, $code, $request->json()->all());

            return $r === null
                ? [['error' => 'not_found', 'message' => "Bon d’envoi « {$code} » inconnu pour ce partenaire."], 404, null]
                : [$r, 200, null];
        });
    }

    public function referentiel(): JsonResponse
    {
        return response()->json(['data' => $this->passerelle->referentiel()]);
    }

    public function estimate(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => $this->passerelle->estimer($this->partenaire($request), $request->query('destination_city'), $request->query('destination_zone'))]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'validation', 'errors' => $e->errors()], 422);
        }
    }

    private function partenaire(Request $request): DeliveryPartner
    {
        return $request->attributes->get('partenaire');
    }

    /**
     * Exécute l'opération et l'inscrit au journal (réussie ou non).
     *
     * @param  Closure(DeliveryPartner): array{0: array, 1: int, 2: ?string}  $operation
     */
    private function journaliser(Request $request, Closure $operation): JsonResponse
    {
        $partenaire = $this->partenaire($request);
        $log = PartnerWebhookLog::create([
            'delivery_partner_id' => $partenaire->id,
            'direction' => 'entrant',
            'endpoint' => mb_substr($request->path(), 0, 120),
            'payload' => $request->json()->all(),
            'processed' => false,
            'signature_valide' => (bool) $request->attributes->get('signature_valide'),
            'ip' => $request->ip(),
        ]);

        try {
            [$corps, $statut, $code] = $operation($partenaire);
        } catch (ValidationException $e) {
            $log->update(['http_status' => 422, 'error_message' => collect($e->errors())->flatten()->implode(' ')]);

            return response()->json(['error' => 'validation', 'errors' => $e->errors()], 422);
        }

        $log->update([
            'http_status' => $statut,
            'processed' => $statut < 300,
            'command_id' => $code ? \App\Models\Command::query()->where('code', $code)->value('id') : null,
            'error_message' => $statut >= 300 ? mb_substr(json_encode($corps, JSON_UNESCAPED_UNICODE), 0, 2000) : null,
        ]);

        return response()->json($corps, $statut);
    }
}
