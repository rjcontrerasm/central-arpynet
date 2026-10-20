<?php

namespace Tests\Feature;

use Tests\TestCase;

class LiveSessionResponseTest extends TestCase
{
    public function test_live_web_request_returns_json_when_session_is_not_authenticated(): void
    {
        $response = $this
            ->withHeaders([
                'X-Central-Live-Action' => '1',
            ])
            ->get('/mi-dia');

        $response
            ->assertStatus(401)
            ->assertHeader(
                'content-type',
                'application/json',
            )
            ->assertJsonPath(
                'message',
                'Unauthenticated.',
            );
    }

    public function test_regular_web_request_still_redirects_to_login(): void
    {
        $this->get('/mi-dia')
            ->assertRedirect('/login');
    }

    public function test_daily_live_script_has_session_recovery_message(): void
    {
        $script = file_get_contents(
            public_path(
                'central-assets/pages/daily-ops.js',
            ),
        );

        $this->assertStringContainsString(
            'Tu sesión venció.',
            $script,
        );

        $this->assertStringContainsString(
            'error.centralReload = true',
            $script,
        );

        $this->assertStringContainsString(
            "button.hidden = true",
            $script,
        );
    }
}
