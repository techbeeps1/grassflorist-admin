<?php

namespace App\Filament\Resources\ShippingMethodResource\Pages;

use App\Filament\Pages\StoreSettings;
use App\Filament\Resources\ShippingMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShippingMethods extends ListRecords
{
    protected static string $resource = ShippingMethodResource::class;

    public function mount(): void
    {
        redirect()->to(StoreSettings::getUrl(['tab' => 'shipping_methods']));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Shipping Method'),
        ];
    }
}
