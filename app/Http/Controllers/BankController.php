<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Banques\Enums\StatutBanque;
use App\Http\Requests\Banques\BankRequest;
use App\Models\Bank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page « Banques » : le référentiel des établissements seulement (comme WinningExpress).
 * Les RIB des business sont sur la page « Comptes bancaires » (CompteBancaireController).
 */
class BankController extends Controller
{
    public const ONGLETS = ['toutes', 'active', 'inactive', 'suspendue', 'corbeille'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Bank::class);

        $onglet = in_array($request->input('onglet'), self::ONGLETS, true) ? (string) $request->input('onglet') : 'toutes';
        $search = trim((string) $request->input('search', ''));
        $parPage = min(max((int) $request->input('per_page', 25), 10), 100);

        $banks = Bank::query()
            ->when($onglet === 'corbeille', fn ($q) => $q->onlyTrashed())
            ->when(in_array($onglet, StatutBanque::valeurs(), true), fn ($q) => $q->where('status', $onglet))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('code_etablissement', 'like', "%{$search}%")
                ->orWhere('swift_code', 'like', "%{$search}%")))
            ->withCount('comptes')
            ->orderBy('name')
            ->paginate($parPage)
            ->withQueryString()
            ->through(fn (Bank $b) => [
                'id' => (int) $b->id,
                'name' => (string) $b->name,
                'code_etablissement' => $b->code_etablissement,
                'swift_code' => $b->swift_code,
                'status' => (string) $b->status,
                'comptes_count' => (int) $b->comptes_count,
                'deleted_at' => $b->deleted_at?->toIso8601String(),
            ]);

        $s = DB::table('banks')
            ->selectRaw('SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as toutes')
            ->selectRaw("SUM(CASE WHEN deleted_at IS NULL AND status = 'active' THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN deleted_at IS NULL AND status = 'inactive' THEN 1 ELSE 0 END) as inactive")
            ->selectRaw("SUM(CASE WHEN deleted_at IS NULL AND status = 'suspendue' THEN 1 ELSE 0 END) as suspendue")
            ->selectRaw('SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as corbeille')
            ->first();
        $comptes = DB::table('comptes_bancaires')->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(DISTINCT bank_id) as banques_utilisees')
            ->first();

        return Inertia::render('Banks/Index', [
            'banks' => $banks,
            'stats' => [
                'toutes' => (int) ($s->toutes ?? 0),
                'active' => (int) ($s->active ?? 0),
                'inactive' => (int) ($s->inactive ?? 0),
                'suspendue' => (int) ($s->suspendue ?? 0),
                'corbeille' => (int) ($s->corbeille ?? 0),
                'comptes' => (int) ($comptes->total ?? 0),
                'banques_utilisees' => (int) ($comptes->banques_utilisees ?? 0),
            ],
            'filters' => ['onglet' => $onglet, 'search' => $search, 'per_page' => $parPage],
            'can' => ['manage' => $request->user()->can('create', Bank::class)],
        ]);
    }

    public function store(BankRequest $request): RedirectResponse
    {
        $bank = Bank::query()->create($request->validated());

        return back()->with('success', "Banque « {$bank->name} » ajoutée.");
    }

    /** Les comptes gardent leur banque : le nom est aussi recopié sur chaque compte. */
    public function update(BankRequest $request, Bank $bank): RedirectResponse
    {
        DB::transaction(function () use ($request, $bank) {
            $bank->update($request->validated());
            if ($bank->wasChanged('name')) {
                DB::table('comptes_bancaires')->where('bank_id', $bank->id)->update(['bank_name' => $bank->name, 'updated_at' => now()]);
            }
        });

        return back()->with('success', 'Banque mise à jour.');
    }

    public function statut(Request $request, Bank $bank): RedirectResponse
    {
        Gate::authorize('update', $bank);
        $statut = StatutBanque::from((string) $request->validate(['status' => ['required', Rule::in(StatutBanque::valeurs())]])['status']);
        $bank->update(['status' => $statut->value]);

        return back()->with('success', "{$bank->name} : {$statut->label()}.");
    }

    /** Corbeille refusée tant que des comptes utilisent la banque : la désactiver plutôt. */
    public function destroy(Bank $bank): RedirectResponse
    {
        Gate::authorize('delete', $bank);
        $n = $bank->comptes()->count();
        if ($n > 0) {
            return back()->with('error', "{$bank->name} est utilisée par {$n} compte(s) bancaire(s) : désactivez-la plutôt que de la supprimer.");
        }
        $bank->delete();

        return back()->with('success', "{$bank->name} mise à la corbeille.");
    }

    public function restaurer(int $bank): RedirectResponse
    {
        $modele = Bank::onlyTrashed()->findOrFail($bank);
        Gate::authorize('restore', $modele);
        $modele->restore();

        return back()->with('success', "{$modele->name} restaurée.");
    }
}
