<?php

namespace App\Filament\Resources\SubscriptionDeliveryResource\Pages;

use App\Filament\Resources\SubscriptionDeliveryResource;
use App\Models\SubscriptionDelivery;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSubscriptionDeliveries extends ListRecords
{
    protected static string $resource = SubscriptionDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Deliveries')
                ->badge(SubscriptionDelivery::count())
                ->icon('heroicon-o-list-bullet'),

            'processing' => Tab::make('Processing')
                ->badge(SubscriptionDelivery::where('delivery_status', 'processing')->count())
                ->badgeColor('info')
                ->icon('heroicon-o-arrow-path')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('delivery_status', 'processing')),

            'scheduled' => Tab::make('Scheduled')
                ->badge(SubscriptionDelivery::where('delivery_status', 'scheduled')->count())
                ->badgeColor('primary')
                ->icon('heroicon-o-calendar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('delivery_status', 'scheduled')),

            'delivered' => Tab::make('Delivered')
                ->badge(SubscriptionDelivery::where('delivery_status', 'delivered')->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('delivery_status', 'delivered')),

            'failed_cancelled' => Tab::make('Failed / Cancelled')
                ->badge(SubscriptionDelivery::whereIn('delivery_status', ['failed', 'cancelled'])->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('delivery_status', ['failed', 'cancelled'])),

            'skipped_rescheduled' => Tab::make('Skipped / Rescheduled')
                ->badge(SubscriptionDelivery::whereIn('delivery_status', ['skipped', 'rescheduled'])->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-forward')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('delivery_status', ['skipped', 'rescheduled'])),
        ];
    }
}
