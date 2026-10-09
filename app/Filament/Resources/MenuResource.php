<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuResource\Pages;
use App\Models\Menu;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static ?string $navigationGroup = 'Pages';

    protected static ?string $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static ?string $navigationLabel = 'Header & Footer Menus';

    protected static ?string $modelLabel = 'Navigation Menu';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false; // Header & Footer menus are managed in place
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Menu Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('slug')
                            ->label('Menu Identifier')
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active on Storefront')
                            ->default(true),
                    ])
                    ->columns(3),

                // ==================== 1. HEADER MENU BUILDER ====================
                Forms\Components\Section::make('Header Menu Items')
                    ->description('Manage main navigation bar items, simple dropdowns, and Occasions-style mega menus with random category products.')
                    ->visible(fn (?Menu $record) => $record?->slug === 'header')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Header Navigation Bar Items')
                            ->itemLabel(fn (array $state): ?string => 
                                (!empty($state['name_en']) ? $state['name_en'] : 'Item') . 
                                (!empty($state['name_ar']) ? ' (' . $state['name_ar'] . ')' : '') .
                                (!empty($state['has_dropdown']) ? ($state['dropdown_type'] === 'mega' ? ' [Mega Menu]' : ' [Dropdown]') : '')
                            )
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->schema([
                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\TextInput::make('name_en')
                                            ->label('Title (English)')
                                            ->placeholder('e.g. OCCASIONS')
                                            ->required(),

                                        Forms\Components\TextInput::make('name_ar')
                                            ->label('Title (Arabic)')
                                            ->placeholder('مثال: المناسبات')
                                            ->required(),

                                        Forms\Components\TextInput::make('url_en')
                                            ->label('URL (English)')
                                            ->placeholder('/en/category/occasions')
                                            ->required(),

                                        Forms\Components\TextInput::make('url_ar')
                                            ->label('URL (Arabic)')
                                            ->placeholder('/category/المناسبات')
                                            ->required(),
                                    ]),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Toggle::make('has_dropdown')
                                            ->label('Enable Submenu / Dropdown')
                                            ->reactive(),

                                        Forms\Components\Select::make('dropdown_type')
                                            ->label('Dropdown Style')
                                            ->options([
                                                'simple' => 'Standard Dropdown (Default)',
                                                'mega' => 'Full-Width Mega Menu (Occasions Style)',
                                            ])
                                            ->default('simple')
                                            ->helperText('Select Mega Menu to display columns of links with an optional featured card/product')
                                            ->visible(fn (callable $get) => (bool)$get('has_dropdown'))
                                            ->reactive(),

                                        Forms\Components\Select::make('showcase_type')
                                            ->label('Mega Menu Showcase (Right Card)')
                                            ->options([
                                                'category_random_product' => 'Random Product from Category (Dynamic)',
                                                'custom_card' => 'Custom Banner Card',
                                                'none' => 'No Showcase Card (Full-Width Links)',
                                            ])
                                            ->default('category_random_product')
                                            ->visible(fn (callable $get) => (bool)$get('has_dropdown') && $get('dropdown_type') === 'mega')
                                            ->reactive(),
                                    ]),

                                // --- MEGA MENU DYNAMIC PRODUCT SHOWCASE CONFIGURATION ---
                                Forms\Components\Section::make('Dynamic Category Product Showcase')
                                    ->description('A random active product from the selected category will automatically be featured with its photo, title, price, and direct link.')
                                    ->visible(fn (callable $get) => 
                                        (bool)$get('has_dropdown') && 
                                        $get('dropdown_type') === 'mega' && 
                                        $get('showcase_type') === 'category_random_product'
                                    )
                                    ->schema([
                                        Forms\Components\Select::make('featured_category_id')
                                            ->label('Select Category for Random Product')
                                            ->options(function () {
                                                return Category::where('is_visible', true)
                                                    ->get()
                                                    ->mapWithKeys(function ($cat) {
                                                        $en = is_array($cat->name) ? ($cat->name['en'] ?? '') : $cat->name;
                                                        $ar = is_array($cat->name) ? ($cat->name['ar'] ?? '') : '';
                                                        return [$cat->id => "{$en} ({$ar}) [ID: {$cat->id}]"];
                                                    });
                                            })
                                            ->searchable()
                                            ->required(fn (callable $get) => $get('showcase_type') === 'category_random_product')
                                            ->helperText('If you change this category, a random product from the newly selected category will appear in the mega menu.'),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('badge_text_en')
                                                    ->label('Badge Text (English)')
                                                    ->default('SAME-DAY DELIVERY'),

                                                Forms\Components\TextInput::make('badge_text_ar')
                                                    ->label('Badge Text (Arabic)')
                                                    ->default('توصيل في نفس اليوم'),

                                                Forms\Components\TextInput::make('cta_text_en')
                                                    ->label('Action Button (English)')
                                                    ->default('Explore Curated Flowers'),

                                                Forms\Components\TextInput::make('cta_text_ar')
                                                    ->label('Action Button (Arabic)')
                                                    ->default('تصفح التشكيلة الكاملة'),
                                            ]),
                                    ]),

                                // --- MEGA MENU CUSTOM CARD CONFIGURATION ---
                                Forms\Components\Section::make('Custom Visual Card Showcase')
                                    ->visible(fn (callable $get) => 
                                        (bool)$get('has_dropdown') && 
                                        $get('dropdown_type') === 'mega' && 
                                        $get('showcase_type') === 'custom_card'
                                    )
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('card_tag_en')
                                                    ->label('Badge Tag (English)')
                                                    ->default('Same-Day Delivery'),

                                                Forms\Components\TextInput::make('card_tag_ar')
                                                    ->label('Badge Tag (Arabic)')
                                                    ->default('توصيل في نفس اليوم'),

                                                Forms\Components\TextInput::make('card_title_en')
                                                    ->label('Card Title (English)')
                                                    ->default('Every Special Moment'),

                                                Forms\Components\TextInput::make('card_title_ar')
                                                    ->label('Card Title (Arabic)')
                                                    ->default('لكل لحظة مميزة'),

                                                Forms\Components\TextInput::make('card_desc_en')
                                                    ->label('Description (English)'),

                                                Forms\Components\TextInput::make('card_desc_ar')
                                                    ->label('Description (Arabic)'),

                                                Forms\Components\TextInput::make('card_image')
                                                    ->label('Banner Image URL')
                                                    ->placeholder('https://... or /storage/...'),

                                                Forms\Components\TextInput::make('card_link_en')
                                                    ->label('Target Link URL')
                                                    ->placeholder('/en/category/occasions'),
                                            ]),
                                    ]),

                                // --- SUB-CATEGORIES / SUB-ITEMS REPEATER ---
                                Forms\Components\Section::make('Submenu Items / Links')
                                    ->visible(fn (callable $get) => (bool)$get('has_dropdown'))
                                    ->schema([
                                        Forms\Components\Repeater::make('subcategories')
                                            ->label('Sub-links')
                                            ->itemLabel(fn (array $state): ?string => 
                                                (!empty($state['name_en']) ? $state['name_en'] : 'Sub-item') . 
                                                (!empty($state['name_ar']) ? ' (' . $state['name_ar'] . ')' : '')
                                            )
                                            ->reorderable()
                                            ->collapsible()
                                            ->schema([
                                                Forms\Components\Grid::make(4)
                                                    ->schema([
                                                        Forms\Components\TextInput::make('name_en')
                                                            ->label('Sub-item Name (English)')
                                                            ->required(),

                                                        Forms\Components\TextInput::make('name_ar')
                                                            ->label('Sub-item Name (Arabic)')
                                                            ->required(),

                                                        Forms\Components\TextInput::make('url_en')
                                                            ->label('URL (English)')
                                                            ->required(),

                                                        Forms\Components\TextInput::make('url_ar')
                                                            ->label('URL (Arabic)')
                                                            ->required(),
                                                    ]),

                                                Forms\Components\Select::make('icon')
                                                    ->label('Occasion Icon (for Mega Menu)')
                                                    ->options([
                                                        'heart' => '❤️ Heart (Love / Mother)',
                                                        'cake' => '🎂 Cake (Birthday)',
                                                        'shield' => '🛡️ Shield (Father)',
                                                        'sparkles' => '✨ Sparkles (For Her / Special)',
                                                        'gift' => '🎁 Gift (For Him / Present)',
                                                        'sun' => '☀️ Sun (Get Well)',
                                                        'graduation-cap' => '🎓 Graduation Cap',
                                                        'flower' => '🌸 Flower (Bouquet / Sorry)',
                                                        'baby' => '👶 Baby (New Born)',
                                                        'briefcase' => '💼 Briefcase (Job / Promotion)',
                                                    ])
                                                    ->nullable(),

                                                // 3rd Level Children (e.g. Balloon subcategories)
                                                Forms\Components\Repeater::make('children')
                                                    ->label('Nested 3rd-Level Links (Optional)')
                                                    ->collapsible()
                                                    ->schema([
                                                        Forms\Components\Grid::make(4)
                                                            ->schema([
                                                                Forms\Components\TextInput::make('name_en')
                                                                    ->label('Name (EN)')
                                                                    ->required(),
                                                                Forms\Components\TextInput::make('name_ar')
                                                                    ->label('Name (AR)')
                                                                    ->required(),
                                                                Forms\Components\TextInput::make('url_en')
                                                                    ->label('URL (EN)')
                                                                    ->required(),
                                                                Forms\Components\TextInput::make('url_ar')
                                                                    ->label('URL (AR)')
                                                                    ->required(),
                                                            ]),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),

                // ==================== 2. FOOTER MENU BUILDER ====================
                Forms\Components\Section::make('Footer Navigation Columns')
                    ->description('Manage link columns displayed in the luxury dark footer.')
                    ->visible(fn (?Menu $record) => $record?->slug === 'footer')
                    ->schema([
                        // Column 1
                        Forms\Components\Section::make('Column 1: Categories')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('footer_items.column_1.title_en')
                                            ->label('Column Title (English)')
                                            ->default('CATEGORIES')
                                            ->required(),

                                        Forms\Components\TextInput::make('footer_items.column_1.title_ar')
                                            ->label('Column Title (Arabic)')
                                            ->default('التصنيفات')
                                            ->required(),

                                        Forms\Components\Select::make('footer_items.column_1.source')
                                            ->label('Categories Source')
                                            ->options([
                                                'categories' => 'Auto-populate from Visible Store Categories',
                                            ])
                                            ->default('categories'),
                                    ]),
                            ]),

                        // Column 2
                        Forms\Components\Section::make('Column 2: Quick Links')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('footer_items.column_2.title_en')
                                            ->label('Column Title (English)')
                                            ->default('QUICK LINKS')
                                            ->required(),

                                        Forms\Components\TextInput::make('footer_items.column_2.title_ar')
                                            ->label('Column Title (Arabic)')
                                            ->default('روابط سريعة')
                                            ->required(),
                                    ]),

                                Forms\Components\Repeater::make('footer_items.column_2.links')
                                    ->label('Quick Links')
                                    ->reorderable()
                                    ->collapsible()
                                    ->schema([
                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                Forms\Components\TextInput::make('name_en')->label('Name (EN)')->required(),
                                                Forms\Components\TextInput::make('name_ar')->label('Name (AR)')->required(),
                                                Forms\Components\TextInput::make('url_en')->label('URL (EN)')->required(),
                                                Forms\Components\TextInput::make('url_ar')->label('URL (AR)')->required(),
                                            ]),
                                    ]),
                            ]),

                        // Column 3
                        Forms\Components\Section::make('Column 3: Policies & Customer Service')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('footer_items.column_3.title_en')
                                            ->label('Column Title (English)')
                                            ->default('POLICIES')
                                            ->required(),

                                        Forms\Components\TextInput::make('footer_items.column_3.title_ar')
                                            ->label('Column Title (Arabic)')
                                            ->default('السياسات')
                                            ->required(),
                                    ]),

                                Forms\Components\Repeater::make('footer_items.column_3.links')
                                    ->label('Policy Links')
                                    ->reorderable()
                                    ->collapsible()
                                    ->schema([
                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                Forms\Components\TextInput::make('name_en')->label('Name (EN)')->required(),
                                                Forms\Components\TextInput::make('name_ar')->label('Name (AR)')->required(),
                                                Forms\Components\TextInput::make('url_en')->label('URL (EN)')->required(),
                                                Forms\Components\TextInput::make('url_ar')->label('URL (AR)')->required(),
                                            ]),
                                    ]),
                            ]),

                        // Footer Settings
                        Forms\Components\Section::make('Footer Delivery City Badge')
                            ->schema([
                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\TextInput::make('settings.delivery_badge_en')
                                            ->label('Badge Label (English)')
                                            ->default('Delivery to'),

                                        Forms\Components\TextInput::make('settings.delivery_city_en')
                                            ->label('City Name (English)')
                                            ->default('Jeddah'),

                                        Forms\Components\TextInput::make('settings.delivery_badge_ar')
                                            ->label('Badge Label (Arabic)')
                                            ->default('التوصيل إلى'),

                                        Forms\Components\TextInput::make('settings.delivery_city_ar')
                                            ->label('City Name (Arabic)')
                                            ->default('جدة'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Menu Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Identifier / Location')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'header' => 'success',
                        'footer' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}
