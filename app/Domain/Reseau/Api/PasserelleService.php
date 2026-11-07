<?php

declare(strict_types=1);

namespace App\Domain\Reseau\Api;

use App\Domain\Colis\Support\GroupesColis;
use App\Domain\StatutsColis\ClassificationEtats;
use App\Domain\Villes\Support\VilleRef;
use App\Domain\Villes\Support\VilleResolution;
use App\Models\Agence;
use App\Models\BonEnvoi;
use App\Models\BonEnvoiLigne;
use App\Models\Command;
use App\Models\CommandStatusHistory;
use App\Models\DeliveryPartner;
use App\Models\NoteMessage;
use App\Models\Statut;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Passerelle API des partenaires du réseau national (contrat JSON compatible Zakaria2 /
 * WinningExpress). Aucune valeur n'est devinée : une ville ou un statut inconnu est refusé,
 * avec la raison, et l'échange reste journalisé (partner_webhook_logs).
 */
final class PasserelleService
{
    /** Statuts standard (Zakaria2 / WinningExpress) → statut du référentiel, si aucune correspondance propre au partenaire. */
    public const STATUTS_STANDARD = [
        'DELIVERED' => 'Livré',
        'IN_TRANSIT' => 'Mise en distribution',
        'OUT_FOR_DELIVERY' => 'Mise en distribution',
        'PICKED_UP' => 'Ramassé',
        'POSTPONED' => 'Reporté',
        'SCHEDULED' => 'Programmé',
        'NO_ANSWER' => 'Pas de réponse',
        'UNREACHABLE' => 'Injoignable',
        'REFUSED' => 'Refusé',
        'CANCELLED' => 'Annulé',
        'OUT_OF_ZONE' => 'Hors zone',
        'RETURNED' => 'Retourné',
        'RETURN_DELIVERED' => 'Retour client reçu',
    ];

    public function __construct(private readonly VilleRef $villes) {}

