<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaqResource\Pages;
use App\Models\Faq;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static ?string $navigationGroup = 'Pages';
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'FAQ';
    protected static ?string $pluralModelLabel = 'FAQs';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('faqs') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        if (! $record) return null;
        return format_translatable($record->question, 'en') ?: 'FAQ #' . $record->id;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Tabs::make('FAQ Content')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('English (EN)')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('category.en')
                                            ->label('Category / Topic (English)')
                                            ->placeholder('e.g. Ordering & Delivery, Special Services, Gifting'),

                                        Forms\Components\TextInput::make('question.en')
                                            ->label('Question (English)')
                                            ->placeholder('e.g. How long does it take to deliver the order?')
                                            ->required(),

                                        Forms\Components\Textarea::make('answer.en')
                                            ->label('Answer (English)')
                                            ->placeholder('We offer a fast, same-day delivery service...')
                                            ->rows(4)
                                            ->required(),
                                    ]),

                                Forms\Components\Tabs\Tab::make('Arabic (عربي)')
                                    ->icon('heroicon-o-globe-alt')
                                    ->schema([
                                        Forms\Components\TextInput::make('category.ar')
                                            ->label('Category / Topic (Arabic)')
                                            ->placeholder('مثال: الطلب والتوصيل، خدمات خاصة، الإهداء')
                                            ->extraInputAttributes(['dir' => 'rtl']),

                                        Forms\Components\TextInput::make('question.ar')
                                            ->label('Question (Arabic)')
                                            ->placeholder('مثال: كم يستغرق توصيل الطلب؟')
                                            ->extraInputAttributes(['dir' => 'rtl']),

                                        Forms\Components\Textarea::make('answer.ar')
                                            ->label('Answer (Arabic)')
                                            ->placeholder('نقدم خدمة توصيل سريعة في نفس اليوم...')
                                            ->rows(4)
                                            ->extraInputAttributes(['dir' => 'rtl']),
                                    ]),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Settings & Visibility')
                            ->schema([
                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Lower numbers appear first on storefront accordion'),

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
                Tables\Columns\TextColumn::make('question')
                    ->label('Question')
                    ->wrap()
                    ->formatStateUsing(fn ($record) => format_translatable($record->question, 'en') ?: '—')
                    ->description(fn ($record) => format_translatable($record->question, 'ar'))
                    ->searchable(query: function ($query, string $search) {
                        return $query->where('question', 'like', "%{$search}%");
                    }),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($record) => format_translatable($record->category, 'en') ?: 'General'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
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
            'index' => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit' => Pages\EditFaq::route('/{record}/edit'),
        ];
    }
}
