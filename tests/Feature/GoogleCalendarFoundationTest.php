<?php

namespace Tests\Feature;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCalendarFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_connection_tokens_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $connection = GoogleCalendarConnection::query()->create([
            'user_id' => $user->id,
            'calendar_id' => 'primary',
            'token_data' => [
                'access_token' => 'token-de-prueba',
                'refresh_token' => 'refresh-de-prueba',
            ],
            'scopes' => [
                'https://www.googleapis.com/auth/calendar.events',
            ],
            'connected_at' => now(),
        ]);

        $raw = $connection->getRawOriginal('token_data');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString(
            'token-de-prueba',
            $raw,
        );

        $this->assertSame(
            'token-de-prueba',
            $connection->fresh()
                ->token_data['access_token'],
        );
    }

    public function test_google_calendar_routes_require_authentication(): void
    {
        $this->get('/google-calendar/connect')
            ->assertRedirect('/login');

        $this->post('/google-calendar/disconnect')
            ->assertRedirect('/login');

        $this->get('/login')
            ->assertRedirect('/admin/login');
    }
}