    /**
     * Import d'un lot de colis. Idempotent : un n° de suivi déjà reçu de ce partenaire renvoie
     * le colis existant (« doublon ») sans en créer un second.
     *
     * @return array{batch_reference: ?string, acceptes: list<array>, doublons: list<array>, refuses: list<array>}
     */
    public function importer(DeliveryPartner $partenaire, array $payload): array
    {
        $v = Validator::make($payload, [
            'batch_reference' => ['nullable', 'string', 'max:100'],
            'parcels' => ['required', 'array', 'min:1', 'max:500'],
            'parcels.*.tracking_number' => ['required', 'string', 'max:100', 'distinct'],
            'parcels.*.recipient_name' => ['required', 'string', 'max:255'],
            'parcels.*.recipient_phone' => ['required', 'string', 'max:30'],
            'parcels.*.recipient_address' => ['nullable', 'string', 'max:500'],
            'parcels.*.destination_city' => ['required', 'string', 'max:120'],
            'parcels.*.crbt_amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'parcels.*.product' => ['nullable', 'string', 'max:255'],
            'parcels.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'parcels.*.is_fragile' => ['nullable', 'boolean'],
            'parcels.*.is_oversized' => ['nullable', 'boolean'],
            'parcels.*.allow_opening' => ['nullable', 'boolean'],
            'parcels.*.instructions' => ['nullable', 'string', 'max:2000'],
        ]);
        $v->validate();

        $acceptes = $doublons = $refuses = [];
        $statutAjoute = Statut::query()->where('libelle', GroupesColis::STATUT_PAR_DEFAUT['nouveau'])->orderByDesc('actif')->orderBy('id')->value('id');
        $agenceId = Agence::principale()?->id;

        foreach ($payload['parcels'] as $p) {
            $suivi = trim((string) $p['tracking_number']);
            $existant = Command::withTrashed()->where('delivery_partner_id', $partenaire->id)->where('partner_tracking_code', $suivi)->first(['id', 'code']);
            if ($existant !== null) {
                $doublons[] = ['tracking_number' => $suivi, 'code' => $existant->code];

                continue;
            }

            $res = $this->villes->resoudre(trim((string) $p['destination_city']));
            if (! $res->estResolue() || $res->methode === VilleResolution::HOMONYMES) {
                $refuses[] = ['tracking_number' => $suivi, 'erreurs' => ['destination_city' => $res->estResolue()
                    ? 'Plusieurs villes portent ce nom : précisez-la.'
                    : "Ville « {$p['destination_city']} » inconnue du référentiel (voir /referentiel/zones-cities)."]];

                continue;
            }
            $ville = Ville::query()->find($res->villeId, ['id', 'name_fr', 'zone_id', 'code_zone']);

            $colis = DB::transaction(function () use ($partenaire, $p, $suivi, $ville, $statutAjoute, $agenceId) {
                $colis = Command::create([
                    'code' => $this->nouveauCode(),
                    'produit' => $p['product'] ?? null,
                    'quantite' => (int) ($p['quantity'] ?? 1),
                    'business_name' => $partenaire->name,
                    'nom_cliente' => $partenaire->name,
                    'delivery_partner_id' => $partenaire->id,
                    'partner_tracking_code' => $suivi,
                    'agence_id' => $agenceId,
                    'destinataire_nom' => $p['recipient_name'],
                    'destinataire_telephone' => $p['recipient_phone'],
                    'destinataire_adresse' => $p['recipient_address'] ?? null,
                    'destinataire_ville' => $ville->name_fr,
                    'destinataire_ville_id' => $ville->id,
                    'destinataire_zone_code' => $ville->code_zone,
                    'destinataire_zone_id' => $ville->zone_id,
                    'prix_brut' => $p['crbt_amount'],
                    'state' => GroupesColis::STATUT_PAR_DEFAUT['nouveau'],
                    'state_group' => 'nouveau',
                    'statut_id' => $statutAjoute,
                    'ouverture_autorisee' => (bool) ($p['allow_opening'] ?? false),
                ]);
                $this->historiser($colis, null, $colis->state, $colis->statut_id, $partenaire, 'Import API');

                $consignes = array_filter([
                    ($p['is_fragile'] ?? false) ? 'Colis fragile' : null,
                    ($p['is_oversized'] ?? false) ? 'Colis volumineux' : null,
                    trim((string) ($p['instructions'] ?? '')) ?: null,
                ]);
                if ($consignes !== []) {
                    $this->noter($colis, $partenaire, implode(' · ', $consignes));
                }

                return $colis;
            });

            $acceptes[] = ['tracking_number' => $suivi, 'code' => $colis->code, 'destination' => $ville->name_fr, 'zone' => $ville->code_zone];
        }

        $partenaire->forceFill(['last_sync_at' => now()])->save();

        return ['batch_reference' => $payload['batch_reference'] ?? null, 'acceptes' => $acceptes, 'doublons' => $doublons, 'refuses' => $refuses];
    }

    /** Suivi d'un colis du partenaire (par son n° de suivi ou par notre code). */
    public function suivre(DeliveryPartner $partenaire, string $suivi): ?array
    {
        $colis = $this->colisDuPartenaire($partenaire, $suivi);
        if ($colis === null) {
            return null;
        }

        return [
            'code' => $colis->code,
            'tracking_number' => $colis->partner_tracking_code,
            'status' => $colis->state,
            'status_group' => $colis->state_group,
            'destination' => $colis->destinataire_ville,
            'crbt_amount' => (float) $colis->prix_brut,
            'delivered_at' => $colis->livre_at?->toIso8601String(),
            'updated_at' => $colis->updated_at?->toIso8601String(),
            'history' => $colis->historiques()->get(['to_state', 'created_at', 'change_source'])
                ->map(fn ($h) => ['status' => $h->to_state, 'at' => $h->created_at?->toIso8601String(), 'source' => $h->change_source])->all(),
        ];
    }

