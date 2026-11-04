<?php

declare(strict_types=1);

namespace App\Domain\Reprise\Etapes;

use App\Domain\Villes\Support\VilleInconnue;
use App\Domain\Villes\Support\VilleRef;
use App\Domain\Villes\Support\VilleResolution;
use App\Models\Agence;
use Illuminate\Support\Facades\DB;

/**
 * Colis : `commands` legacy → `commands` (même id). Valeurs reprises À L'IDENTIQUE ; tout ce qui ne
 * se rattache pas exactement (expéditeur absent, ville introuvable, valeur inattendue…) est repris
 * sans invention et listé dans les anomalies. Les champs legacy sans équivalent sont copiés dans
 * `legacy_brut`. Les frais (tarifs) calculés par leurs propres reprises ne sont jamais écrasés.
 */
final class RepriseColis extends EtapeReprise
{
    /** Colonnes écrites par cette étape lors d'une mise à jour (les frais/tarifs n'y figurent pas). */
    private const COLONNES_MAJ = [
        'code', 'produit', 'quantite', 'client_id', 'business_id', 'business_name', 'nom_cliente',
        'livreur_id', 'livreur_nom', 'livreur_telephone', 'agence_id',
        'destinataire_nom', 'destinataire_telephone', 'destinataire_adresse', 'destinataire_ville',
        'destinataire_ville_id', 'destinataire_zone_code', 'destinataire_zone_id',
        'prix_brut', 'montant_supplementaire', 'montant_emballage',
        'state', 'state_group', 'statut_id', 'is_ramassee', 'ouverture_autorisee', 'echange',
        'facture_client', 'facture_livreur', 'note', 'datereported_at', 'legacy_brut',
        'created_at', 'updated_at', 'deleted_at',
    ];

    /** @var array<string, array{ids: list<int>, methode: string, ville_id: ?int}> */
    private array $villesRapprochees = [];

    /** @var array<int, array{nom: string, ids: list<int>}> */
    private array $villesSansZone = [];

    public static function nom(): string
    {
        return 'colis';
    }

    public static function domaines(): array
    {
        return ['commandes', 'clients', 'villes', 'zones'];
    }

