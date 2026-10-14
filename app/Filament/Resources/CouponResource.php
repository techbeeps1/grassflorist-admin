<?php
namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Filament\Resources\CouponResource\RelationManagers;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Illuminate\Support\Str;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;


class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';


    protected static ?int $navigationSort = 5;

    protected static ?string $navigationGroup = "Shop";

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('coupons') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Section::make('Update Information')
                ->schema([
                    Forms\Components\Placeholder::make('last_updated_at')
                        ->label('Last Updated')
                        ->content(function (?Coupon $record) {
                            return $record?->updated_at
                                ? $record->updated_at->format('d M Y, h:i A')
                                : 'Not updated yet';
                        }),

                    Forms\Components\Placeholder::make('updated_by')
                        ->label('Updated By')
                        ->content(function (?Coupon $record) {
                            return $record?->updatedBy?->name ?? 'Not available';
                        }),
                ])
                ->columns(2)
                ->visible(fn (string $operation) => $operation === 'edit'),

                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->placeholder('e.g. WELCOME50'),

                Forms\Components\Select::make('type')
                    ->options([
                        'fixed' => 'Fixed Amount (SAR)',
                        'percent' => 'Percentage (%)',
                    ])
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('value')
                    ->label(fn (Forms\Get $get): string => $get('type') === 'percent' ? 'Discount Percentage (%)' : 'Discount Amount (SAR)')
                    ->required()
                    ->numeric()
                    ->rules(['min:0']),

                Forms\Components\TextInput::make('max_discount_amount')
                    ->label('Max Discount Cap (SAR)')
                    ->helperText('Maximum discount limit in SAR for percentage coupons (optional)')
                    ->numeric()
                    ->rules(['min:0'])
                    ->visible(fn (Forms\Get $get): bool => $get('type') === 'percent'),

                // Category
                Forms\Components\Select::make('category_id')
                    ->label('Applicable Categories (Optional)')
                    ->relationship(
                        name: 'category',
                        titleAttribute: 'name',
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->afterStateHydrated(function (Forms\Components\Select $component, $state) {
                        if (is_string($state)) {
                            $component->state(json_decode($state, true));
                        }
                    }),

                Forms\Components\Section::make('Usage Limits & Restrictions')
                    ->schema([
                        Forms\Components\TextInput::make('user_limit')
                            ->label('Usage Limit Per Customer')
                            ->helperText('Max times a single customer can use this coupon (e.g. 1 for Welcome / Single-Use). Leave empty for unlimited.')
                            ->numeric()
                            ->rules(['min:1'])
                            ->placeholder('e.g. 1'),

                        Forms\Components\TextInput::make('usage_limit')
                            ->label('Total Usage Limit (Overall)')
                            ->helperText('Total times this coupon can be used across all customers. Leave empty for unlimited.')
                            ->numeric()
                            ->rules(['min:1'])
                            ->placeholder('e.g. 100'),

                        Forms\Components\Toggle::make('is_first_order_only')
                            ->label('First Order Only (New Customers Only)')
                            ->helperText('Restrict this coupon strictly to brand-new customers who have never placed an order.')
                            ->default(false),

                        Forms\Components\Toggle::make('is_subscriber_only')
                            ->label('Apply Only for Subscribers (Newsletter Only)')
                            ->helperText('Restrict this coupon strictly to verified newsletter subscribers.')
                            ->default(false),
                    ])->columns(2),

                Forms\Components\Section::make('Cart Value & Dates')
                    ->schema([
                        Forms\Components\TextInput::make('min_cart_amount')
                            ->label('Min Cart Amount (SAR)')
                            ->numeric()
                            ->rules(['min:0'])
                            ->placeholder('0'),

                        Forms\Components\TextInput::make('max_cart_amount')
                            ->label('Max Cart Amount (SAR)')
                            ->numeric()
                            ->rules(['min:0'])
                            ->placeholder('Optional'),

                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Valid From')
                            ->required()
                            ->default(now()),

                        Forms\Components\DateTimePicker::make('valid_to')
                            ->label('Valid To')
                            ->required()
                            ->default(now()->addMonths(1)),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true)
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->copyable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('value')
                    ->label('Discount')
                    ->formatStateUsing(function (Coupon $record): string {
                        if ($record->type === 'percent') {
                            $text = $record->value . '%';
                            if ($record->max_discount_amount) {
                                $text .= ' (Max ' . $record->max_discount_amount . ' SAR)';
                            }
                            return $text;
                        }
                        return number_format((float) $record->value, 2) . ' SAR';
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('user_limit')
                    ->label('Per User Limit')
                    ->formatStateUsing(fn (?int $state): string => $state ? $state . ' time' . ($state > 1 ? 's' : '') : 'Unlimited')
                    ->badge()
                    ->color(fn (?int $state): string => $state ? 'warning' : 'gray'),

                Tables\Columns\IconColumn::make('is_first_order_only')
                    ->label('1st Order Only')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_subscriber_only')
                    ->label('Subscribers Only')
                    ->boolean(),

                Tables\Columns\TextColumn::make('min_cart_amount')
                    ->label('Min Cart')
                    ->formatStateUsing(fn ($state) => $state ? $state . ' SAR' : '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valid_to')
                    ->dateTime('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->counts('orders')
                    ->label('Times Used')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('total_discount')
                    ->label('Total Discount')
                    ->getStateUsing(fn (Coupon $record): string => number_format((float) $record->orders()->sum('discount_amount'), 2) . ' SAR')
                    ->sortable(false),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}