    /**
     * Changement de statut envoyé par le partenaire. Formats acceptés :
     *  · Zakaria2        : { "tracking_code": "...", "status": "DELIVERED", "notes": "..." }
     *  · Rapport 63 / WE : { "event": "parcel.status_changed", "data": { "tracking_number", "status_code", ... } }
     *
     * @return array{code: string, status: string}
     */
    public function changerStatut(DeliveryPartner $partenaire, array $payload): array
    {
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
        $suivi = trim((string) ($data['tracking_number'] ?? $data['tracking_code'] ?? $data['reference'] ?? ''));
        $statutPartenaire = strtoupper(trim((string) ($data['status_code'] ?? $data['status'] ?? '')));

        if ($suivi === '' || $statutPartenaire === '') {
            throw ValidationException::withMessages(['payload' => 'tracking_number (ou tracking_code) et status_code (ou status) sont obligatoires.']);
        }
        $colis = $this->colisDuPartenaire($partenaire, $suivi);
        if ($colis === null) {
            throw ValidationException::withMessages(['tracking_number' => "Aucun colis « {$suivi} » confié à ce partenaire."]);
        }

        $correspondance = $partenaire->statusMappings()->whereRaw('UPPER(partner_status) = ?', [$statutPartenaire])->first();
        $libelle = $correspondance?->colis_status ?? self::STATUTS_STANDARD[$statutPartenaire] ?? null;
        $statut = $correspondance?->statut_id
            ? Statut::query()->find($correspondance->statut_id)
            : ($libelle ? Statut::query()->where('libelle', $libelle)->orderByDesc('actif')->orderBy('id')->first() : null);
        if ($statut === null) {
            throw ValidationException::withMessages(['status_code' => "Statut « {$statutPartenaire} » sans correspondance : à déclarer dans la Passerelle API."]);
        }

        DB::transaction(function () use ($colis, $statut, $partenaire, $data) {
            $avant = $colis->state;
            $groupe = ClassificationEtats::groupePour((int) $statut->id) ?? $statut->groupe;
            $colis->forceFill([
                'state' => $statut->libelle,
                'statut_id' => $statut->id,
                'state_group' => $groupe,
                'is_ramassee' => true,
                'livre_at' => $groupe === 'livre' ? ($this->date($data['delivered_at'] ?? null) ?? now()) : $colis->livre_at,
            ])->save();
            $this->historiser($colis, $avant, $statut->libelle, (int) $statut->id, $partenaire, 'Webhook partenaire');
            if (trim((string) ($data['notes'] ?? '')) !== '') {
                $this->noter($colis, $partenaire, trim((string) $data['notes']));
            }
        });

        $partenaire->forceFill(['last_sync_at' => now()])->save();

        return ['code' => $colis->code, 'status' => $statut->libelle];
    }

    /**
     * Pointage contradictoire d'un bon d'envoi par le partenaire destinataire.
     * Sans liste : tout le bon est confirmé. Avec `parcels` : seules les lignes citées sont
     * pointées ; manquants et colis en trop sont renvoyés.
     */
    public function recevoirBon(DeliveryPartner $partenaire, string $code, array $payload): ?array
    {
        $bon = BonEnvoi::query()->where('code', $code)->where('delivery_partner_id', $partenaire->id)->first();
        if ($bon === null) {
            return null;
        }

        $lignes = BonEnvoiLigne::query()->where('bon_envoi_id', $bon->id)->get();
        $cites = isset($payload['parcels']) && is_array($payload['parcels'])
            ? collect($payload['parcels'])->map(fn ($c) => trim((string) (is_array($c) ? ($c['code'] ?? $c['tracking_number'] ?? '') : $c)))->filter()->unique()->values()
            : null;

        $pointes = $cites === null ? $lignes : $lignes->filter(fn ($l) => $cites->contains($l->command_code));
        $enTrop = $cites === null ? collect() : $cites->diff($lignes->pluck('command_code'))->values();

        DB::transaction(function () use ($bon, $pointes, $lignes) {
            BonEnvoiLigne::query()->whereIn('id', $pointes->pluck('id'))->whereNull('checked_at')
                ->update(['is_checked' => true, 'checked_at' => now(), 'checked_methode' => 'api']);
            $nbPointes = BonEnvoiLigne::query()->where('bon_envoi_id', $bon->id)->where('is_checked', true)->count();
            $bon->forceFill([
                'checked_colis_count' => $nbPointes,
                'status' => $nbPointes === $lignes->count() ? 'traite' : 'expedie',
                'traite_at' => $nbPointes === $lignes->count() ? now() : $bon->traite_at,
            ])->save();
        });

        $partenaire->forceFill(['last_sync_at' => now()])->save();
        $bon->refresh();

        return [
            'code' => $bon->code,
            'status' => $bon->status,
            'total' => $lignes->count(),
            'received' => (int) $bon->checked_colis_count,
            'missing' => BonEnvoiLigne::query()->where('bon_envoi_id', $bon->id)->where('is_checked', false)->pluck('command_code')->filter()->values()->all(),
            'unexpected' => $enTrop->all(),
        ];
    }

