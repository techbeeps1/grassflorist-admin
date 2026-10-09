<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoogleReviewResource\Pages;
use App\Models\GoogleReview;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GoogleReviewResource extends Resource
{
    protected static ?string $model = GoogleReview::class;

    protected static ?string $navigationGroup = 'Shop';
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Google Reviews';
    protected static ?string $modelLabel = 'Google Review';
    protected static ?string $pluralModelLabel = 'Google Reviews';
    protected static ?int $navigationSort = 15;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('google_reviews') || auth()->user()?->isAdmin() || true;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('is_visible', true)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Review Information (بيانات التقييم)')
                    ->description('Manage original reviewer content directly from Google Maps.')
                    ->schema([
                        Forms\Components\TextInput::make('author_name')
                            ->label('Reviewer Name (اسم صاحب التقييم)')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('rating')
                            ->label('Rating (التقييم)')
                            ->options([
                                5 => '⭐⭐⭐⭐⭐ 5 Stars (ممتاز)',
                                4 => '⭐⭐⭐⭐ 4 Stars (جيد جداً)',
                                3 => '⭐⭐⭐ 3 Stars (جيد)',
                                2 => '⭐⭐ 2 Stars (مقبول)',
                                1 => '⭐ 1 Star (ضعيف)',
                            ])
                            ->default(5)
                            ->required(),

                        Forms\Components\Textarea::make('comment')
                            ->label('Review Text (نص التقييم)')
                            ->helperText('Original review text submitted by the customer on Google Maps.')
                            ->rows(4)
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('language')
                            ->label('Review Language (لغة التقييم)')
                            ->options([
                                'ar' => '🇸🇦 العربية (Arabic)',
                                'en' => '🇬🇧 English (الإنجليزية)',
                            ])
                            ->default('ar')
                            ->required(),

                        Forms\Components\TextInput::make('author_photo_url')
                            ->label('Reviewer Photo URL (رابط صورة صاحب التقييم)')
                            ->url()
                            ->placeholder('https://...'),

                        Forms\Components\TextInput::make('relative_time_description')
                            ->label('Time Description (توقيت التقييم)')
                            ->placeholder('e.g. قبل أسبوعين / 2 weeks ago')
                            ->default('مؤخراً'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_visible')
                                    ->label('Show on Storefront (عرض في المتجر)')
                                    ->helperText('Turn off to hide this review from product details pages.')
                                    ->default(true),

                                Forms\Components\Toggle::make('is_featured')
                                    ->label('Featured Review (تقييم مميز)')
                                    ->default(true),
                            ]),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->circular()
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=G&background=1b3d2f&color=fff'),

                Tables\Columns\TextColumn::make('author_name')
                    ->label('Reviewer')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->badge()
                    ->color(fn ($state) => $state >= 4 ? 'success' : ($state >= 3 ? 'warning' : 'danger'))
                    ->formatStateUsing(fn ($state) => "⭐ {$state}/5")
                    ->sortable(),

                Tables\Columns\TextColumn::make('comment')
                    ->label('Review Text')
                    ->limit(65)
                    ->tooltip(fn (GoogleReview $record) => $record->comment)
                    ->searchable(),

                Tables\Columns\TextColumn::make('language')
                    ->label('Lang')
                    ->badge()
                    ->color(fn ($state) => $state === 'ar' ? 'primary' : 'info')
                    ->formatStateUsing(fn ($state) => $state === 'ar' ? '🇸🇦 AR' : '🇬🇧 EN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('relative_time_description')
                    ->label('Date / Time')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\ToggleColumn::make('is_visible')
                    ->label('Visible')
                    ->onIcon('heroicon-m-eye')
                    ->offIcon('heroicon-m-eye-slash')
                    ->onColor('success')
                    ->offColor('danger'),

                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Featured'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visibility')
                    ->trueLabel('Visible only')
                    ->falseLabel('Hidden only'),

                Tables\Filters\SelectFilter::make('language')
                    ->options([
                        'ar' => '🇸🇦 Arabic',
                        'en' => '🇬🇧 English',
                    ]),

                Tables\Filters\SelectFilter::make('rating')
                    ->options([
                        5 => '5 Stars',
                        4 => '4 Stars',
                        3 => '3 Stars',
                        2 => '2 Stars',
                        1 => '1 Star',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('export_selected_csv')
                        ->label('Export Selected to CSV (تصدير المحدد)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn ($records) => \App\Services\GoogleReviewCsvService::exportCsv($records)),
                    Tables\Actions\BulkAction::make('hide_selected')
                        ->label('Hide Selected')
                        ->icon('heroicon-o-eye-slash')
                        ->action(fn ($records) => $records->each->update(['is_visible' => false])),
                    Tables\Actions\BulkAction::make('show_selected')
                        ->label('Show Selected')
                        ->icon('heroicon-o-eye')
                        ->action(fn ($records) => $records->each->update(['is_visible' => true])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoogleReviews::route('/'),
            'create' => Pages\CreateGoogleReview::route('/create'),
            'edit' => Pages\EditGoogleReview::route('/{record}/edit'),
        ];
    }
}
