<?php

/*
 * Reprise de l'ancienne base OUAISS EXPRESS (u405480831_stockout) — LECTURE SEULE.
 *
 * La reprise ne fait que des SELECT sur la connexion legacy ; toutes les écritures vont dans la
 * base du nouveau projet. Les identifiants de la connexion legacy viennent du .env (LEGACY_DB_*).
 */
return [
    'connexion' => env('REPRISE_CONNEXION_LEGACY', 'legacy_stockout'),

    /*
     * Comptes « livreur » de l'ancien site utilisés pour enregistrer les envois au réseau national
     * (rapport 58 : #76 « Abdelkrim livreur », #102 « Wini ouaiss »). Leurs bons `BL-…` deviennent
     * des bons d'envoi et chaque compte un partenaire. Décision signalée dans les anomalies.
     */
    'comptes_partenaires' => array_values(array_filter(array_map('intval', explode(',', (string) env('REPRISE_COMPTES_PARTENAIRES', '76,102'))))),

    // Au-delà, un montant de colis est signalé comme probablement erroné (il est repris tel quel).
    'montant_suspect' => 100000,

    'taille_lot' => 1000,
];
