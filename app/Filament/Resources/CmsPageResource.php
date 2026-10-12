<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CmsPageResource\Pages;
use App\Filament\Resources\CmsPageResource\RelationManagers;
use App\Models\CmsPage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\RichEditor;
use Illuminate\Support\Str;
use Filament\Forms\Set;

class CmsPageResource extends Resource
{
    protected static ?string $model = CmsPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';
    protected static ?string $navigationGroup = 'Content & Storefront';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'Page';
    protected static ?string $pluralModelLabel = 'Pages';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('cms_pages') ?? false;
    }
    
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        if (! $record) return null;
        return format_translatable($record->title, 'en')
            ?: (format_translatable($record->title, 'ar') ?: ($record->slug ?: ('Page #' . $record->id)));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Update Information')
                    ->schema([
                        Forms\Components\Placeholder::make('last_updated_at')
                            ->label('Last Updated')
                            ->content(function (?CmsPage $record) {
                                return $record?->updated_at
                                    ? $record->updated_at->format('d M Y, h:i A')
                                    : 'Not updated yet';
                            }),

                        Forms\Components\Placeholder::make('updated_by')
                            ->label('Updated By')
                            ->content(function (?CmsPage $record) {
                                return $record?->updatedBy?->name ?? 'Not available';
                            }),
                    ])
                    ->columns(2)
                    ->visible(fn (string $operation) => $operation === 'edit'),

                Forms\Components\Tabs::make('Page Content')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('English (EN)')
                            ->icon('heroicon-o-language')
                            ->schema([
                                TextInput::make('title.en')
                                    ->label('Page Title (English)')
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
                                    ->unique(ignoreRecord: true),

                                Textarea::make('short_description.en')
                                    ->label('Short Summary / Excerpt (English)')
                                    ->rows(3),

                                RichEditor::make('content.en')
                                    ->label('Main Content (English)'),

                                Forms\Components\Section::make('SEO Metadata (English)')
                                    ->schema([
                                        TextInput::make('meta_title.en')
                                            ->label('Meta Title (English)'),
                                        TextInput::make('meta_keywords.en')
                                            ->label('Meta Keywords (English)'),
                                        Textarea::make('meta_description.en')
                                            ->label('Meta Description (English)')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->collapsed(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Arabic (عربي)')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                TextInput::make('title.ar')
                                    ->label('Page Title (Arabic)')
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

                                Textarea::make('short_description.ar')
                                    ->label('Short Summary / Excerpt (Arabic)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->rows(3),

                                RichEditor::make('content.ar')
                                    ->label('Main Content (Arabic)')
                                    ->extraInputAttributes(['dir' => 'rtl']),

                                Forms\Components\Section::make('SEO Metadata (Arabic)')
                                    ->schema([
                                        TextInput::make('meta_title.ar')
                                            ->label('Meta Title (Arabic)')
                                            ->extraInputAttributes(['dir' => 'rtl']),
                                        TextInput::make('meta_keywords.ar')
                                            ->label('Meta Keywords (Arabic)')
                                            ->extraInputAttributes(['dir' => 'rtl']),
                                        Textarea::make('meta_description.ar')
                                            ->label('Meta Description (Arabic)')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->collapsed(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Forms\Components\Section::make('Media & Status')
                    ->schema([
                        Forms\Components\FileUpload::make('banner_images')
                            ->label('Banner Image')
                            ->image()
                            ->disk('public')
                            ->directory('cms-pages/banner'),

                        Toggle::make('is_active')
                            ->label('Is Active / Published')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Page Title')
                    ->formatStateUsing(function ($state, CmsPage $record) {
                        $en = format_translatable($record->title, 'en');
                        $ar = format_translatable($record->title, 'ar');
                        if ($en && $ar && $en !== $ar) {
                            return "{$en} / {$ar}";
                        }
                        return $en ?: ($ar ?: '-');
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
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
            'index' => Pages\ListCmsPages::route('/'),
            'create' => Pages\CreateCmsPage::route('/create'),
            'edit' => Pages\EditCmsPage::route('/{record}/edit'),
        ];
    }
}
