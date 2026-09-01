<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_peut_s_inscrire(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Japhet',
            'email' => 'japhet@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Utilisateur créé avec succès.',
            ])
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Japhet',
            'email' => 'japhet@example.com',
        ]);
    }

    public function test_un_utilisateur_peut_se_connecter(): void
    {
        $user = User::factory()->create([
            'email' => 'japhet@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'japhet@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Connexion réussie.',
            ])
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);
    }

    public function test_un_utilisateur_ne_peut_pas_se_connecter_avec_un_mauvais_mot_de_passe(): void
    {
        User::factory()->create([
            'email' => 'japhet@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'japhet@example.com',
            'password' => 'mauvaismotdepasse',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_un_utilisateur_peut_se_deconnecter(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Déconnexion réussie.',
            ]);
    }
}
