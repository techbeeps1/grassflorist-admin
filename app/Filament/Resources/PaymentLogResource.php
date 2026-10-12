<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentLogResource\Pages;
use App\Models\PaymentLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;

class PaymentLogResource extends Resource
{
    protected static ?string $model = PaymentLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Logs';

    protected static ?string $navigationLabel = 'Payment Logs';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('payment_logs') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('order_number')
                    ->label('Order Number'),
                TextInput::make('gateway')
                    ->label('Payment Gateway'),
                TextInput::make('event_type')
                    ->label('Event Type'),
                TextInput::make('status')
                    ->label('Status'),
                TextInput::make('razorpay_order_id')
                    ->label('Razorpay Order ID'),
                TextInput::make('razorpay_payment_id')
                    ->label('Razorpay Payment ID'),
                TextInput::make('amount')
                    ->label('Amount (INR)'),
                DateTimePicker::make('created_at')
                    ->label('Logged At'),
                Textarea::make('message')
                    ->label('Message / Reason')
                    ->columnSpanFull()
                    ->rows(3),
                Textarea::make('formatted_payload')
                    ->label('Raw Payload (JSON)')
                    ->columnSpanFull()
                    ->rows(10)
                    ->formatStateUsing(fn ($record) => $record && $record->payload ? json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : ''),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('M d, Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('gateway')
                    ->label('Gateway')
                    ->badge()
                    ->colors([
                        'primary' => fn ($state) => in_array($state, ['hyperpay', 'mada', 'applepay', 'stcpay']),
                        'success' => fn ($state) => str_starts_with($state, 'tabby'),
                        'warning' => fn ($state) => str_starts_with($state, 'tamara'),
                        'info' => fn ($state) => str_starts_with($state, 'paypal'),
                        'gray' => fn ($state) => in_array($state, ['cod', 'manual']),
                    ])
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->colors([
                        'info' => fn ($state) => in_array($state, ['callback', 'frontend_verify']),
                        'purple' => 'webhook',
                        'warning' => 'cron_sync',
                        'primary' => 'manual_recovery',
                        'gray' => fn ($state) => in_array($state, ['order_created', 'initiate']),
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'callback' => 'Callback',
                        'webhook' => 'Webhook',
                        'cron_sync' => 'Cron Sync',
                        'manual_recovery' => 'Manual Recover',
                        'order_created', 'initiate' => 'Session Init',
                        'frontend_verify' => 'Verify Return',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function ($state, $record) {
                        if ($record && in_array($record->event_type, ['order_created', 'initiate']) && in_array($state, ['success', 'initiated'])) {
                            return 'info';
                        }
                        return match ($state) {
                            'success', 'paid' => 'success',
                            'initiated' => 'info',
                            'pending' => 'warning',
                            'cancelled' => 'warning',
                            'failed', 'error' => 'danger',
                            'skipped' => 'gray',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state, $record) {
                        if ($record && in_array($record->event_type, ['order_created', 'initiate']) && $state === 'success') {
                            return 'Initiated';
                        }
                        return match ($state) {
                            'initiated' => 'Initiated',
                            'pending' => 'Pending',
                            'success', 'paid' => 'Success',
                            'failed' => 'Failed',
                            'cancelled' => 'Cancelled',
                            'skipped' => 'Skipped',
                            'error' => 'Error',
                            default => ucfirst($state),
                        };
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float)$state, 2) . ' SAR' : '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('razorpay_order_id')
                    ->label('Gateway Checkout ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('razorpay_payment_id')
                    ->label('Transaction Ref')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('message')
                    ->label('Log Message')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->message)
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('gateway')
                    ->label('Gateway')
                    ->options([
                        'hyperpay' => 'HyperPay (Mada / Visa / ApplePay)',
                        'tabby' => 'Tabby',
                        'tamara' => 'Tamara',
                        'paypal' => 'PayPal',
                        'cod' => 'Cash on Delivery',
                    ]),

                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'initiate' => 'Session Initiated',
                        'webhook' => 'Webhook Received',
                        'frontend_verify' => 'Frontend Verification',
                        'callback' => 'Callback',
                        'cron_sync' => 'Cron Sync',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'success' => 'Success (Paid)',
                        'initiated' => 'Initiated',
                        'cancelled' => 'Cancelled',
                        'failed' => 'Failed',
                        'error' => 'Error',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Details'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->isAdmin() ?? false),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentLogs::route('/'),
        ];
    }
}
