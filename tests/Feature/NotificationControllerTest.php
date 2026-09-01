<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ProductPurchasedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_peut_voir_ses_notifications(): void
    {
        $user = User::factory()->create();

        $user->notify(
            new ProductPurchasedNotification(
                1,
                'Ordinateur HP',
                2
            )
        );

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'notifications',
                'unread_count',
            ]);

        $response->assertJson([
            'unread_count' => 1,
        ]);
    }

    public function test_un_utilisateur_peut_marquer_une_notification_comme_lue(): void
    {
        $user = User::factory()->create();

        $user->notify(
            new ProductPurchasedNotification(
                1,
                'Ordinateur HP',
                2
            )
        );

        $notification = $user->notifications()->first();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Notification marquée comme lue.',
            ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => $notification->fresh()->read_at,
        ]);
    }

    public function test_un_utilisateur_peut_marquer_toutes_les_notifications_comme_lues(): void
    {
        $user = User::factory()->create();

        $user->notify(
            new ProductPurchasedNotification(
                1,
                'Ordinateur HP',
                1
            )
        );

        $user->notify(
            new ProductPurchasedNotification(
                2,
                'Souris HP',
                3
            )
        );

        $this->assertEquals(2, $user->unreadNotifications()->count());

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/notifications/read-all');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Toutes les notifications ont été marquées comme lues.',
            ]);

        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_un_utilisateur_peut_supprimer_une_notification(): void
    {
        $user = User::factory()->create();

        $user->notify(
            new ProductPurchasedNotification(
                1,
                'Ordinateur HP',
                1
            )
        );

        $notification = $user->notifications()->first();

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Notification supprimée.',
            ]);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_un_utilisateur_ne_peut_pas_acceder_aux_notifications_sans_authentification(): void
    {
        $response = $this->getJson('/api/notifications');

        $response->assertStatus(401);
    }
}