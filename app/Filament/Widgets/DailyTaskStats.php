<?php

namespace App\Filament\Widgets;

use App\Support\DailyDashboard;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DailyTaskStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public function getStats(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $metrics = app(DailyDashboard::class)->metrics($user);

        return [
            Stat::make('Vencidas', $metrics['overdue'])
                ->description('Requieren regularización')
                ->color($metrics['overdue'] > 0 ? 'danger' : 'success'),
            Stat::make('Para hoy', $metrics['today'])
                ->description('Vencen durante el día')
                ->color($metrics['today'] > 0 ? 'warning' : 'success'),
            Stat::make('Críticas', $metrics['critical'])
                ->description('Prioridad de 85 a 100')
                ->color($metrics['critical'] > 0 ? 'danger' : 'success'),
            Stat::make('En espera', $metrics['waiting'])
                ->description('Dependen de terceros')
                ->color('info'),
            Stat::make('Abiertas', $metrics['open'])
                ->description('Total pendiente')
                ->color('gray'),
        ];
    }
}
