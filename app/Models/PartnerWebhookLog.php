<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal des échanges API avec les partenaires (entrants et sortants). */
class PartnerWebhookLog extends Model
{
    protected $fillable = ['delivery_partner_id', 'direction', 'endpoint', 'payload', 'command_id', 'processed', 'signature_valide', 'http_status', 'error_message', 'ip'];

    protected $casts = ['payload' => 'array', 'processed' => 'boolean', 'signature_valide' => 'boolean', 'http_status' => 'integer'];

    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class, 'delivery_partner_id');
    }

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class)->withTrashed();
    }
}
