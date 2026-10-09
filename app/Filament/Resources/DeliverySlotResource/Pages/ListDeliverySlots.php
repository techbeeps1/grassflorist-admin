<?php

namespace App\Filament\Resources\DeliverySlotResource\Pages;

use App\Filament\Resources\DeliverySlotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliverySlots extends ListRecords
{
    protected static string $resource = DeliverySlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('manage_blocked_dates')
                ->label('Blocked Dates & Holidays')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->url(fn (): string => \App\Filament\Resources\DeliveryBlockedDateResource::getUrl('index')),
            Actions\CreateAction::make(),
        ];
    }
}
