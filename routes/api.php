<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'last.seen'])->group(function () {

    // Déconnexion
    Route::post('/logout', [AuthController::class, 'logout']);

    // =========================
    // PRODUITS
    // =========================

    // Produits accessibles aux utilisateurs connectés
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    // Produits réservés à l'administrateur
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

    // Liste des conversations de l'utilisateur connecté
    Route::get('/conversations', [ConversationController::class, 'index']);

    // Créer une conversation
    Route::post('/conversations', [ConversationController::class, 'store']);

    // Afficher une conversation
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);

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
});