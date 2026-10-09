<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventBookingResource\Pages;
use App\Models\EventBooking;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventBookingResource extends Resource
{
    protected static ?string $model = EventBooking::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Pages';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Event Booking';
    protected static ?string $pluralModelLabel = 'Event Bookings';

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'new')->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Client Information')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')->required(),
                        Forms\Components\TextInput::make('last_name'),
                        Forms\Components\TextInput::make('phone')->tel()->required(),
                        Forms\Components\TextInput::make('email')->email()->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Event Logistics')
                    ->schema([
                        Forms\Components\TextInput::make('event_type')->required(),
                        Forms\Components\TextInput::make('location')->required(),
                        Forms\Components\TextInput::make('event_date')->label('Event Date'),
                        Forms\Components\TextInput::make('guests')->label('Guest Count'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'new' => 'New Inquiry',
                                'in_progress' => 'In Progress / Contacted',
                                'confirmed' => 'Confirmed & Booked',
                                'completed' => 'Event Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('new')
                            ->required(),
                        Forms\Components\TextInput::make('locale')->disabled(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Client Vision & Notes')
                    ->schema([
                        Forms\Components\Textarea::make('message')
                            ->label('Client Message / Inquiries')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client')
                    ->label('Client Name')
                    ->state(fn (EventBooking $record) => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->url(fn (EventBooking $record) => "tel:{$record->phone}"),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event Type')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                Tables\Columns\TextColumn::make('event_date')
                    ->label('Event Date')
                    ->sortable(),

                Tables\Columns\TextColumn::make('location')
                    ->label('Venue / City')
                    ->limit(25),

                Tables\Columns\TextColumn::make('guests')
                    ->label('Guests')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'new' => 'New',
                        'in_progress' => 'In Progress',
                        'confirmed' => 'Confirmed',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted At')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'new' => 'New',
                        'in_progress' => 'In Progress',
                        'confirmed' => 'Confirmed',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventBookings::route('/'),
        ];
    }
}
