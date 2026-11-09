<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ajouter les participants des anciennes conversations
     * dans conversation_user.
     *
     * Cette migration ne supprime aucune donnée.
     */
    public function up(): void
    {
        DB::table('conversations')
            ->orderBy('id')
            ->chunkById(100, function ($conversations) {

                foreach ($conversations as $conversation) {

                    /*
                     * Ajouter le premier participant.
                     *
                     * Il s'agit actuellement du user_id
                     * de l'ancienne structure.
                     */
                    if ($conversation->user_id) {
                        DB::table('conversation_user')
                            ->insertOrIgnore([
                                'conversation_id' => $conversation->id,
                                'user_id' => $conversation->user_id,
                                'deleted_at' => null,
                                'restart_at' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }

                    /*
                     * Ajouter le deuxième participant.
                     *
                     * Il s'agit actuellement de l'admin_id
                     * de l'ancienne structure.
                     */
                    if ($conversation->admin_id) {
                        DB::table('conversation_user')
                            ->insertOrIgnore([
                                'conversation_id' => $conversation->id,
                                'user_id' => $conversation->admin_id,
                                'deleted_at' => null,
                                'restart_at' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }
                }
            });
    }

    /**
     * Cette migration ne supprime aucune conversation.
     *
     * Les données ajoutées dans conversation_user
     * sont conservées afin de protéger les conversations
     * existantes.
     */
    public function down(): void
    {
        /*
         * Rien à supprimer ici volontairement.
         *
         * Les participants peuvent être nécessaires
         * aux conversations existantes.
         */
    }
};