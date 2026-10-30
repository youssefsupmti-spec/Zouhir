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
});
