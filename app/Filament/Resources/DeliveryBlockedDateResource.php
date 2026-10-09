<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryBlockedDateResource\Pages;
use App\Models\DeliveryBlockedDate;
use App\Models\DeliverySlot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryBlockedDateResource extends Resource
{
    protected static ?string $model = DeliveryBlockedDate::class;

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';
    protected static ?string $navigationGroup = 'Shipping';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Blocked Dates & Holidays';
    protected static ?string $modelLabel = 'Blocked Date / Holiday';
    protected static ?string $pluralModelLabel = 'Blocked Dates & Holidays';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('shipping_methods') ?? true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Holiday / Blackout Date Configuration')
                    ->description('Set specific calendar dates when delivery slots must be disabled (holidays, emergencies, or maintenance).')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('title_en')
                                    ->label('Holiday / Reason Title (English)')
                                    ->required()
                                    ->placeholder('e.g. Saudi National Day, Eid Al-Fitr')
                                    ->default('Store Holiday'),

                                Forms\Components\TextInput::make('title_ar')
                                    ->label('Holiday / Reason Title (Arabic)')
                                    ->required()
                                    ->placeholder('e.g. اليوم الوطني السعودي، عيد الفطر')
                                    ->default('عطلة المتجر'),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Blocked Date (or Start Date)')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('Y-m-d')
                                    ->closeOnDateSelection()
                                    ->default(now()),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label('End Date (Optional Range)')
                                    ->helperText('Leave empty for a single day. Set if blocking consecutive holiday days (e.g. Eid).')
                                    ->native(false)
                                    ->displayFormat('Y-m-d')
                                    ->closeOnDateSelection(),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Block Active')
                                    ->default(true)
                                    ->helperText('Enable or temporarily disable without deleting.'),
                            ]),
                    ]),

                Forms\Components\Section::make('Customer Notification Notice (Multilingual)')
                    ->description('This notice will be displayed to the customer on checkout when they view or attempt to select this date.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('reason_en')
                                    ->label('Customer Notice Message (English)')
                                    ->rows(3)
                                    ->placeholder('e.g. Our boutique will be closed on this day for the national holiday. Deliveries will resume tomorrow.')
                                    ->helperText('Shown on checkout date picker in English.'),

                                Forms\Components\Textarea::make('reason_ar')
                                    ->label('Customer Notice Message (Arabic)')
                                    ->rows(3)
                                    ->placeholder('e.g. متجرنا مغلق بمناسبة العطلة الرسمية. سيتم استئناف التوصيل غداً كالمعتاد.')
                                    ->helperText('يظهر في صفحة الدفع باللغة العربية.'),
                            ]),
                    ]),

                Forms\Components\Section::make('Scope & Internal Notes')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('delivery_slot_id')
                            ->label('Applicable Scope')
                            ->options(function () {
                                $options = [null => '⛔ Entire Day (All Delivery Slots Closed)'];
                                foreach (DeliverySlot::all() as $slot) {
                                    $options[$slot->id] = "Slot: {$slot->title_en} ({$slot->title_ar})";
                                }
                                return $options;
                            })
                            ->default(null)
                            ->native(false)
                            ->helperText('By default, the entire day is blocked. Choose a specific slot if only one slot is unavailable.'),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Internal Admin Notes')
                            ->rows(2)
                            ->placeholder('Optional internal notes (not visible to customers).'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title_en')
                    ->label('Holiday / Reason')
                    ->searchable()
                    ->description(fn (DeliveryBlockedDate $record) => $record->title_ar),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Blocked Date(s)')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(function (DeliveryBlockedDate $record) {
                        if ($record->end_date && $record->end_date->ne($record->start_date)) {
                            return $record->start_date->format('M d, Y') . ' ➔ ' . $record->end_date->format('M d, Y');
                        }
                        return $record->start_date->format('M d, Y');
                    }),

                Tables\Columns\TextColumn::make('reason_en')
                    ->label('Customer Notice')
                    ->limit(40)
                    ->description(fn (DeliveryBlockedDate $record) => \Illuminate\Support\Str::limit($record->reason_ar, 40)),

                Tables\Columns\TextColumn::make('delivery_slot_id')
                    ->label('Scope')
                    ->badge()
                    ->color(fn ($state) => $state ? 'warning' : 'danger')
                    ->formatStateUsing(fn ($state, DeliveryBlockedDate $record) => $record->deliverySlot ? $record->deliverySlot->title_en : '⛔ All Day Closed'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('start_date', 'asc')
            ->filters([
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
            'index' => Pages\ListDeliveryBlockedDates::route('/'),
            'create' => Pages\CreateDeliveryBlockedDate::route('/create'),
            'edit' => Pages\EditDeliveryBlockedDate::route('/{record}/edit'),
        ];
    }
}
