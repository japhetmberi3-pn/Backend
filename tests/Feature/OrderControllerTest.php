<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_peut_acheter_un_produit(): void
    {
        $user = User::factory()->create();

        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Ordinateur HP',
            'description' => 'Ordinateur portable',
            'price' => 500000,
            'stock' => 10,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Achat effectué avec succès.',
                'order' => [
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'status' => 'completed',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 8,
        ]);
    }

    public function test_un_utilisateur_ne_peut_pas_acheter_si_le_stock_est_insuffisant(): void
    {
        $user = User::factory()->create();

        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Ordinateur HP',
            'description' => 'Ordinateur portable',
            'price' => 500000,
            'stock' => 2,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'product_id' => $product->id,
                'quantity' => 5,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 2,
        ]);
    }

    public function test_un_utilisateur_non_authentifie_ne_peut_pas_acheter(): void
    {
        $response = $this->postJson('/api/orders', [
            'product_id' => 1,
            'quantity' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_une_commande_possede_un_utilisateur_et_un_produit(): void
    {
        $user = User::factory()->create();

        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Ordinateur HP',
            'description' => 'Ordinateur portable',
            'price' => 500000,
            'stock' => 10,
        ]);

        $order = \App\Models\Order::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'status' => 'completed',
        ]);

        $this->assertTrue($order->user->is($user));
        $this->assertTrue($order->product->is($product));
    }

}