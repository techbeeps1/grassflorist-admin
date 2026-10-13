<?php

namespace App\Filament\Resources\SubscriptionDeliveryResource\Pages;

use App\Filament\Resources\SubscriptionDeliveryResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionDeliveries extends ListRecords
{
    protected static string $resource = SubscriptionDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
