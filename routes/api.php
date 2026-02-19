<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccueilController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TypeCategorieController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\GammeController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\ProduitSportController;
use App\Http\Controllers\SportController;
use App\Http\Controllers\PanierController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\LivreurController;
use App\Http\Controllers\VendeurController;
use App\Http\Controllers\TemoignageController;
use App\Http\Controllers\SmsController;

// ─── Auth ─────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

// ─── Routes publiques ─────────────────────────────────────────
Route::get('/accueil',                [AccueilController::class, 'index']);
Route::get('/accueil/search',         [AccueilController::class, 'search']);
Route::get('/accueil/categorie/{id}', [AccueilController::class, 'filterByCategorie']);
Route::get('/accueil/gamme/{id}',     [AccueilController::class, 'filterByGamme']);

Route::get('/sport',       [SportController::class, 'index']);
Route::get('/temoignages', [TemoignageController::class, 'showPublic']);

Route::apiResource('typecategories', TypeCategorieController::class)->only(['index', 'show']);
Route::apiResource('categories',     CategorieController::class)->only(['index', 'show']);
Route::apiResource('gammes',         GammeController::class)->only(['index', 'show']);
Route::apiResource('produits',       ProduitController::class)->only(['index', 'show']);
Route::apiResource('produits-sport', ProduitSportController::class)->only(['index', 'show']);
Route::get('/produits-sport/{id}/medias', [ProduitSportController::class, 'getMedias']);
Route::apiResource('boutiques', BoutiqueController::class)->only(['index', 'show']);

// ─── Checkout public (guests + connectés) ────────────────────
Route::post('/checkout',                            [CheckoutController::class, 'process']);
Route::get('/checkout/confirmation/{orderNumber}',  [CheckoutController::class, 'confirmation']);
Route::get('/checkout/status/{orderNumber}',        [CheckoutController::class, 'checkOrderStatus']);
Route::get('/checkout/whatsapp/{orderNumber}',      [CheckoutController::class, 'getWhatsAppMessage']);

// ─── Authentifié ──────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // PDF (protégé car téléchargement sensible)
    Route::get('/checkout/pdf/{orderNumber}', [CheckoutController::class, 'generatePDF']);

    // Panier — routes fixes AVANT les routes avec {id}
    Route::get('/panier/count',  [PanierController::class, 'count']);
    Route::post('/panier/vider', [PanierController::class, 'viderPanier']);
    Route::get('/panier',        [PanierController::class, 'index']);
    Route::post('/panier',       [PanierController::class, 'store']);
    Route::put('/panier/{id}',   [PanierController::class, 'update']);
    Route::delete('/panier/{id}',[PanierController::class, 'destroy']);

    // SMS
    Route::post('/sms/send',   [SmsController::class, 'sendSms']);
    Route::get('/sms/balance', [SmsController::class, 'getSmsBalance']);

    // ─── Admin ────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Catalogue
        Route::apiResource('typecategories', TypeCategorieController::class)->except(['index', 'show']);
        Route::apiResource('categories',     CategorieController::class)->except(['index', 'show']);
        Route::apiResource('gammes',         GammeController::class)->except(['index', 'show']);
        Route::apiResource('produits',       ProduitController::class)->except(['index', 'show']);
        Route::apiResource('produits-sport', ProduitSportController::class)->except(['index', 'show']);

        // Commandes — routes fixes AVANT apiResource
        Route::get('/commandes/export/csv', [CommandeController::class, 'export']);
        Route::get('/commandes/statistics', [CommandeController::class, 'statistics']);
        Route::apiResource('commandes', CommandeController::class)->except(['store', 'create', 'edit']);

        // Clients
        Route::patch('/clients/{client}/verify-email', [ClientController::class, 'verifyEmail']);
        Route::get('/clients/{client}/stats',          [ClientController::class, 'stats']);
        Route::apiResource('clients', ClientController::class)->except(['create', 'edit']);

        // Personnel
        Route::patch('/vendeurs/{id}/change-role', [VendeurController::class, 'changeRole']);
        Route::apiResource('livreurs',  LivreurController::class)->except(['show', 'create', 'edit']);
        Route::apiResource('vendeurs',  VendeurController::class)->except(['show', 'create', 'edit']);
        Route::apiResource('boutiques', BoutiqueController::class)->except(['show', 'create', 'edit']);

        // Témoignages
        Route::apiResource('temoignages', TemoignageController::class)->except(['show', 'create', 'edit']);
    });
});
