<?php

namespace App\Filament\Resources\DeliveryBlockedDateResource\Pages;

use App\Filament\Resources\DeliveryBlockedDateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryBlockedDates extends ListRecords
{
    protected static string $resource = DeliveryBlockedDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
