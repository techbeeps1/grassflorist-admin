<?php

namespace App\Filament\Resources\SubscriptionResource\RelationManagers;

use App\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Generated Recurring Orders';

    protected static ?string $icon = 'heroicon-o-shopping-bag';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->badge()
                    ->color('primary')
                    ->url(fn (Order $record) => route('filament.admin.resources.orders.edit', $record->id))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('ordered_at')
                    ->label('Ordered Date')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_date')
                    ->label('Delivery Date')
                    ->date('d M Y'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Order Status')
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'pending',
                        'info' => 'processing',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Payment')
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'payment_pending',
                        'danger' => 'failed',
                    ]),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('SAR'),
            ])
            ->defaultSort('id', 'desc');
    }
}
