<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\User;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $conversations = Conversation::where(function ($query) use ($request) {
            $query->where('user_id', $request->user()->id)
                ->orWhere('admin_id', $request->user()->id);
        })
        ->with(['user', 'admin'])
        ->withCount([
            'messages as unread_count' => function ($query) use ($request) {
                $query->whereNull('read_at')
                    ->where('sender_id', '!=', $request->user()->id);
            }
        ])
        ->latest()
        ->get();

        // Ajouter le statut en ligne
        $conversations->each(function ($conversation) {
            $conversation->user->is_online = $conversation->user->isOnline();
            $conversation->admin->is_online = $conversation->admin->isOnline();
        });

        return response()->json($conversations);
    }

    // Créer une conversation
    public function store(Request $request)
    {
        // Vérifier les données reçues
        $validated = $request->validate([
            'admin_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Vérifier que l'utilisateur choisi est bien un administrateur
        $admin = User::where('id', $validated['admin_id'])
                    ->where('role', 'admin')
                    ->first();

        if (!$admin) {
            return response()->json([
                'message' => 'L’utilisateur choisi n’est pas un administrateur.'
            ], 403);
        }

        // Vérifier si une conversation existe déjà
        $conversation = Conversation::where('user_id', $request->user()->id)
                                  ->where('admin_id', $admin->id)
                                  ->first();

        if ($conversation) {
            return response()->json([
                'message' => 'Cette conversation existe déjà.',
                'conversation' => $conversation
            ], 200);
        }

        // Créer la conversation
        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'admin_id' => $admin->id,
        ]);

        return response()->json([
            'message' => 'Conversation créée avec succès.',
            'conversation' => $conversation
        ], 201);
    }

    // Afficher une conversation avec ses messages
    public function show(Request $request, Conversation $conversation)
    {
        // Vérifier que l'utilisateur appartient à cette conversation
        if (
            $conversation->user_id !== $request->user()->id &&
            $conversation->admin_id !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Vous n’avez pas accès à cette conversation.'
            ], 403);
        }

        // Charger la conversation, ses utilisateurs et ses messages
        $conversation->load([
            'user',
            'admin',
            'messages.sender'
        ]);

        // Ajouter le statut en ligne
        $conversation->user->is_online = $conversation->user->isOnline();
        $conversation->admin->is_online = $conversation->admin->isOnline();

        return response()->json($conversation);
    }

    public function admins()
    {
        $admins = User::where('role', 'admin')
            ->select('id', 'name', 'email', 'role')
            ->get();

        return response()->json($admins);
    }
}