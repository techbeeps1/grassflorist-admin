<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('settings')
                ->label('Subscription Settings (إعدادات الاشتراكات)')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(url('/admin/subscription-settings')),
        ];
    }
}
