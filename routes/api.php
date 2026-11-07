<?php

use App\Http\Controllers\Api\External\PartnerParcelController;
use App\Http\Middleware\AuthentifierPartenaire;
use App\Http\Middleware\VerifierSignaturePartenaire;
use Illuminate\Support\Facades\Route;

/*
| API des partenaires du réseau national (rapport 63, contrat compatible Zakaria2 / WinningExpress).
|
|  · Lecture : jeton Bearer (Authorization: Bearer oex_…).
|  · Écriture : jeton Bearer + signature X-Signature-SHA256 = HMAC-SHA256(corps brut, secret).
|  · Webhook au format Zakaria2 : POST /api/v1/webhooks/{code} (signature seule, comme Zakaria2).
*/

Route::prefix('v1')->middleware('throttle:120,1')->group(function (): void {
    Route::post('webhooks/{partner:code}', [PartnerParcelController::class, 'webhook'])
        ->middleware(VerifierSignaturePartenaire::class)
        ->name('api.partenaires.webhook');

    Route::prefix('external')->middleware(AuthentifierPartenaire::class)->name('api.external.')->group(function (): void {
        Route::get('parcels/{tracking}/track', [PartnerParcelController::class, 'track'])->name('track');
        Route::get('referentiel/zones-cities', [PartnerParcelController::class, 'referentiel'])->name('referentiel');
        Route::get('rates/estimate', [PartnerParcelController::class, 'estimate'])->name('estimate');

        Route::middleware(VerifierSignaturePartenaire::class)->group(function (): void {
            Route::post('parcels/import', [PartnerParcelController::class, 'import'])->name('import');
            Route::post('parcels/webhook/status', [PartnerParcelController::class, 'status'])->name('status');
            Route::post('bons-envoi/{code}/receive', [PartnerParcelController::class, 'receiveBon'])->name('bons-envoi.receive');
        });
    });
});
