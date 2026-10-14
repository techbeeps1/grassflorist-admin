<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingMethodResource\Pages;
use App\Models\ShippingMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ShippingMethodResource extends Resource
{
    protected static ?string $model = ShippingMethod::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Shipping Methods';
    protected static ?string $modelLabel = 'Shipping Method';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('shipping_methods') ?? false;
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        if (! $record) return null;
        return format_translatable($record->name, 'en')
            ?: (format_translatable($record->name, 'ar') ?: ($record->code ?: ('Shipping #' . $record->id)));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    // Left Column (2 Cols): Multilingual Content
                    Forms\Components\Group::make()->schema([
                        Forms\Components\Tabs::make('Shipping Method Details')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('English (EN)')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('name.en')
                                            ->label('Method Name (English)')
                                            ->placeholder('e.g. Delivery Inside Jeddah, Express Delivery')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (string $operation, ?string $state, Forms\Set $set, Forms\Get $get) {
                                                if ($operation === 'create' && empty($get('code')) && !empty($state)) {
                                                    $set('code', Str::slug($state, '_'));
                                                }
                                            }),

                                        Forms\Components\Textarea::make('description.en')
                                            ->label('Description (English)')
                                            ->placeholder('e.g. Refrigerated express floral delivery to all districts inside Jeddah.')
                                            ->rows(3),
                                    ]),

                                Forms\Components\Tabs\Tab::make('Arabic (عربي)')
                                    ->icon('heroicon-o-globe-alt')
                                    ->schema([
                                        Forms\Components\TextInput::make('name.ar')
                                            ->label('اسم طريقة التوصيل (عربي)')
                                            ->placeholder('مثال: التوصيل داخل جدة، توصيل سريع')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\Textarea::make('description.ar')
                                            ->label('وصف التوصيل (عربي)')
                                            ->placeholder('مثال: توصيل مبرد وسريع لجميع أحياء ومناطق مدينة جدة.')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->rows(3),
                                    ]),
                            ]),
                    ])->columnSpan(2),

                    // Right Column (1 Col): Pricing, System Code & Availability
                    Forms\Components\Group::make()->schema([
                        Forms\Components\Section::make('Pricing & Coverage')
                            ->description('Rate, coverage area and availability.')
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->label('Delivery Rate (SAR / ر.س)')
                                    ->numeric()
                                    ->prefix('SAR')
                                    ->required()
                                    ->default(30.00)
                                    ->helperText('Exact delivery charge applied to orders.'),

                                Forms\Components\TextInput::make('city')
                                    ->label('City / Coverage Area')
                                    ->placeholder('e.g. Jeddah, All Cities')
                                    ->default('Jeddah')
                                    ->helperText('Applicable city (e.g. Jeddah for inside city orders).'),

                                Forms\Components\TextInput::make('code')
                                    ->label('System Code (Identifier)')
                                    ->placeholder('e.g. inside_jeddah, outside_jeddah')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Unique lowercase identifier used by system/API.')
                                    ->alphaDash(),

                                Forms\Components\Toggle::make('is_free_shipping_eligible')
                                    ->label('Eligible for Free Delivery')
                                    ->default(true)
                                    ->live()
                                    ->helperText('If ON, orders meeting the free shipping threshold will get SAR 0 delivery.'),

                                Forms\Components\TextInput::make('min_order_for_free_shipping')
                                    ->label('Free Delivery Min Spend (SAR)')
                                    ->numeric()
                                    ->prefix('SAR')
                                    ->placeholder('e.g. 250')
                                    ->visible(fn (Forms\Get $get) => (bool)$get('is_free_shipping_eligible'))
                                    ->helperText('Leave empty to use global store threshold.'),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active (Enabled)')
                                    ->default(true)
                                    ->helperText('Only active shipping methods are shown to customers.'),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(1)
                                    ->helperText('Lower number displays first at checkout.'),
                            ]),
                    ])->columnSpan(1),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Method Name')
                    ->weight('bold')
                    ->formatStateUsing(fn ($record) => format_translatable($record->name, app()->getLocale()) ?: (format_translatable($record->name, 'en') ?: 'Shipping Method'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->fontFamily('mono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('City / Area')
                    ->badge()
                    ->color('info')
                    ->default('Jeddah'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Delivery Fee')
                    ->formatStateUsing(fn ($state) => 'SAR ' . number_format((float)$state, 2))
                    ->weight('semibold')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_free_shipping_eligible')
                    ->label('Free Threshold')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingMethods::route('/'),
            'create' => Pages\CreateShippingMethod::route('/create'),
            'edit' => Pages\EditShippingMethod::route('/{record}/edit'),
        ];
    }
}