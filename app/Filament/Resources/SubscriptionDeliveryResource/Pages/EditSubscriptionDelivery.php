<?php

namespace App\Filament\Resources\SubscriptionDeliveryResource\Pages;

use App\Filament\Resources\SubscriptionDeliveryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubscriptionDelivery extends EditRecord
{
    protected static string $resource = SubscriptionDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
