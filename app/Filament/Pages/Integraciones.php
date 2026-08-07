<?php

namespace App\Filament\Pages;

use App\Models\GoogleCalendarConnection;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Integraciones extends Page
{
    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedLink;

    protected static ?string $navigationLabel = 'Integraciones';

    protected static ?string $title = 'Integraciones';

    protected static ?string $slug = 'integraciones';

    protected static ?int $navigationSort = 80;

    protected string $view =
        'filament.pages.integraciones';

    public function getViewData(): array
    {
        $connection = auth()->user()
            ?->googleCalendarConnection;

        return [
            'connection' => $connection,
            'configured' =>
                filled(config(
                    'services.google_calendar.client_id',
                ))
                && filled(config(
                    'services.google_calendar.client_secret',
                )),
        ];
    }
}
