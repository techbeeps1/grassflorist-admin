<?php

namespace App\Filament\Resources\SubscriptionResource\RelationManagers;

use App\Models\SubscriptionDelivery;
use App\Services\Subscriptions\SubscriptionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Scheduled Delivery Cycles';

    protected static ?string $icon = 'heroicon-o-calendar-days';

    public function form(Form $form): Form
    {
        return $form
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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('delivery_number')
            ->columns([
                Tables\Columns\TextColumn::make('delivery_number')
                    ->label('Cycle #')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Scheduled Date')
                    ->date('D, d M Y')
                    ->sortable(),

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
                    ->label('Generated Order')
                    ->badge()
                    ->color('primary')
                    ->url(fn (SubscriptionDelivery $record) => $record->order_id ? route('filament.admin.resources.orders.edit', $record->order_id) : null)
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('scheduled_date', 'asc')
            ->actions([
                // Skip Action
                Action::make('skip')
                    ->label('Skip Cycle')
                    ->icon('heroicon-o-forward')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (SubscriptionDelivery $record) => $record->delivery_status === 'scheduled')
                    ->action(function (SubscriptionDelivery $record, SubscriptionService $service) {
                        try {
                            $service->skipDelivery($record, 'Skipped by admin');
                            Notification::make()->title('Delivery Cycle Skipped')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Skip Failed')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // Reschedule Action
                Action::make('reschedule')
                    ->label('Reschedule')
                    ->icon('heroicon-o-calendar')
                    ->color('primary')
                    ->form([
                        Forms\Components\DatePicker::make('new_date')
                            ->label('New Delivery Date')
                            ->required(),
                        Forms\Components\TextInput::make('new_slot')
                            ->label('New Time Slot')
                            ->placeholder('Morning (09:00 AM - 01:00 PM)'),
                        Forms\Components\TextInput::make('reason')
                            ->label('Reason')
                            ->placeholder('Customer requested change'),
                    ])
                    ->visible(fn (SubscriptionDelivery $record) => in_array($record->delivery_status, ['scheduled', 'rescheduled']))
                    ->action(function (SubscriptionDelivery $record, array $data, SubscriptionService $service) {
                        try {
                            $service->rescheduleDelivery($record, $data['new_date'], $data['new_slot'] ?? null, $data['reason'] ?? 'Admin Rescheduled');
                            Notification::make()->title('Delivery Rescheduled')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Reschedule Failed')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
            ]);
    }
}
