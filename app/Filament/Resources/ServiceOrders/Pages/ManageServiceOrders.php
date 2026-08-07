<?php

namespace App\Filament\Resources\ServiceOrders\Pages;

use App\Filament\Resources\ServiceOrders\ServiceOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageServiceOrders extends ManageRecords
{
    protected static string $resource = ServiceOrderResource::class;

    public function getTitle(): string
    {
        return 'Órdenes y servicios';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo seguimiento'),
        ];
    }
}