    /** Zones desservies et leurs villes (mis en cache 1 h). */
    public function referentiel(): array
    {
        return Cache::remember('api.referentiel.zones-villes', 3600, fn () => Zone::query()->orderBy('code')
            ->with(['villes' => fn ($q) => $q->orderBy('name_fr')->select(['id', 'name_fr', 'name_ar', 'zone_id'])])
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (Zone $z) => [
                'code' => $z->code, 'name' => $z->name, 'type' => $z->type,
                'cities' => $z->villes->map(fn ($v) => ['id' => $v->id, 'name' => $v->name_fr, 'name_ar' => $v->name_ar])->all(),
            ])->all());
    }

    /** Tarif de transit du partenaire pour une ville ou une zone. */
    public function estimer(DeliveryPartner $partenaire, ?string $ville, ?string $zone): array
    {
        $zoneId = null;
        if ($ville !== null && $ville !== '') {
            $res = $this->villes->resoudre($ville);
            $zoneId = $res->estResolue() ? Ville::query()->whereKey($res->villeId)->value('zone_id') : null;
        } elseif ($zone !== null && $zone !== '') {
            $zoneId = Zone::query()->where('code', $zone)->value('id');
        }
        if ($zoneId === null) {
            throw ValidationException::withMessages(['destination' => 'Ville ou zone inconnue.']);
        }
        $ligne = $partenaire->couvertures()->where('zone_id', $zoneId)->first();

        return [
            'zone' => Zone::query()->whereKey($zoneId)->value('code'),
            'covered' => $ligne !== null,
            'transit_rate' => $ligne?->tarif_transit_unitaire !== null ? (float) $ligne->tarif_transit_unitaire : ($partenaire->commission_defaut !== null ? (float) $partenaire->commission_defaut : null),
            'guaranteed_delay_h' => $ligne?->delai_garanti_h ?? $partenaire->delai_transit_moyen_h,
            'source' => $ligne?->tarif_transit_unitaire !== null ? 'zone' : ($partenaire->commission_defaut !== null ? 'defaut' : 'aucun'),
        ];
    }

    private function colisDuPartenaire(DeliveryPartner $partenaire, string $suivi): ?Command
    {
        return Command::query()->where('delivery_partner_id', $partenaire->id)
            ->where(fn ($q) => $q->where('partner_tracking_code', $suivi)->orWhere('code', $suivi))
            ->orderByDesc('id')->first();
    }

    private function historiser(Command $colis, ?string $de, string $vers, ?int $statutId, DeliveryPartner $partenaire, string $raison): void
    {
        CommandStatusHistory::create([
            'command_id' => $colis->id, 'command_code' => $colis->code, 'from_state' => $de, 'to_state' => $vers,
            'to_statut_id' => $statutId, 'changed_by_type' => 'partenaire', 'changed_by_id' => $partenaire->user_id,
            'changed_by_nom' => $partenaire->name, 'change_source' => 'api', 'reason' => $raison, 'created_at' => now(),
        ]);
    }

    private function noter(Command $colis, DeliveryPartner $partenaire, string $texte): void
    {
        NoteMessage::create([
            'command_id' => $colis->id, 'user_id' => $partenaire->user_id, 'role' => 'externe', 'auteur_nom' => $partenaire->name,
            'message' => Str::limit($texte, 2000, ''), 'position' => (int) NoteMessage::where('command_id', $colis->id)->where('source', 'application')->max('position') + 1,
            'source' => 'application', 'date_exacte' => true,
        ]);
    }

    private function nouveauCode(): string
    {
        do {
            $code = 'OEX-'.strtoupper(Str::random(9));
        } while (Command::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    private function date(mixed $valeur): ?\Illuminate\Support\Carbon
    {
        try {
            return $valeur ? \Illuminate\Support\Carbon::parse((string) $valeur) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
