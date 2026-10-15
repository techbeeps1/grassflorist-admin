<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttributeResource\Pages;
use App\Models\Attribute;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationLabel = 'Attributes';

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        if (! $record) return null;
        return format_translatable($record->name, 'en')
            ?: (format_translatable($record->name, 'ar') ?: ($record->slug ?: ('Attribute #' . $record->id)));
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        if ($user->isAdmin() || $user->isVendor()) return true;
        return $user->hasPermission('products') || $user->hasPermission('categories');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    // Main column (2 spans)
                    Forms\Components\Group::make()->schema([
                        Tabs::make('Attribute Info')
                            ->tabs([
                                Tabs\Tab::make('English (EN)')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        TextInput::make('name.en')
                                            ->label('Attribute Name (English)')
                                            ->placeholder('e.g., Color, Size, Flower Type')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set, Forms\Get $get) {
                                                if ($operation === 'create' && empty($get('slug'))) {
                                                    $set('slug', Str::slug($state));
                                                }
                                            }),
                                    ]),

                                Tabs\Tab::make('Arabic (عربي)')
                                    ->icon('heroicon-o-globe-alt')
                                    ->schema([
                                        TextInput::make('name.ar')
                                            ->label('Attribute Name (Arabic)')
                                            ->placeholder('مثال: اللون، الحجم، نوع الورد')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->required(),
                                    ]),
                            ]),

                        Section::make('Attribute Values')
                            ->description('Manage all selectable terms/options for this attribute (e.g., White, Pink, Red).')
                            ->schema([
                                Repeater::make('values')
                                    ->relationship('values')
                                    ->schema([
                                        Grid::make(4)->schema([
                                            TextInput::make('value.en')
                                                ->label('Value (EN)')
                                                ->placeholder('e.g. White')
                                                ->required()
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(function (string $operation, $state, Forms\Set $set, Forms\Get $get) {
                                                    if (empty($get('slug'))) {
                                                        $set('slug', Str::slug($state));
                                                    }
                                                })
                                                ->columnSpan(1),

                                            TextInput::make('value.ar')
                                                ->label('Value (AR)')
                                                ->placeholder('مثال: أبيض')
                                                ->extraInputAttributes(['dir' => 'rtl'])
                                                ->required()
                                                ->columnSpan(1),

                                            TextInput::make('slug')
                                                ->label('Slug')
                                                ->placeholder('e.g. white')
                                                ->required()
                                                ->columnSpan(1),

                                            ColorPicker::make('color_code')
                                                ->label('Color Swatch')
                                                ->columnSpan(1),
                                        ]),
                                    ])
                                    ->orderColumn('sort_order')
                                    ->reorderable('sort_order')
                                    ->collapsible()
                                    ->collapsed(false)
                                    ->itemLabel(fn (array $state): ?string => 
                                        ($state['value']['en'] ?? '') . (isset($state['value']['ar']) ? ' / ' . $state['value']['ar'] : '')
                                    )
                                    ->defaultItems(0)
                                    ->addActionLabel('+ Add Attribute Value'),
                            ]),
                    ])->columnSpan(2),

                    // Side column (1 span)
                    Forms\Components\Group::make()->schema([
                        Section::make('Configuration')
                            ->schema([
                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->unique(Attribute::class, 'slug', ignoreRecord: true)
                                    ->helperText('Unique URL-friendly identifier e.g. "color".'),

                                Select::make('type')
                                    ->label('Display Type')
                                    ->options([
                                        'color' => 'Color Swatch (Visual Circles)',
                                        'select' => 'Checkbox / Button List',
                                    ])
                                    ->default('select')
                                    ->required(),

                                Toggle::make('is_filterable')
                                    ->label('Show in Category Filters')
                                    ->helperText('Enable to display this attribute in the category page sidebar & mobile filter drawer.')
                                    ->default(true),

                                TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0),
                            ]),
                    ])->columnSpan(1),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Attribute Name')
                    ->formatStateUsing(function ($state, Attribute $record) {
                        $en = $record->getTranslation('name', 'en') ?: '';
                        $ar = $record->getTranslation('name', 'ar') ?: '';
                        if ($en && $ar && $en !== $ar) {
                            return "{$en} ({$ar})";
                        }
                        return $en ?: ($ar ?: '-');
                    })
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'color' => 'warning',
                        default => 'info',
                    }),

                TextColumn::make('values_count')
                    ->label('Values')
                    ->counts('values')
                    ->badge()
                    ->color('success'),

                TextColumn::make('values.value')
                    ->label('Options Preview')
                    ->limit(40)
                    ->formatStateUsing(function ($state, Attribute $record) {
                        return $record->values->map(function ($v) {
                            return $v->getTranslation('value', 'en') ?: $v->slug;
                        })->take(6)->implode(', ') . ($record->values->count() > 6 ? '...' : '');
                    }),

                IconColumn::make('is_filterable')
                    ->label('Filterable')
                    ->boolean(),

                TextColumn::make('sort_order')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttributes::route('/'),
            'create' => Pages\CreateAttribute::route('/create'),
            'edit' => Pages\EditAttribute::route('/{record}/edit'),
        ];
    }
}
