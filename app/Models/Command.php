<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Command extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'commands';

    protected $fillable = [
        'code',
        'produit',
        'quantite',
        'client_id',
        'business_id',
        'business_name',
        'nom_cliente',
        'livreur_id',
        'livreur_nom',
        'livreur_telephone',
        'destinataire_nom',
        'destinataire_telephone',
        'destinataire_adresse',
        'destinataire_ville',
        'destinataire_ville_id',
        'destinataire_zone_code',
        'prix_brut',
        'frais_livraison_client',
        'frais_livraison_livreur',
        'state',
        'state_group',
        'is_ramassee',
        'ouverture_autorisee',
        'echange',
        'note',
        'livre_at',
        'expedie_at',
        'ramasse_at',
        'datereported_at',
    ];

    protected $casts = [
        'quantite' => 'integer',
        'prix_brut' => 'decimal:2',
        'frais_livraison_client' => 'decimal:2',
        'frais_livraison_livreur' => 'decimal:2',
        'is_ramassee' => 'boolean',
        'ouverture_autorisee' => 'boolean',
        'echange' => 'boolean',
        'livre_at' => 'datetime',
        'expedie_at' => 'datetime',
        'ramasse_at' => 'datetime',
        'datereported_at' => 'datetime',
    ];

    /** Relations */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function livreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_id');
    }

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'destinataire_ville_id');
    }

    /** Scopes */
    public function scopeLivre(Builder $query): Builder
    {
        return $query->where('state_group', 'livre');
    }

    public function scopeEnCours(Builder $query): Builder
    {
        return $query->where('state_group', 'en_cours');
    }

    public function scopeRetour(Builder $query): Builder
    {
        return $query->where('state_group', 'retour');
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Data Isolation : colis visibles par l'utilisateur selon ses permissions
     * de périmètre effectives (PerimetreColis / ActAs*), jamais selon un nom de rôle.
     * - Administration (ActAsAdmin ou Gate::before admin) : tous les colis.
     * - ActAsClient : colis dont il est l'expéditeur (client_id).
     * - ActAsDriver : colis qui lui sont affectés (livreur_id).
     * - Autres périmètres (ex. ActAsAgency, sans clé de rattachement) : aucun.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $isClient = $user->isClient();
        $isDriver = $user->isDriver();

        if (! $isClient && ! $isDriver) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($user, $isClient, $isDriver) {
            if ($isClient) {
                $q->orWhere('client_id', $user->id);
            }
            if ($isDriver) {
                $q->orWhere('livreur_id', $user->id);
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->isAdmin()
            || ($user->isClient() && (int) $this->client_id === (int) $user->id)
            || ($user->isDriver() && (int) $this->livreur_id === (int) $user->id);
    }
}
