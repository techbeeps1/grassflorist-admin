<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventBookingResource\Pages;
use App\Models\EventBooking;
use App\Services\EventBookingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventBookingResource extends Resource
{
    protected static ?string $model = EventBooking::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Inquiries & Leads';
    protected static ?int $navigationSort = 2;
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
                        Forms\Components\TextInput::make('first_name')->label('First Name')->required(),
                        Forms\Components\TextInput::make('last_name')->label('Last Name'),
                        Forms\Components\TextInput::make('phone')->label('Phone Number')->tel()->required(),
                        Forms\Components\TextInput::make('email')->label('Email Address')->email()->required(),
                        Forms\Components\TextInput::make('locale')->label('Submission Language')->disabled(),
                        Forms\Components\TextInput::make('ip_address')->label('IP Address')->disabled(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Event Logistics')
                    ->schema([
                        Forms\Components\TextInput::make('event_type')
                            ->label('Event Type')
                            ->required()
                            ->reactive(),
                        Forms\Components\TextInput::make('location')
                            ->label('Venue / Location')
                            ->required(),
                        Forms\Components\TextInput::make('event_date')
                            ->label('Event Date'),
                        Forms\Components\TextInput::make('guests')
                            ->label('Guest Count')
                            ->placeholder('e.g. 50-150')
                            ->visible(fn ($get, $record) => EventBookingService::isGuestsApplicable($get('event_type') ?? ($record?->event_type))),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'new' => 'New Inquiry',
                                'in_progress' => 'In Progress / Contacted',
                                'confirmed' => 'Confirmed & Booked',
                                'completed' => 'Event Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('new')
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Event-Specific Custom Details')
                    ->description('Dynamic parameters, preferences, and choices submitted for this event type')
                    ->schema([
                        // Newborn Welcome Decorations
                        Forms\Components\Fieldset::make('Newborn Welcome Details')
                            ->schema([
                                Forms\Components\TextInput::make('custom_fields.hospital_name')
                                    ->label('Hospital / Clinic Name'),
                                Forms\Components\TextInput::make('custom_fields.decoration_option')
                                    ->label('Decoration Scope'),
                                Forms\Components\TextInput::make('custom_fields.flower_type')
                                    ->label('Flower Type'),
                                Forms\Components\TextInput::make('custom_fields.theme_color')
                                    ->label('Theme & Colors'),
                            ])
                            ->columns(2)
                            ->visible(fn ($get, $record) => str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'newborn') || str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'مواليد')),

                        // Bridal Bouquets
                        Forms\Components\Fieldset::make('Bridal Bouquet Details')
                            ->schema([
                                Forms\Components\TextInput::make('custom_fields.bouquet_colors')
                                    ->label('Bouquet Colors'),
                                Forms\Components\TextInput::make('custom_fields.bouquet_size')
                                    ->label('Bouquet Size'),
                                Forms\Components\TextInput::make('custom_fields.bouquet_style')
                                    ->label('Bouquet Style'),
                                Forms\Components\TextInput::make('custom_fields.flower_type')
                                    ->label('Flower Type'),
                                Forms\Components\TextInput::make('custom_fields.delivery_method')
                                    ->label('Delivery Method'),
                                Forms\Components\TextInput::make('custom_fields.delivery_address')
                                    ->label('Delivery Address'),
                            ])
                            ->columns(3)
                            ->visible(fn ($get, $record) => str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'bridal') || str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'عرائس')),

                        // Milkah Ceremony
                        Forms\Components\Fieldset::make('Milkah Ceremony Details')
                            ->schema([
                                Forms\Components\TextInput::make('custom_fields.milkah_service')
                                    ->label('Requested Service'),
                                Forms\Components\TextInput::make('custom_fields.flower_type')
                                    ->label('Flower Type'),
                                Forms\Components\TextInput::make('custom_fields.theme_color')
                                    ->label('Theme & Colors'),
                                Forms\Components\TextInput::make('custom_fields.delivery_method')
                                    ->label('Delivery Method'),
                                Forms\Components\TextInput::make('custom_fields.delivery_address')
                                    ->label('Delivery Address'),
                            ])
                            ->columns(3)
                            ->visible(fn ($get, $record) => str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'milkah') || str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'ملكة')),

                        // Hall & Home Wedding
                        Forms\Components\Fieldset::make('Wedding & Celebration Details')
                            ->schema([
                                Forms\Components\TextInput::make('custom_fields.home_event_type')
                                    ->label('Occasion Type'),
                                Forms\Components\TextInput::make('custom_fields.home_event_other_text')
                                    ->label('Occasion Details (If Other)'),
                                Forms\Components\TextInput::make('custom_fields.flower_type')
                                    ->label('Flower Type'),
                                Forms\Components\TextInput::make('custom_fields.theme_color')
                                    ->label('Preferred Colors'),
                                Forms\Components\TextInput::make('custom_fields.theme')
                                    ->label('Overall Theme / Concept'),
                                Forms\Components\TagsInput::make('custom_fields.decor_areas')
                                    ->label('Decorated Areas')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->visible(fn ($get, $record) => EventBookingService::isGuestsApplicable($get('event_type') ?? $record?->event_type)),

                        // Flower Supply
                        Forms\Components\Fieldset::make('Corporate & Hotel Flower Supply Details')
                            ->schema([
                                Forms\Components\TextInput::make('custom_fields.company_name')
                                    ->label('Company / Hotel Name'),
                                Forms\Components\TextInput::make('custom_fields.flower_type')
                                    ->label('Flower Type'),
                                Forms\Components\TextInput::make('custom_fields.supply_frequency')
                                    ->label('Supply Frequency'),
                                Forms\Components\TextInput::make('custom_fields.supply_quantity')
                                    ->label('Supply Quantity'),
                                Forms\Components\TextInput::make('custom_fields.contract_duration')
                                    ->label('Contract Duration'),
                                Forms\Components\TextInput::make('custom_fields.estimated_budget')
                                    ->label('Estimated Budget'),
                                Forms\Components\TextInput::make('custom_fields.purchasing_contact_name')
                                    ->label('Procurement Contact Person'),
                                Forms\Components\TextInput::make('custom_fields.purchasing_contact_phone')
                                    ->label('Procurement Phone'),
                                Forms\Components\TagsInput::make('custom_fields.supply_types')
                                    ->label('Arrangement Types')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('custom_fields.supply_other')
                                    ->label('Other Supply Requirements')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->visible(fn ($get, $record) => str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'supply') || str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'توريد')),

                        // Grand Opening Ceremony
                        Forms\Components\Fieldset::make('Grand Opening Details')
                            ->schema([
                                Forms\Components\TextInput::make('custom_fields.opening_type')
                                    ->label('Opening Type'),
                                Forms\Components\TextInput::make('custom_fields.opening_type_other')
                                    ->label('Other Opening Type'),
                                Forms\Components\TextInput::make('custom_fields.flower_type')
                                    ->label('Flower Type'),
                                Forms\Components\TextInput::make('custom_fields.theme_color')
                                    ->label('Theme & Colors'),
                                Forms\Components\TextInput::make('custom_fields.theme')
                                    ->label('Concept / Theme'),
                                Forms\Components\TextInput::make('custom_fields.estimated_budget')
                                    ->label('Estimated Budget'),
                                Forms\Components\TagsInput::make('custom_fields.decor_areas')
                                    ->label('Decoration Areas')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->visible(fn ($get, $record) => str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'opening') || str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'افتتاح')),

                        // Corporate Event
                        Forms\Components\Fieldset::make('Corporate Event Details')
                            ->schema([
                                Forms\Components\TextInput::make('custom_fields.company_name')
                                    ->label('Company Name'),
                                Forms\Components\TextInput::make('custom_fields.corporate_type')
                                    ->label('Event Type'),
                                Forms\Components\TextInput::make('custom_fields.corporate_type_other')
                                    ->label('Other Event Type'),
                                Forms\Components\TextInput::make('custom_fields.flower_type')
                                    ->label('Flower Type'),
                                Forms\Components\TextInput::make('custom_fields.theme_color')
                                    ->label('Theme & Colors'),
                                Forms\Components\TextInput::make('custom_fields.theme')
                                    ->label('Concept / Theme'),
                                Forms\Components\TextInput::make('custom_fields.estimated_budget')
                                    ->label('Estimated Budget'),
                                Forms\Components\TagsInput::make('custom_fields.decor_areas')
                                    ->label('Decoration Areas')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->visible(fn ($get, $record) => str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'corporate') || str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'شركات')),

                        // Other Inquiries
                        Forms\Components\Fieldset::make('Inquiry Specifics')
                            ->schema([
                                Forms\Components\Textarea::make('custom_fields.inquiry_details')
                                    ->label('Inquiry Details')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn ($get, $record) => str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'other') || str_contains(mb_strtolower((string)($get('event_type') ?? $record?->event_type)), 'استفسار')),

                        // Raw key-value fallback for complete visibility and manual editing
                        Forms\Components\Section::make('Raw Custom Parameters (JSON)')
                            ->description('Direct key-value storage of all dynamic parameters submitted by the client')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Forms\Components\KeyValue::make('custom_fields')
                                    ->label('')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Forms\Components\Section::make('Client Vision & Notes')
                    ->schema([
                        Forms\Components\Textarea::make('message')
                            ->label('Client Handwritten Message / Inquiries')
                            ->placeholder('Any additional thoughts, notes, or special instructions written by the client')
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
                    ->searchable()
                    ->description(function (EventBooking $record) {
                        $cf = $record->custom_fields ?? [];
                        if (empty($cf)) return null;
                        $parts = [];
                        if (!empty($cf['hospital_name'])) $parts[] = 'Hospital: ' . $cf['hospital_name'];
                        if (!empty($cf['company_name'])) $parts[] = 'Company: ' . $cf['company_name'];
                        if (!empty($cf['bouquet_size'])) $parts[] = 'Size: ' . $cf['bouquet_size'];
                        if (!empty($cf['home_event_type'])) $parts[] = 'Type: ' . $cf['home_event_type'];
                        if (!empty($cf['flower_type'])) $parts[] = 'Flowers: ' . $cf['flower_type'];
                        return !empty($parts) ? implode(' • ', $parts) : null;
                    }),

                Tables\Columns\TextColumn::make('event_date')
                    ->label('Event Date')
                    ->sortable(),

                Tables\Columns\TextColumn::make('location')
                    ->label('Venue / City')
                    ->limit(25),

                Tables\Columns\TextColumn::make('guests')
                    ->label('Guests')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->state(fn (EventBooking $record) => $record->guests ?: null),

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
