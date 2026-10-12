<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentGatewayResource\Pages;
use App\Models\PaymentGateway;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentGatewayResource extends Resource
{
    protected static ?string $model = PaymentGateway::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Payment Gateways';
    protected static ?string $modelLabel = 'Payment Gateway';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('payment_gateways') ?? true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Gateway Identity & Status')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label('System Code')
                                    ->disabled()
                                    ->required(),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active on Checkout')
                                    ->helperText('Enable or disable this payment method for customers.')
                                    ->default(true),

                                Forms\Components\Toggle::make('is_sandbox')
                                    ->label('Test / Sandbox Mode')
                                    ->helperText('When enabled, test cards & test API will be used.')
                                    ->default(false),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name_en')
                                    ->label('Display Name (English)')
                                    ->required(),

                                Forms\Components\TextInput::make('name_ar')
                                    ->label('Display Name (Arabic)')
                                    ->required(),

                                Forms\Components\Textarea::make('description_en')
                                    ->label('Description (English)')
                                    ->rows(2),

                                Forms\Components\Textarea::make('description_ar')
                                    ->label('Description (Arabic)')
                                    ->rows(2),
                            ]),
                    ]),

                Forms\Components\Section::make('API Keys & Credentials')
                    ->description('Enter live or sandbox credentials for this payment gateway.')
                    ->schema([
                        // 1. HyperPay Credentials
                        Forms\Components\Grid::make(2)
                            ->visible(fn (?PaymentGateway $record) => $record?->code === 'hyperpay')
                            ->schema([
                                Forms\Components\TextInput::make('credentials.access_token')
                                    ->label('Access Token (Bearer Secret)')
                                    ->password()
                                    ->revealable()
                                    ->helperText('Bearer access token from HyperPay Merchant Portal.')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('credentials.entity_id_mada')
                                    ->label('Entity ID - Mada')
                                    ->helperText('Entity ID specifically for Saudi Mada cards.')
                                    ->placeholder('8a8294174b7e...'),

                                Forms\Components\TextInput::make('credentials.entity_id_visa_master')
                                    ->label('Entity ID - Visa / Mastercard')
                                    ->helperText('Entity ID for standard Credit & Debit cards.')
                                    ->placeholder('8a8294184b7e...'),

                                Forms\Components\TextInput::make('credentials.entity_id_applepay')
                                    ->label('Entity ID - Apple Pay')
                                    ->helperText('Entity ID for Apple Pay Web processing.')
                                    ->placeholder('8a8294194b7e...'),

                                Forms\Components\TextInput::make('credentials.entity_id_stcpay')
                                    ->label('Entity ID - STC Pay')
                                    ->helperText('Optional Entity ID for STC Pay mobile wallet.')
                                    ->placeholder('8a82941a4b7e...'),
                            ]),

                        // 2. Tabby Credentials
                        Forms\Components\Grid::make(2)
                            ->visible(fn (?PaymentGateway $record) => $record?->code === 'tabby')
                            ->schema([
                                Forms\Components\TextInput::make('credentials.public_key')
                                    ->label('Tabby Public Key (pk_...)')
                                    ->placeholder('pk_test_... or pk_live_...')
                                    ->helperText('Used for frontend installment promo snippets.'),

                                Forms\Components\TextInput::make('credentials.merchant_code')
                                    ->label('Merchant Code')
                                    ->placeholder('grassflorist')
                                    ->helperText('Your registered merchant identifier in Tabby.'),

                                Forms\Components\TextInput::make('credentials.secret_key')
                                    ->label('Tabby Secret Key (sk_...)')
                                    ->password()
                                    ->revealable()
                                    ->placeholder('sk_test_... or sk_live_...')
                                    ->helperText('Used strictly on backend server-to-server calls.')
                                    ->columnSpanFull(),
                            ]),

                        // 3. Tamara Credentials
                        Forms\Components\Grid::make(2)
                            ->visible(fn (?PaymentGateway $record) => $record?->code === 'tamara')
                            ->schema([
                                Forms\Components\TextInput::make('credentials.api_token')
                                    ->label('Tamara API Token')
                                    ->password()
                                    ->revealable()
                                    ->helperText('Bearer API token from Tamara Partners Portal.')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('credentials.notification_token')
                                    ->label('Webhook / Notification Key')
                                    ->password()
                                    ->revealable()
                                    ->helperText('Secret key used to verify Tamara webhook events.'),

                                Forms\Components\TextInput::make('credentials.public_key')
                                    ->label('Public Key')
                                    ->helperText('Public key for Tamara promotional widgets.'),
                            ]),

                        // 4. PayPal Credentials
                        Forms\Components\Grid::make(2)
                            ->visible(fn (?PaymentGateway $record) => $record?->code === 'paypal')
                            ->schema([
                                Forms\Components\TextInput::make('credentials.client_id')
                                    ->label('PayPal Client ID')
                                    ->placeholder('AYb8... or Ab_...')
                                    ->helperText('Client ID from PayPal Developer Dashboard.')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('credentials.client_secret')
                                    ->label('PayPal Client Secret')
                                    ->password()
                                    ->revealable()
                                    ->placeholder('EC... or EL...')
                                    ->helperText('Secret key from PayPal Developer App.')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('credentials.webhook_id')
                                    ->label('PayPal Webhook ID')
                                    ->placeholder('WH-...')
                                    ->helperText('Webhook ID created in PayPal dashboard to verify events.')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Forms\Components\Section::make('Order Limits & Surcharges')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('min_order_amount')
                                    ->label('Minimum Order Amount (SAR)')
                                    ->numeric()
                                    ->prefix('SAR'),

                                Forms\Components\TextInput::make('max_order_amount')
                                    ->label('Maximum Order Amount (SAR)')
                                    ->numeric()
                                    ->prefix('SAR'),

                                Forms\Components\TextInput::make('extra_fee')
                                    ->label('Extra Handling / COD Fee (SAR)')
                                    ->numeric()
                                    ->prefix('SAR')
                                    ->default(0.00),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_en')
                    ->label('Gateway Name')
                    ->searchable()
                    ->description(fn (PaymentGateway $record) => $record->name_ar),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_sandbox')
                    ->label('Sandbox')
                    ->boolean()
                    ->trueIcon('heroicon-o-beaker')
                    ->falseIcon('heroicon-o-check-badge')
                    ->trueColor('warning')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('extra_fee')
                    ->label('Extra Fee')
                    ->money('SAR'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y, h:i A')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentGateways::route('/'),
            'edit' => Pages\EditPaymentGateway::route('/{record}/edit'),
        ];
    }
}
