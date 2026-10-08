<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TestimonialResource\Pages;
use App\Models\Testimonial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static ?string $navigationGroup = 'Pages';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Testimonial';
    protected static ?string $pluralModelLabel = 'Testimonials';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('testimonials') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        if (! $record) return null;
        return format_translatable($record->author_name, 'en') ?: 'Testimonial #' . $record->id;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Tabs::make('Testimonial Content')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('English (EN)')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('author_name.en')
                                            ->label('Client Name (English)')
                                            ->placeholder('e.g. Sara Al-Dossary')
                                            ->required(),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('city.en')
                                                    ->label('City / Location (English)')
                                                    ->placeholder('e.g. Riyadh'),

                                                Forms\Components\TextInput::make('occasion_tag.en')
                                                    ->label('Occasion Tag (English)')
                                                    ->placeholder('e.g. Wedding Anniversary'),
                                            ]),

                                        Forms\Components\Textarea::make('content.en')
                                            ->label('Review Quote (English)')
                                            ->placeholder('Ordered the Royal Crimson roses...')
                                            ->rows(4)
                                            ->required(),
                                    ]),

                                Forms\Components\Tabs\Tab::make('Arabic (عربي)')
                                    ->icon('heroicon-o-globe-alt')
                                    ->schema([
                                        Forms\Components\TextInput::make('author_name.ar')
                                            ->label('Client Name (Arabic)')
                                            ->placeholder('مثال: سارة الدوسري')
                                            ->extraInputAttributes(['dir' => 'rtl']),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('city.ar')
                                                    ->label('City / Location (Arabic)')
                                                    ->placeholder('مثال: الرياض')
                                                    ->extraInputAttributes(['dir' => 'rtl']),

                                                Forms\Components\TextInput::make('occasion_tag.ar')
                                                    ->label('Occasion Tag (Arabic)')
                                                    ->placeholder('مثال: ذكرى زواج')
                                                    ->extraInputAttributes(['dir' => 'rtl']),
                                            ]),

                                        Forms\Components\Textarea::make('content.ar')
                                            ->label('Review Quote (Arabic)')
                                            ->placeholder('طلبت باقة ورد رويال كريمسون...')
                                            ->rows(4)
                                            ->extraInputAttributes(['dir' => 'rtl']),
                                    ]),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Review Details & Display')
                            ->schema([
                                Forms\Components\Select::make('rating')
                                    ->label('Star Rating')
                                    ->options([
                                        '5.0' => '5.0 Stars (★★★★★)',
                                        '4.5' => '4.5 Stars (★★★★½)',
                                        '4.0' => '4.0 Stars (★★★★☆)',
                                        '3.5' => '3.5 Stars (★★★½☆)',
                                        '3.0' => '3.0 Stars (★★★☆☆)',
                                    ])
                                    ->default('5.0')
                                    ->required()
                                    ->native(false),

                                Forms\Components\FileUpload::make('avatar')
                                    ->label('Client Avatar / Photo')
                                    ->image()
                                    ->directory('testimonials')
                                    ->disk('public')
                                    ->avatar()
                                    ->alignCenter(),

                                Forms\Components\Toggle::make('is_verified')
                                    ->label('Verified Client Checkmark')
                                    ->helperText('Shows the verified icon next to client name')
                                    ->default(true),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Lower numbers appear first on carousel'),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active on Storefront')
                                    ->default(true),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Photo')
                    ->circular()
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=Client&background=047857&color=ffffff'),

                Tables\Columns\TextColumn::make('author_name')
                    ->label('Client Name')
                    ->formatStateUsing(fn ($record) => format_translatable($record->author_name, 'en') ?: '—')
                    ->description(fn ($record) => format_translatable($record->author_name, 'ar'))
                    ->searchable(query: function ($query, string $search) {
                        return $query->where('author_name', 'like', "%{$search}%");
                    }),

                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->formatStateUsing(fn ($record) => format_translatable($record->city, 'en') ?: '—'),

                Tables\Columns\TextColumn::make('occasion_tag')
                    ->label('Occasion Tag')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($record) => ($tag = format_translatable($record->occasion_tag, 'en')) ? '#' . $tag : '—'),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => '★ ' . number_format((float) $state, 1)),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Verified Buyers Only'),
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
            'index' => Pages\ListTestimonials::route('/'),
            'create' => Pages\CreateTestimonial::route('/create'),
            'edit' => Pages\EditTestimonial::route('/{record}/edit'),
        ];
    }
}
