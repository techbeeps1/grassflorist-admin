<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliverySlotResource\Pages;
use App\Models\DeliverySlot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliverySlotResource extends Resource
{
    protected static ?string $model = DeliverySlot::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Delivery Slots (Friday / Daily)';
    protected static ?string $modelLabel = 'Delivery Slot';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('shipping_methods') ?? true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Delivery Slot Configuration')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('day_type')
                                    ->label('Applicable Day Type')
                                    ->options([
                                        'regular' => 'Regular Days (Saturday to Thursday)',
                                        'friday' => 'Friday Only (Special Jummah Timing)',
                                        'all' => 'All Days (Everyday Slot)',
                                    ])
                                    ->required()
                                    ->default('regular')
                                    ->native(false),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active Slot')
                                    ->default(true),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('title_en')
                                    ->label('Slot Title (English)')
                                    ->placeholder('e.g. 11:00 AM to 03:00 PM')
                                    ->required(),

                                Forms\Components\TextInput::make('title_ar')
                                    ->label('Slot Title (Arabic)')
                                    ->placeholder('e.g. من 11 صباحاً وحتى 3 مساءً')
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TimePicker::make('start_time')
                                    ->label('Start Time')
                                    ->seconds(false)
                                    ->required(),

                                Forms\Components\TimePicker::make('end_time')
                                    ->label('End Time')
                                    ->seconds(false)
                                    ->required(),

                                Forms\Components\TextInput::make('cutoff_hours_before')
                                    ->label('Cutoff Before Start (Hours)')
                                    ->numeric()
                                    ->default(1)
                                    ->helperText('Hours before start time to auto-disable slot for same-day delivery.'),

                                Forms\Components\TextInput::make('extra_charge')
                                    ->label('Extra Express Surcharge (SAR)')
                                    ->numeric()
                                    ->prefix('SAR')
                                    ->default(0.00),
                            ]),

                        Forms\Components\TextInput::make('max_orders_capacity')
                            ->label('Max Order Capacity (Per Slot / Day)')
                            ->numeric()
                            ->placeholder('Leave empty for unlimited')
                            ->helperText('Prevents order overload during peak florist hours.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('day_type')
                    ->label('Day Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'friday' => 'warning',
                        'regular' => 'info',
                        'all' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'friday' => '🕌 Friday Special',
                        'regular' => '📅 Regular Days (Sat-Thu)',
                        'all' => '🌟 Everyday',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('title_en')
                    ->label('Slot Timing')
                    ->searchable()
                    ->description(fn (DeliverySlot $record) => $record->title_ar),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Time Window')
                    ->formatStateUsing(fn (DeliverySlot $record) => date('h:i A', strtotime($record->start_time)) . ' - ' . date('h:i A', strtotime($record->end_time))),

                Tables\Columns\TextColumn::make('cutoff_hours_before')
                    ->label('Cutoff')
                    ->formatStateUsing(fn ($state) => $state . ' hr before'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('extra_charge')
                    ->label('Extra Charge')
                    ->money('SAR'),
            ])
            ->defaultSort('day_type', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('day_type')
                    ->options([
                        'regular' => 'Regular Days',
                        'friday' => 'Friday',
                        'all' => 'All Days',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status'),
            ])
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliverySlots::route('/'),
            'create' => Pages\CreateDeliverySlot::route('/create'),
            'edit' => Pages\EditDeliverySlot::route('/{record}/edit'),
        ];
    }
}
