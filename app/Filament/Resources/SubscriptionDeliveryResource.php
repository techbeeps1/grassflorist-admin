<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionDeliveryResource\Pages;
use App\Models\SubscriptionDelivery;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionDeliveryResource extends Resource
{
    protected static ?string $model = SubscriptionDelivery::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Delivery Schedules';

    public static function getNavigationBadge(): ?string
    {
        $today = now()->toDateString();
        $count = static::getModel()::where('delivery_status', 'scheduled')
            ->whereDate('scheduled_date', '<=', $today)
            ->count();
        return $count > 0 ? (string)$count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Delivery Cycle Details')
                    ->schema([
                        Forms\Components\DatePicker::make('scheduled_date')
                            ->required(),
                        Forms\Components\TextInput::make('time_slot'),
                        Forms\Components\Select::make('delivery_status')
                            ->options([
                                'scheduled' => 'Scheduled',
                                'processing' => 'Processing',
                                'delivered' => 'Delivered',
                                'skipped' => 'Skipped',
                                'rescheduled' => 'Rescheduled',
                                'failed' => 'Failed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),
                        Forms\Components\Select::make('payment_status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'failed' => 'Failed',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('SAR'),
                        Forms\Components\Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subscription.subscription_number')
                    ->label('Sub #')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(['first_name', 'last_name', 'phone', 'email'])
                    ->description(fn (SubscriptionDelivery $record) => $record->customer?->phone),

                Tables\Columns\TextColumn::make('delivery_number')
                    ->label('Cycle')
                    ->badge(),

                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Scheduled Date')
                    ->date('D, d M Y')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state && $state <= now()->toDateString() ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('time_slot')
                    ->label('Time Slot')
                    ->limit(20),

                Tables\Columns\BadgeColumn::make('delivery_status')
                    ->label('Delivery')
                    ->colors([
                        'success' => 'delivered',
                        'primary' => 'scheduled',
                        'warning' => 'rescheduled',
                        'gray' => 'skipped',
                        'danger' => fn ($state) => in_array($state, ['failed', 'cancelled']),
                        'info' => 'processing',
                    ]),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Payment')
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'pending',
                        'danger' => 'failed',
                    ]),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('SAR'),

                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->badge()
                    ->color('success')
                    ->url(fn (SubscriptionDelivery $record) => $record->order_id ? route('filament.admin.resources.orders.edit', $record->order_id) : null)
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('scheduled_date', 'asc')
            ->filters([
                SelectFilter::make('delivery_status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'processing' => 'Processing',
                        'delivered' => 'Delivered',
                        'skipped' => 'Skipped',
                        'rescheduled' => 'Rescheduled',
                        'failed' => 'Failed',
                    ]),
                SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionDeliveries::route('/'),
            'edit' => Pages\EditSubscriptionDelivery::route('/{record}/edit'),
        ];
    }
}
