<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ShopController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'last.seen'])->group(function () {

    // Déconnexion
    Route::post('/logout', [AuthController::class, 'logout']);

    // =========================
    // PRODUITS
    // =========================

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    Route::middleware('admin')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    });

    // =========================
    // COMMANDES
    // =========================

    Route::post('/orders', [OrderController::class, 'store']);

    // =========================
    // NOTIFICATIONS
    // =========================

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // =========================
    // CONVERSATIONS
    // =========================

    // Liste des administrateurs disponibles
    Route::get('/admins', [ConversationController::class, 'admins']);

    // Liste des conversations
    Route::get('/conversations', [ConversationController::class, 'index']);

    // Créer une conversation
    Route::post('/conversations', [ConversationController::class, 'store']);

    // Afficher une conversation
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);

    // Marquer toute une conversation comme lue
    Route::patch(
        '/conversations/{conversation}/read',
        [MessageController::class, 'markConversationAsRead']
    );

    // Supprimer toute une conversation
    Route::delete(
        '/conversations/{conversation}',
        [ConversationController::class, 'destroy']
    );

    // =========================
    // MESSAGES
    // =========================

    // Liste des messages
    Route::get(
        '/conversations/{conversation}/messages',
        [MessageController::class, 'index']
    );

    // Envoyer un message
    Route::post(
        '/conversations/{conversation}/messages',
        [MessageController::class, 'store']
    );

    // Marquer un message comme lu
    Route::patch(
        '/messages/{message}/read',
        [MessageController::class, 'markAsRead']
    );

    // Supprimer un message
    Route::delete(
        '/messages/{message}',
        [MessageController::class, 'destroy']
    );

    // =========================
    // PANIER
    // =========================

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::patch('/cart/items/{item}', [CartController::class, 'update']);
    Route::delete('/cart/items/{item}', [CartController::class, 'destroy']);

    // =========================
    // BOUTIQUES
    // =========================

    // Voir toutes les boutiques
    Route::get('/shops', [ShopController::class, 'all']);

    // Voir ma boutique
    Route::get('/shop', [ShopController::class, 'index']);

    // Créer ma boutique
    Route::post('/shop', [ShopController::class, 'store']);
});