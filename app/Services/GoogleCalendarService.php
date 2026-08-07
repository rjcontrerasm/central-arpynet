<?php

namespace App\Services;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use RuntimeException;

class GoogleCalendarService
{
    public const SCOPES = [
        GoogleCalendar::CALENDAR_EVENTS,
    ];

    public function client(): GoogleClient
    {
        $clientId = config('services.google_calendar.client_id');
        $clientSecret = config('services.google_calendar.client_secret');
        $redirectUri = config('services.google_calendar.redirect_uri');

        if (! $clientId || ! $clientSecret || ! $redirectUri) {
            throw new RuntimeException(
                'Google Calendar todavía no está configurado en .env.',
            );
        }

        $client = new GoogleClient();

        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setScopes(self::SCOPES);
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        $client->setPrompt('consent');

        return $client;
    }

    public function authorizationUrl(): string
    {
        return $this->client()->createAuthUrl();
    }

    public function exchangeCode(
        User $user,
        string $code,
    ): GoogleCalendarConnection {
        $client = $this->client();

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException(
                $token['error_description']
                    ?? $token['error']
                    ?? 'Google rechazó la autorización.',
            );
        }

        $existing = $user->googleCalendarConnection;

        $existingRefreshToken = $existing
            ?->token_data['refresh_token'] ?? null;

        if (
            empty($token['refresh_token'])
            && filled($existingRefreshToken)
        ) {
            $token['refresh_token'] = $existingRefreshToken;
        }

        $client->setAccessToken($token);

        $connection = GoogleCalendarConnection::query()
            ->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'calendar_id' => 'primary',
                    'calendar_summary' => 'Calendario principal',
                    'token_data' => $token,
                    'scopes' => self::SCOPES,
                    'connected_at' => now(),
                    'token_expires_at' => isset($token['expires_in'])
                        ? now()->addSeconds((int) $token['expires_in'])
                        : null,
                    'last_error_at' => null,
                    'last_error' => null,
                ],
            );

        return $connection->fresh();
    }

    public function authenticatedClient(
        GoogleCalendarConnection $connection,
    ): GoogleClient {
        if (! $connection->isConnected()) {
            throw new RuntimeException(
                'La cuenta de Google Calendar no está conectada.',
            );
        }

        $client = $this->client();
        $client->setAccessToken($connection->token_data);

        if ($client->isAccessTokenExpired()) {
            $refreshToken =
                $connection->token_data['refresh_token'] ?? null;

            if (! $refreshToken) {
                throw new RuntimeException(
                    'No existe refresh token. Reconecta Google Calendar.',
                );
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken(
                $refreshToken,
            );

            if (isset($newToken['error'])) {
                throw new RuntimeException(
                    $newToken['error_description']
                        ?? $newToken['error']
                        ?? 'No se pudo renovar el acceso a Google.',
                );
            }

            $newToken['refresh_token'] = $refreshToken;

            $connection->forceFill([
                'token_data' => $newToken,
                'token_expires_at' => isset($newToken['expires_in'])
                    ? now()->addSeconds(
                        (int) $newToken['expires_in'],
                    )
                    : null,
                'last_error_at' => null,
                'last_error' => null,
            ])->save();

            $client->setAccessToken($newToken);
        }

        return $client;
    }

    public function disconnect(User $user): void
    {
        $connection = $user->googleCalendarConnection;

        if (! $connection) {
            return;
        }

        try {
            if ($connection->isConnected()) {
                $this->authenticatedClient($connection)
                    ->revokeToken();
            }
        } catch (\Throwable) {
            // La desconexión local no debe fallar si Google no responde.
        }

        $connection->delete();
    }
}
