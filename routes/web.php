<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Ouais Express (Client: Zouhir)
|--------------------------------------------------------------------------
| Phase 1: Landing Page, Authentification & Inscription uniquement.
*/

// Vitrine & Outils publics
Route::get('/', [LandingController::class, 'index'])->name('home');
Route::post('/api/tarification/calculate', [LandingController::class, 'calculateRate'])->name('tarification.calculate');
Route::post('/api/tracking/search', [LandingController::class, 'trackParcel'])->name('tracking.search');

// Authentification Invités (Redirige vers /dashboard si déjà connecté)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Espace Membres Authentifiés (Redirige vers /login si non connecté)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // 5 Référentiels Métier & Pages de Gestion (Sprint 1 & Sprint 2 CRUD)
    Route::get('/businesses', [\App\Http\Controllers\BusinessController::class, 'index'])->name('businesses.index');
    Route::post('/businesses', [\App\Http\Controllers\BusinessController::class, 'store'])->name('businesses.store');
    Route::put('/businesses/{business}', [\App\Http\Controllers\BusinessController::class, 'update'])->name('businesses.update');
    Route::delete('/businesses/{business}', [\App\Http\Controllers\BusinessController::class, 'destroy'])->name('businesses.destroy');

    Route::get('/clients', [\App\Http\Controllers\ClientController::class, 'index'])->name('clients.index');
    Route::post('/clients', [\App\Http\Controllers\ClientController::class, 'store'])->name('clients.store');
    Route::put('/clients/{client}', [\App\Http\Controllers\ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [\App\Http\Controllers\ClientController::class, 'destroy'])->name('clients.destroy');

    Route::get('/livreurs', [\App\Http\Controllers\LivreurController::class, 'index'])->name('livreurs.index');
    Route::post('/livreurs', [\App\Http\Controllers\LivreurController::class, 'store'])->name('livreurs.store');
    Route::put('/livreurs/{livreur}', [\App\Http\Controllers\LivreurController::class, 'update'])->name('livreurs.update');
    Route::delete('/livreurs/{livreur}', [\App\Http\Controllers\LivreurController::class, 'destroy'])->name('livreurs.destroy');

    Route::get('/villes', [\App\Http\Controllers\VilleController::class, 'index'])->name('villes.index');
    Route::post('/villes', [\App\Http\Controllers\VilleController::class, 'store'])->name('villes.store');
    Route::put('/villes/{ville}', [\App\Http\Controllers\VilleController::class, 'update'])->name('villes.update');
    Route::delete('/villes/{ville}', [\App\Http\Controllers\VilleController::class, 'destroy'])->name('villes.destroy');

    Route::get('/banks', [\App\Http\Controllers\BankController::class, 'index'])->name('banks.index');
    Route::post('/banks/comptes', [\App\Http\Controllers\BankController::class, 'storeCompte'])->name('banks.comptes.store');
    Route::put('/banks/comptes/{compte}', [\App\Http\Controllers\BankController::class, 'updateCompte'])->name('banks.comptes.update');
    Route::delete('/banks/comptes/{compte}', [\App\Http\Controllers\BankController::class, 'destroyCompte'])->name('banks.comptes.destroy');

    // Colis & Expéditions (Sprint 3)
    Route::get('/colis', [\App\Http\Controllers\ColisController::class, 'index'])->name('colis.index');
    Route::post('/colis', [\App\Http\Controllers\ColisController::class, 'store'])->name('colis.store');
    Route::put('/colis/{coli}', [\App\Http\Controllers\ColisController::class, 'update'])->name('colis.update');
    Route::patch('/colis/{coli}/state', [\App\Http\Controllers\ColisController::class, 'updateState'])->name('colis.state');
    Route::delete('/colis/{coli}', [\App\Http\Controllers\ColisController::class, 'destroy'])->name('colis.destroy');

    // Administration & Sécurité : rôles et permissions Spatie (Sprint 25).
    // Autorisation dans les contrôleurs / FormRequests (RolePolicy, PermissionPolicy).
    Route::get('/roles', [\App\Http\Controllers\RoleController::class, 'index'])->name('roles.index');
    Route::post('/roles', [\App\Http\Controllers\RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}', [\App\Http\Controllers\RoleController::class, 'show'])->name('roles.show');
    Route::put('/roles/{role}', [\App\Http\Controllers\RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [\App\Http\Controllers\RoleController::class, 'destroy'])->name('roles.destroy');
    Route::post('/roles/{role}/duplicate', [\App\Http\Controllers\RoleController::class, 'duplicate'])->name('roles.duplicate');
    Route::post('/roles/{role}/users', [\App\Http\Controllers\RoleController::class, 'addMember'])->name('roles.users.store');
    Route::delete('/roles/{role}/users/{user}', [\App\Http\Controllers\RoleController::class, 'removeMember'])->name('roles.users.destroy');

    Route::get('/permissions', [\App\Http\Controllers\PermissionController::class, 'index'])->name('permissions.index');
    Route::post('/permissions/sync', [\App\Http\Controllers\PermissionController::class, 'synchronize'])->name('permissions.sync');
    Route::delete('/permissions/{permission}', [\App\Http\Controllers\PermissionController::class, 'destroy'])->name('permissions.destroy');
});
