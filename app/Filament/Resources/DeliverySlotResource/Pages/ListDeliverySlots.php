<?php

namespace App\Filament\Resources\DeliverySlotResource\Pages;

use App\Filament\Pages\StoreSettings;
use App\Filament\Resources\DeliverySlotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliverySlots extends ListRecords
{
    protected static string $resource = DeliverySlotResource::class;

    public function mount(): void
    {
        redirect()->to(StoreSettings::getUrl(['tab' => 'delivery_slots']));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
