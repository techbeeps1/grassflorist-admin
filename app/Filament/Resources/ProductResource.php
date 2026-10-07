<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\StoreImportService;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationGroup = "Shop";
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Products';

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        if (! $record) return null;
        return format_translatable($record->name, 'en')
            ?: (format_translatable($record->name, 'ar') ?: ($record->sku ?: ('Product #' . $record->id)));
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        if ($user->isAdmin() || $user->isVendor()) return true;
        return $user->hasPermission('products');
    }

    public static function getNavigationBadge(): ?string
    {
        if (auth()->user()?->isAdmin()) {
            return (string) static::getModel()::count();
        }
        if (auth()->user()?->isVendor()) {
            $vendor = auth()->user()->vendor;
            if ($vendor) {
                return (string) static::getModel()::where('vendor_id', $vendor->id)->count();
            }
            return '0';
        }

        return null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()?->isVendor()) {
            $vendor = auth()->user()->vendor;
            if ($vendor) {
                $query->where('vendor_id', $vendor->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    // Left Column (2 spans)
                    Forms\Components\Group::make()->schema([
                        Tabs::make('Translations')
                            ->tabs([
                                Tabs\Tab::make('English (EN)')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        TextInput::make('name.en')
                                            ->label('Product Name (English)')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set, Forms\Get $get) {
                                                if ($operation === 'create' && empty($get('slug'))) {
                                                    $set('slug', Str::slug($state));
                                                }
                                            }),

                                        TextInput::make('slug')
                                            ->label('Slug (English)')
                                            ->required()
                                            ->unique(Product::class, 'slug', ignoreRecord: true),

                                        Textarea::make('sub_title.en')
                                            ->label('Sub Description / Short Description (English)')
                                            ->rows(3),

                                        Forms\Components\RichEditor::make('description.en')
                                            ->label('Main Description (English)'),

                                        Forms\Components\Section::make('Search Engine Optimization (SEO - English)')
                                            ->schema([
                                                TextInput::make('meta_tag_title.en')
                                                    ->label('Meta Title (English)'),
                                                TextInput::make('meta_tag_keywords.en')
                                                    ->label('Meta Keywords (English)'),
                                                Textarea::make('meta_tag_description.en')
                                                    ->label('Meta Description (English)')
                                                    ->rows(2)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->collapsed(),
                                    ]),

                                Tabs\Tab::make('Arabic (عربي)')
                                    ->icon('heroicon-o-globe-alt')
                                    ->schema([
                                        TextInput::make('name.ar')
                                            ->label('Product Name (Arabic)')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set, Forms\Get $get) {
                                                if ($operation === 'create' && empty($get('slug_ar'))) {
                                                    $clean = str_replace(' ', '-', trim($state));
                                                    $set('slug_ar', $clean);
                                                }
                                            }),

                                        TextInput::make('slug_ar')
                                            ->label('Slug (Arabic)')
                                            ->extraInputAttributes(['dir' => 'rtl']),

                                        Textarea::make('sub_title.ar')
                                            ->label('Sub Description / Short Description (Arabic)')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->rows(3),

                                        Forms\Components\RichEditor::make('description.ar')
                                            ->label('Main Description (Arabic)')
                                            ->extraInputAttributes(['dir' => 'rtl']),

                                        Forms\Components\Section::make('Search Engine Optimization (SEO - Arabic)')
                                            ->schema([
                                                TextInput::make('meta_tag_title.ar')
                                                    ->label('Meta Title (Arabic)')
                                                    ->extraInputAttributes(['dir' => 'rtl']),
                                                TextInput::make('meta_tag_keywords.ar')
                                                    ->label('Meta Keywords (Arabic)')
                                                    ->extraInputAttributes(['dir' => 'rtl']),
                                                Textarea::make('meta_tag_description.ar')
                                                    ->label('Meta Description (Arabic)')
                                                    ->extraInputAttributes(['dir' => 'rtl'])
                                                    ->rows(2)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->collapsed(),
                                    ]),
                            ]),

                        Forms\Components\Section::make('Product Media')
                            ->schema([
                                FileUpload::make('image')
                                    ->label('Featured Image')
                                    ->image()
                                    ->imagePreviewHeight('220')
                                    ->openable()
                                    ->disk('public')
                                    ->directory('products'),

                                FileUpload::make('gallery')
                                    ->label('Gallery Images')
                                    ->multiple()
                                    ->reorderable()
                                    ->image()
                                    ->disk('public')
                                    ->directory('products/gallery')
                                    ->panelLayout('grid')
                                    ->imagePreviewHeight('150')
                                    ->openable(),
                            ]),
                    ])->columnSpan(2),

                    // Right Column (1 span)
                    Forms\Components\Group::make()->schema([
                        Forms\Components\Section::make('Pricing & Inventory')
                            ->schema([
                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->required()
                                    ->unique(Product::class, 'sku', ignoreRecord: true),

                                TextInput::make('price')
                                    ->label('Sale Price')
                                    ->required()
                                    ->numeric()
                                    ->prefix(currency_symbol()),

                                TextInput::make('mrp')
                                    ->label('Regular Price (MRP)')
                                    ->numeric()
                                    ->prefix(currency_symbol()),

                                TextInput::make('quantity')
                                    ->label('Stock Quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(100),

                                Select::make('category_id')
                                    ->label('Categories')
                                    ->multiple()
                                    ->options(function () {
                                        return Category::all()->mapWithKeys(function ($cat) {
                                            $name = format_translatable($cat->name, 'en') ?: $cat->slug;
                                            return [$cat->id => $name];
                                        })->toArray();
                                    })
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('source_id')
                                    ->label('WooCommerce ID')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn (?Product $record) => filled($record?->source_id)),

                                Toggle::make('is_visible')
                                    ->label('Active / Visible')
                                    ->default(true),
                            ]),
                    ])->columnSpan(1),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Image')
                    ->disk('public')
                    ->circular()
                    ->size(45),

                Tables\Columns\TextColumn::make('name')
                    ->label('Product Name')
                    ->formatStateUsing(function ($state, Product $record) {
                        $ar = $record->getTranslation('name', 'ar') ?: '';
                        $en = $record->getTranslation('name', 'en') ?: '';

                        if (str_contains($en, '%')) {
                            $en = urldecode($en);
                        }
                        if (str_contains($ar, '%')) {
                            $ar = urldecode($ar);
                        }

                        if ($en && $ar && $en !== $ar) {
                            return "{$ar} ({$en})";
                        }
                        return $ar ?: ($en ?: '-');
                    })
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('SAR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Stock')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state <= 5 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('source_id')
                    ->label('WP ID')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make(),
                Action::make('sync_wp_products')
                    ->label('Sync from WooCommerce')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Products from WooCommerce')
                    ->modalDescription('Do you want to fetch and sync products directly from grassflorist.com?')
                    ->action(function (StoreImportService $importService) {
                        try {
                            $initial = $importService->importProductsChunk(1, 50);
                            for ($p = 2; $p <= $initial['total_pages']; $p++) {
                                $importService->importProductsChunk($p, 50);
                            }

                            Notification::make()
                                ->title('Products Synced Successfully!')
                                ->body("Synced {$initial['total_records']} products from grassflorist.com.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Sync Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}