    public function executer(): array
    {
        // Sentinelle d'abord : les référentiels chargés ensuite la contiennent.
        $uknowVilleId = $this->simulation
            ? DB::table('villes')->where('name_fr', VilleInconnue::NOM_VILLE)->value('id')
            : VilleInconnue::assurer()[0];

        $users = DB::table('users')->get(['id', 'nom_complet', 'telephone', 'role'])->keyBy('id');
        $business = [];
        foreach (DB::table('businesses')->orderBy('id')->get(['id', 'user_id', 'name']) as $b) {
            $business[(int) $b->user_id] ??= $b;
        }
        $villes = DB::table('villes')->get(['id', 'name_fr', 'zone_id', 'code_zone'])->keyBy('id');
        $zonesExistantes = DB::table('zones')->whereNull('deleted_at')->pluck('id', 'id');
        $statuts = RepriseStatuts::carte();
        $groupes = DB::table('statuts')->pluck('groupe', 'id');
        $agenceId = Agence::principale()?->id;
        $villeRef = app(VilleRef::class);


        $suspect = (float) config('reprise.montant_suspect', 100000);
        $total = 0;
        $sansClient = 0;

        $this->legacy->table('commands')->orderBy('id')->chunkById($this->taille(), function ($lot) use (
            $users, $business, $villes, $zonesExistantes, $statuts, $groupes, $agenceId, $villeRef, $uknowVilleId, $suspect, &$total, &$sansClient
        ) {
            $lignes = [];
            foreach ($lot as $c) {
                $id = (int) $c->id;
                $total++;

                // Expéditeur
                $clientLegacy = (int) $c->client;
                $client = $users[$clientLegacy] ?? null;
                if ($client === null) {
                    $sansClient++;
                    $this->journal->signaler('clients', $clientLegacy === 0 ? 'colis_sans_expediteur' : 'expediteur_introuvable', 'bloquant',
                        $clientLegacy === 0 ? "Colis {$c->code} sans expéditeur dans l’ancienne base" : "Colis {$c->code} : expéditeur #{$clientLegacy} introuvable",
                        'commands', $id, (string) $clientLegacy, [], 'commands', $id);
                } elseif ($client->role !== 'client') {
                    $this->journal->signaler('clients', 'expediteur_non_client', 'attention', "Colis {$c->code} : l’expéditeur #{$client->id} « {$client->nom_complet} » n’est pas un compte client ({$client->role})", 'commands', $id, null, [], 'commands', $id);
                }
                $biz = $client !== null ? ($business[(int) $client->id] ?? null) : null;
                if ($client !== null && $biz === null) {
                    $this->journal->signaler('clients', 'client_sans_enseigne', 'attention', "Client #{$client->id} « {$client->nom_complet} » sans enseigne (business)", 'users', $client->id);
                }

                // Livreur
                $dlm = (int) $c->dlm;
                $livreur = $dlm > 0 ? ($users[$dlm] ?? null) : null;
                if ($dlm > 0 && $livreur === null) {
                    $this->journal->signaler('livreurs', 'livreur_introuvable', 'bloquant', "Colis {$c->code} : livreur #{$dlm} introuvable", 'commands', $id, (string) $dlm, [], 'commands', $id);
                }

                // Statut
                $state = trim((string) $c->state);
                $statutId = $statuts[$state] ?? null;
                $groupe = $statutId !== null ? ($groupes[$statutId] ?? 'en_cours') : 'en_cours';
                if ($statutId === null) {
                    $this->journal->signaler('statuts', 'statut_colis_inconnu', 'bloquant', "Colis {$c->code} : statut « {$state} » introuvable dans le référentiel", 'commands', $id, $state, [], 'commands', $id);
                }
                if ($dlm === 0 && in_array($groupe, ['livre', 'retour'], true)) {
                    $this->journal->signaler('livreurs', 'colis_traite_sans_livreur', 'attention', "Colis {$c->code} « {$state} » sans livreur dans l’ancienne base", 'commands', $id, $state, [], 'commands', $id);
                }

                // Ville / zone
                $libelleVille = trim((string) $c->city);
                $villeId = null;
                if ($libelleVille === '' || $libelleVille === '=') {
                    $villeId = $uknowVilleId;
                    $this->rapprocher('__vide__', $id, 'vide', $uknowVilleId);
                } else {
                    $res = $villeRef->resoudre($libelleVille, historique: true);
                    if ($res->estResolue()) {
                        $villeId = $res->villeId;
                        if ($res->methode !== VilleResolution::EXACTE) {
                            $this->rapprocher($libelleVille, $id, $res->methode, $villeId);
                        }
                    } else {
                        $villeId = $uknowVilleId;
                        $this->rapprocher($libelleVille, $id, 'introuvable', $uknowVilleId);
                    }
                }
                $ville = $villeId !== null ? ($villes[$villeId] ?? null) : null;
                $zoneId = $ville?->zone_id !== null && isset($zonesExistantes[$ville->zone_id]) ? (int) $ville->zone_id : null;
                if ($ville !== null && $zoneId === null) {
                    $this->villesSansZone[(int) $ville->id]['nom'] = $ville->name_fr;
                    $this->villesSansZone[(int) $ville->id]['ids'][] = $id;
                }

                // Montants
                $prix = round((float) $c->price, 2);
                if ($prix < 0 || $prix > $suspect) {
                    $this->journal->signaler('commandes', $prix < 0 ? 'montant_negatif' : 'montant_suspect', 'attention',
                        "Colis {$c->code} : montant {$prix} DH ".($prix < 0 ? 'négatif' : 'anormalement élevé').' (repris tel quel)', 'commands', $id, (string) $c->price, [], 'commands', $id);
                }

                // Quantité
                $qty = trim((string) $c->qty);
                $quantite = ctype_digit($qty) && (int) $qty > 0 ? (int) $qty : 1;
                if ($qty !== '' && ! (ctype_digit($qty) && (int) $qty > 0)) {
                    $this->journal->signaler('commandes', 'quantite_invalide', 'attention', "Colis {$c->code} : quantité « ".mb_substr($qty, 0, 80).' » non numérique (1 retenu)', 'commands', $id, $qty, [], 'commands', $id);
                }

                // Indicateurs
                $ouverture = (int) $c->openpackage;
                if (! in_array($ouverture, [0, 1], true)) {
                    $this->journal->signaler('commandes', 'ouverture_valeur_inattendue', 'attention', "Colis {$c->code} : « ouverture autorisée » vaut {$ouverture} (ni 0 ni 1 — retenu : non)", 'commands', $id, (string) $ouverture, [], 'commands', $id);
                }
                $reporte = self::date($c->datereported);
                if (self::texte($c->datereported) !== null && $reporte === null) {
                    $this->journal->signaler('commandes', 'date_report_invalide', 'attention', "Colis {$c->code} : date de report « {$c->datereported} » invalide", 'commands', $id, (string) $c->datereported, [], 'commands', $id);
                }

                $creation = self::date($c->dateadd);
                $maj = self::date($c->dateupdate) ?? $creation;

                $lignes[] = [
                    'id' => $id,
                    'code' => trim((string) $c->code),
                    'produit' => self::texte($c->product),
                    'quantite' => $quantite,
                    'client_id' => $client?->id,
                    'business_id' => $biz?->id,
                    'business_name' => $biz?->name,
                    'nom_cliente' => self::texte($c->clientname) ?? $client?->nom_complet,
                    'livreur_id' => $livreur?->id,
                    'livreur_nom' => $livreur?->nom_complet,
                    'livreur_telephone' => $livreur?->telephone,
                    'agence_id' => $agenceId,
                    'destinataire_nom' => trim((string) $c->fullname),
                    'destinataire_telephone' => trim((string) $c->phone),
                    'destinataire_adresse' => self::texte($c->address),
                    'destinataire_ville' => $libelleVille,
                    'destinataire_ville_id' => $villeId,
                    'destinataire_zone_code' => $ville?->code_zone,
                    'destinataire_zone_id' => $zoneId,
                    'prix_brut' => $prix,
                    'montant_supplementaire' => round((float) $c->extrafees, 2),
                    'montant_emballage' => round((float) $c->package, 2),
                    'state' => $state,
                    'state_group' => $groupe,
                    'statut_id' => $statutId,
                    'is_ramassee' => trim((string) $c->collected) === 'on',
                    'ouverture_autorisee' => $ouverture === 1,
                    'echange' => (int) $c->echange === 1,
                    'facture_client' => trim((string) $c->invoiced) === 'on',
                    'facture_livreur' => trim((string) $c->invoiceddlm) === 'on',
                    'note' => self::texte($c->note),
                    'datereported_at' => $reporte,
                    'legacy_brut' => json_encode([
                        'qty' => $c->qty, 'client' => $c->client, 'dlm' => $c->dlm, 'subdlm' => $c->subdlm,
                        'clientname' => $c->clientname, 'clientphone' => $c->clientphone, 'city' => $c->city,
                        'collected' => $c->collected, 'invoiced' => $c->invoiced, 'invoiceddlm' => $c->invoiceddlm,
                        'archived' => $c->archived, 'openpackage' => $c->openpackage, 'treated' => $c->treated,
                        'datereported' => $c->datereported, 'dateadd' => $c->dateadd, 'dateupdate' => $c->dateupdate, 'trash' => $c->trash,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $creation,
                    'updated_at' => $maj,
                    // Corbeille legacy (trash = 0) → suppression logique, datée de la dernière modification.
                    'deleted_at' => (int) $c->trash === 0 ? $maj : null,
                ];
            }

            if (! $this->simulation && $lignes !== []) {
                foreach (array_chunk($lignes, 250) as $morceau) {
                    DB::table('commands')->upsert($morceau, ['id'], self::COLONNES_MAJ);
                }
            }
            $this->info("  colis : {$total}");
        });

        $this->signalerVilles();
        $this->signalerCodesEnDouble();

        $this->journal->cloturer(['commandes', 'clients', 'villes', 'zones', 'livreurs', 'statuts'], [
            'colis_sans_expediteur', 'expediteur_introuvable', 'expediteur_non_client', 'client_sans_enseigne',
            'livreur_introuvable', 'colis_traite_sans_livreur', 'statut_colis_inconnu',
            'ville_vide', 'ville_introuvable', 'ville_rapprochee', 'ville_sans_zone',
            'montant_negatif', 'montant_suspect', 'quantite_invalide', 'ouverture_valeur_inattendue', 'date_report_invalide', 'code_en_double',
        ]);

        return ['colis' => $total, 'sans_expediteur' => $sansClient, 'villes_non_exactes' => count($this->villesRapprochees)];
    }

    private function rapprocher(string $libelle, int $colisId, string $methode, ?int $villeId): void
    {
        $this->villesRapprochees[$libelle]['ids'][] = $colisId;
        $this->villesRapprochees[$libelle]['methode'] = $methode;
        $this->villesRapprochees[$libelle]['ville_id'] = $villeId;
    }

    private function signalerVilles(): void
    {
        $noms = DB::table('villes')->whereIn('id', array_filter(array_column($this->villesRapprochees, 'ville_id')))->pluck('name_fr', 'id');

        foreach ($this->villesRapprochees as $libelle => $v) {
            $n = count($v['ids']);
            $details = ['colis' => $n, 'colis_ids' => $v['ids'], 'methode' => $v['methode']];
            if ($v['methode'] === 'vide') {
                $this->journal->signaler('villes', 'ville_vide', 'bloquant', "{$n} colis sans ville (vide ou « = ») → rangés dans Uknow-ville", 'commands', null, '', $details);
            } elseif ($v['methode'] === 'introuvable') {
                $this->journal->signaler('villes', 'ville_introuvable', 'bloquant', "Ville « {$libelle} » introuvable dans le référentiel : {$n} colis → Uknow-ville", 'commands', null, $libelle, $details);
            } else {
                $cible = $noms[$v['ville_id']] ?? '?';
                $this->journal->signaler('villes', 'ville_rapprochee', 'info', "Ville « {$libelle} » rattachée à « {$cible} » (règle : {$v['methode']}) — {$n} colis", 'commands', null, $libelle, $details + ['ville_id' => $v['ville_id']], 'villes', $v['ville_id']);
            }
        }

        foreach ($this->villesSansZone as $villeId => $v) {
            $n = count($v['ids']);
            $this->journal->signaler('zones', 'ville_sans_zone', 'attention', "Ville « {$v['nom']} » sans zone : {$n} colis sans zone de destination", 'villes', $villeId, null, ['colis' => $n, 'colis_ids' => $v['ids']], 'villes', $villeId);
        }
    }

    private function signalerCodesEnDouble(): void
    {
        $doublons = $this->legacy->table('commands')->selectRaw('code, COUNT(*) AS n')->groupBy('code')->havingRaw('COUNT(*) > 1')->get();
        foreach ($doublons as $d) {
            $ids = $this->legacy->table('commands')->where('code', $d->code)->orderBy('id')->pluck('id')->map(fn ($i) => (int) $i)->all();
            $this->journal->signaler('commandes', 'code_en_double', 'attention', "Code « {$d->code} » porté par {$d->n} colis", 'commands', null, (string) $d->code, ['colis_ids' => $ids]);
        }
    }
}
