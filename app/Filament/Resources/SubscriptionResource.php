<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionService;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Subscriptions';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'active')->count();
        return $count > 0 ? (string)$count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    // Main Information (2 columns)
                    Section::make('Subscription Overview')
                        ->schema([
                            TextInput::make('subscription_number')
                                ->label('Subscription #')
                                ->disabled()
                                ->dehydrated(false),

                            Select::make('customer_id')
                                ->label('Customer')
                                ->relationship('customer', 'username')
                                ->getOptionLabelFromRecordUsing(fn (Customer $record) => "{$record->name} ({$record->email})")
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('product_id')
                                ->label('Product')
                                ->relationship('product', 'sku')
                                ->getOptionLabelFromRecordUsing(fn (Product $record) => format_translatable($record->name, 'en') ?: $record->sku)
                                ->searchable()
                                ->preload()
                                ->required(),

                            TextInput::make('quantity')
                                ->label('Quantity per Delivery')
                                ->numeric()
                                ->minValue(1)
                                ->required(),

                            Select::make('frequency')
                                ->label('Frequency')
                                ->options([
                                    'weekly' => 'Every Week (7 days)',
                                    'biweekly' => 'Every 2 Weeks (14 days)',
                                    'monthly' => 'Every Month (30 days)',
                                    'custom' => 'Custom Dates',
                                ])
                                ->required(),

                            Select::make('duration_type')
                                ->label('Plan Duration')
                                ->options([
                                    '1_month' => '1 Month',
                                    '3_months' => '3 Months',
                                    '6_months' => '6 Months',
                                    '12_months' => '12 Months',
                                    'until_cancelled' => 'Until Cancelled (Ongoing)',
                                    'custom' => 'Custom Plan',
                                ])
                                ->required(),

                            Forms\Components\DatePicker::make('start_date')
                                ->label('Start Date')
                                ->required(),

                            Forms\Components\DatePicker::make('next_delivery_date')
                                ->label('Next Delivery Date'),

                            TextInput::make('delivery_time_slot')
                                ->label('Time Slot')
                                ->placeholder('Morning (09:00 AM - 01:00 PM)'),

                            Textarea::make('gift_message')
                                ->label('Gift Card Message')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->columnSpan(2),

                    // Status & Financial Snapshot (1 column)
                    Grid::make(1)->schema([
                        Section::make('Status & Lifecycle')
                            ->schema([
                                Select::make('status')
                                    ->label('Subscription Status')
                                    ->options([
                                        'active' => 'Active',
                                        'paused' => 'Paused',
                                        'cancelled' => 'Cancelled',
                                        'completed' => 'Completed',
                                        'pending_payment' => 'Pending Payment',
                                    ])
                                    ->required(),

                                Forms\Components\DatePicker::make('pause_start_date')
                                    ->label('Paused On')
                                    ->visible(fn (Forms\Get $get) => $get('status') === 'paused'),

                                Textarea::make('cancellation_reason')
                                    ->label('Cancellation Reason')
                                    ->visible(fn (Forms\Get $get) => $get('status') === 'cancelled')
                                    ->rows(2),
                            ]),

                        Section::make('Pricing & Per-Delivery Financials')
                            ->schema([
                                TextInput::make('unit_price')
                                    ->label('Base Unit Price')
                                    ->numeric()
                                    ->prefix('SAR'),

                                TextInput::make('discount_percentage')
                                    ->label('Discount %')
                                    ->numeric()
                                    ->suffix('%'),

                                TextInput::make('discounted_price')
                                    ->label('Discounted Unit Price')
                                    ->numeric()
                                    ->prefix('SAR'),

                                TextInput::make('delivery_fee')
                                    ->label('Delivery Fee')
                                    ->numeric()
                                    ->prefix('SAR'),

                                TextInput::make('tax_amount')
                                    ->label('VAT Amount (15%)')
                                    ->numeric()
                                    ->prefix('SAR'),

                                TextInput::make('total_per_delivery')
                                    ->label('Total Per Delivery')
                                    ->numeric()
                                    ->prefix('SAR')
                                    ->extraInputAttributes(['class' => 'font-bold text-success-600']),
                            ]),

                        Section::make('Payment Tokenization')
                            ->schema([
                                TextInput::make('payment_gateway')
                                    ->label('Gateway')
                                    ->disabled(),

                                TextInput::make('payment_token')
                                    ->label('Saved Token / Registration ID')
                                    ->password()
                                    ->revealable()
                                    ->disabled(),

                                TextInput::make('consecutive_failed_payments')
                                    ->label('Consecutive Failures')
                                    ->numeric()
                                    ->disabled(),
                            ]),
                    ])->columnSpan(1),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subscription_number')
                    ->label('Sub #')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(['first_name', 'last_name', 'username', 'email'])
                    ->description(fn (Subscription $record) => $record->customer?->phone ?? $record->customer?->email),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->formatStateUsing(fn ($state, Subscription $record) => format_translatable($record->product?->name, 'en') ?: $record->product?->sku)
                    ->limit(25),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'paused',
                        'danger' => 'cancelled',
                        'secondary' => 'completed',
                        'gray' => 'pending_payment',
                    ]),

                Tables\Columns\TextColumn::make('frequency')
                    ->label('Frequency')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'weekly' => 'Every Week',
                        'biweekly' => 'Every 2 Weeks',
                        'monthly' => 'Every Month',
                        default => ucfirst($state),
                    })
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('total_per_delivery')
                    ->label('Per Delivery')
                    ->money('SAR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('next_delivery_date')
                    ->label('Next Delivery')
                    ->date('d M Y')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state && $state <= now()->toDateString() ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('deliveries_count')
                    ->counts('deliveries')
                    ->label('Cycles')
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                        'pending_payment' => 'Pending Payment',
                    ]),
                SelectFilter::make('frequency')
                    ->options([
                        'weekly' => 'Weekly',
                        'biweekly' => 'Bi-weekly',
                        'monthly' => 'Monthly',
                        'custom' => 'Custom',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),

                // Action to manually trigger next order generation
                Action::make('trigger_delivery')
                    ->label('Generate Order Now')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Trigger Immediate Delivery Order')
                    ->modalDescription('This will generate a formal Order and charge/process the recurring payment for the earliest scheduled delivery cycle.')
                    ->modalSubmitActionLabel('Yes, Generate Order')
                    ->modalCancelActionLabel('Cancel')
                    ->modalIcon('heroicon-o-truck')
                    ->visible(fn (Subscription $record) => $record->status === 'active')
                    ->action(function (Subscription $record, SubscriptionService $service) {
                        $delivery = $record->deliveries()
                            ->where('delivery_status', 'scheduled')
                            ->orderBy('scheduled_date', 'asc')
                            ->first();

                        if (!$delivery) {
                            Notification::make()
                                ->title('No scheduled deliveries')
                                ->body('There are no pending scheduled delivery cycles remaining for this subscription.')
                                ->warning()
                                ->send();
                            return;
                        }

                        try {
                            $order = $service->generateOrderForDelivery($delivery, true);
                            $record->refresh();
                            $orderUrl = route('filament.admin.resources.orders.edit', $order->id);

                            if ($order->payment_status === 'paid') {
                                Notification::make()
                                    ->title('Delivery Order Generated & Paid')
                                    ->body("Order #{$order->order_number} successfully created for delivery #{$delivery->delivery_number}.")
                                    ->success()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('view_order')
                                            ->label('View Order')
                                            ->url($orderUrl)
                                            ->openUrlInNewTab(),
                                    ])
                                    ->send();
                            } else {
                                $reason = $order->failed_order_reason ? "({$order->failed_order_reason})" : "(Payment Pending)";
                                Notification::make()
                                    ->title('Delivery Order Generated')
                                    ->body("Order #{$order->order_number} created for delivery #{$delivery->delivery_number}. {$reason}")
                                    ->warning()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('view_order')
                                            ->label('View Order')
                                            ->url($orderUrl)
                                            ->openUrlInNewTab(),
                                    ])
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Order Generation Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Pause Action
                Action::make('pause')
                    ->label('Pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Subscription $record) => $record->status === 'active')
                    ->action(function (Subscription $record, SubscriptionService $service) {
                        $service->pauseSubscription($record, 'Paused from admin panel');
                        Notification::make()->title('Subscription Paused')->success()->send();
                    }),

                // Resume Action
                Action::make('resume')
                    ->label('Resume')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Subscription $record) => $record->status === 'paused')
                    ->action(function (Subscription $record, SubscriptionService $service) {
                        $service->resumeSubscription($record);
                        Notification::make()->title('Subscription Resumed')->success()->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DeliveriesRelationManager::class,
            RelationManagers\OrdersRelationManager::class,
            RelationManagers\ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
