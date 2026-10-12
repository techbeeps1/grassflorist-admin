<?php

namespace App\Filament\Resources\OdooOrderResource\Pages;

use App\Filament\Resources\OdooOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOdooOrders extends ListRecords
{
    protected static string $resource = OdooOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
