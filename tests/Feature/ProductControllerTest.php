<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_peut_creer_un_produit(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/products', [
                'name' => 'Ordinateur HP',
                'description' => 'Ordinateur portable HP',
                'price' => 500000,
                'stock' => 10,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Produit créé avec succès.',
                'product' => [
                    'name' => 'Ordinateur HP',
                    'description' => 'Ordinateur portable HP',
                    'price' => 500000,
                    'stock' => 10,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'Ordinateur HP',
            'price' => 500000,
            'stock' => 10,
        ]);
    }

    public function test_un_utilisateur_peut_voir_tous_les_produits(): void
    {
        $user = User::factory()->create();

        Product::create([
            'user_id' => $user->id,
            'name' => 'Ordinateur HP',
            'description' => 'Ordinateur portable',
            'price' => 500000,
            'stock' => 10,
        ]);

        Product::create([
            'user_id' => $user->id,
            'name' => 'Souris Logitech',
            'description' => 'Souris sans fil',
            'price' => 15000,
            'stock' => 20,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment([
                'name' => 'Ordinateur HP',
            ])
            ->assertJsonFragment([
                'name' => 'Souris Logitech',
            ]);
    }

    public function test_un_utilisateur_peut_voir_un_produit(): void
    {
        $user = User::factory()->create();

        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Ordinateur HP',
            'description' => 'Ordinateur portable HP',
            'price' => 500000,
            'stock' => 10,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $product->id,
                'name' => 'Ordinateur HP',
                'description' => 'Ordinateur portable HP',
                'price' => 500000,
                'stock' => 10,
            ]);
    }

    public function test_un_utilisateur_peut_modifier_un_produit(): void
    {
        $user = User::factory()->create();

        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Ancien ordinateur',
            'description' => 'Ancienne description',
            'price' => 400000,
            'stock' => 5,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/products/{$product->id}", [
                'name' => 'Nouvel ordinateur',
                'description' => 'Nouvelle description',
                'price' => 600000,
                'stock' => 15,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Produit modifié avec succès.',
                'product' => [
                    'name' => 'Nouvel ordinateur',
                    'description' => 'Nouvelle description',
                    'price' => 600000,
                    'stock' => 15,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Nouvel ordinateur',
            'price' => 600000,
            'stock' => 15,
        ]);
    }

    public function test_un_utilisateur_peut_supprimer_un_produit(): void
    {
        $user = User::factory()->create();

        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Produit à supprimer',
            'description' => 'Produit temporaire',
            'price' => 100000,
            'stock' => 5,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Produit supprimé avec succès.',
            ]);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }
}