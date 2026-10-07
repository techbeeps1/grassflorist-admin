<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
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

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationGroup = "Shop";

    protected static ?string $navigationIcon = 'heroicon-o-bookmark';

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        if (! $record) return null;
        return format_translatable($record->name, 'en')
            ?: (format_translatable($record->name, 'ar') ?: ($record->slug ?: ('Category #' . $record->id)));
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('categories') ?? false;
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Group::make()->schema([
                        Tabs::make('Category Details')
                            ->tabs([
                                Tabs\Tab::make('English (EN)')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        TextInput::make('name.en')
                                            ->label('Name (English)')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set, Forms\Get $get) {
                                                if ($operation === 'create' && empty($get('slug'))) {
                                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                                }
                                            }),

                                        TextInput::make('slug')
                                            ->label('Slug (English)')
                                            ->formatStateUsing(fn ($state) => urldecode((string) $state))
                                            ->required(),

                                        Textarea::make('description.en')
                                            ->label('Description (English)')
                                            ->rows(3),

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
                                            ->label('Name (Arabic)')
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

                                        Textarea::make('description.ar')
                                            ->label('Description (Arabic)')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->rows(3),

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
                    ])->columnSpan(2),

                    Forms\Components\Group::make()->schema([
                        Forms\Components\Section::make('Organization & Media')
                            ->schema([
                                Select::make('parent_id')
                                    ->label('Parent Category')
                                    ->relationship('parent', 'name')
                                    ->getOptionLabelFromRecordUsing(fn (Category $record) => format_translatable($record->name, 'en') ?: $record->slug)
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                TextInput::make('source_id')
                                    ->label('WooCommerce ID')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn (?Category $record) => filled($record?->source_id)),

                                Toggle::make('is_visible')
                                    ->label('Is Visible on Store')
                                    ->default(true),

                                FileUpload::make('cat_image')
                                    ->label('Category Image')
                                    ->image()
                                    ->disk('public')
                                    ->directory('categories'),
                            ]),
                    ])->columnSpan(1),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cat_image')
                    ->label('Image')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl('/placeholder-category.png'),

                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Category Name')
                    ->formatStateUsing(function ($state, Category $record) {
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->formatStateUsing(function ($state, ?Category $record) {
                        if (! $record?->parent) return '-';
                        $parentName = format_translatable($record->parent->name, 'ar')
                            ?: (format_translatable($record->parent->name, 'en') ?: $record->parent->slug);
                        return urldecode($parentName);
                    })
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->formatStateUsing(fn ($state) => urldecode((string) $state))
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('source_id')
                    ->label('WP ID')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make(),
                Action::make('sync_wp_categories')
                    ->label('Sync from WooCommerce')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Categories from WooCommerce')
                    ->modalDescription('Do you want to fetch and update categories directly from grassflorist.com?')
                    ->action(function (StoreImportService $importService) {
                        try {
                            $initial = $importService->importCategoriesChunk(1, 100);
                            for ($p = 2; $p <= $initial['total_pages']; $p++) {
                                $importService->importCategoriesChunk($p, 100);
                            }

                            Notification::make()
                                ->title('Categories Synced Successfully!')
                                ->body("Synced {$initial['total_records']} categories from grassflorist.com.")
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
