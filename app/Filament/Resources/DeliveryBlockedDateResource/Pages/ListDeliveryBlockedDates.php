<?php

namespace App\Filament\Resources\DeliveryBlockedDateResource\Pages;

use App\Filament\Pages\StoreSettings;
use App\Filament\Resources\DeliveryBlockedDateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryBlockedDates extends ListRecords
{
    protected static string $resource = DeliveryBlockedDateResource::class;

    public function mount(): void
    {
        redirect()->to(StoreSettings::getUrl(['tab' => 'blocked_dates']));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
