<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Transporteur du réseau national (structure Zakaria2 `delivery_partners`, améliorée).
 *
 *  · api_key_hash   : empreinte SHA-256 du jeton d'API (le jeton en clair n'est montré qu'une fois)
 *  · webhook_secret : secret de signature HMAC-SHA256, chiffré en base (cast « encrypted »)
 *  · user_id        : compte de l'ancien site utilisé pour enregistrer ses bons (#76, #102)
 */
class DeliveryPartner extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'transporteur_ligne' => 'Transporteur de ligne',
        'agence_consoeur' => 'Agence consœur',
        'plateforme_nationale' => 'Plateforme nationale',
    ];

    public const FORMATS = [
        'json_standard' => 'JSON standard (Zakaria2)',
        'webhook_winning' => 'Webhook WinningExpress',
        'custom' => 'Personnalisé',
    ];

    protected $table = 'delivery_partners';

    protected $fillable = [
        'code', 'name', 'ice', 'telephone', 'email', 'user_id', 'notes', 'is_active', 'api_endpoint',
        'format_payload', 'type_partenaire', 'adresse', 'ville_id', 'commission_defaut', 'delai_transit_moyen_h',
    ];

    protected $hidden = ['api_key_hash', 'webhook_secret'];

    protected $casts = [
        'is_active' => 'boolean',
        'webhook_secret' => 'encrypted',
        'last_sync_at' => 'datetime',
        'commission_defaut' => 'decimal:2',
        'delai_transit_moyen_h' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function scopeActifs(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Nouveau jeton d'API : renvoyé en clair UNE fois, seule son empreinte est gardée. */
    public function genererJeton(): string
    {
        $jeton = 'oex_'.Str::random(48);
        $this->forceFill(['api_key_hash' => hash('sha256', $jeton)])->save();

        return $jeton;
    }

    /** Nouveau secret de signature : renvoyé en clair UNE fois (stocké chiffré). */
    public function genererSecret(): string
    {
        $secret = Str::random(64);
        $this->forceFill(['webhook_secret' => $secret])->save();

        return $secret;
    }

    public static function parJeton(?string $jeton): ?self
    {
        if ($jeton === null || $jeton === '') {
            return null;
        }

        return static::query()->where('api_key_hash', hash('sha256', $jeton))->first();
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class);
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'delivery_partner_zones')
            ->using(DeliveryPartnerZone::class)
            ->withPivot(['id', 'tarif_transit_unitaire', 'delai_garanti_h', 'is_exclusif'])
            ->withTimestamps();
    }

    public function couvertures(): HasMany
    {
        return $this->hasMany(DeliveryPartnerZone::class);
    }

    public function statusMappings(): HasMany
    {
        return $this->hasMany(PartnerStatusMapping::class);
    }

    public function webhookLogs(): HasMany
    {
        return $this->hasMany(PartnerWebhookLog::class);
    }

    public function bonsEnvoi(): HasMany
    {
        return $this->hasMany(BonEnvoi::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(Command::class);
    }
}
