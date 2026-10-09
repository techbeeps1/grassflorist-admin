<?php

namespace App\Filament\Resources\DeliveryBlockedDateResource\Pages;

use App\Filament\Resources\DeliveryBlockedDateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryBlockedDate extends EditRecord
{
    protected static string $resource = DeliveryBlockedDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
