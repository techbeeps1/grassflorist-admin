<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsletterSubscriberResource\Pages;
use App\Models\NewsletterSubscriber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;
    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';
    protected static ?string $navigationGroup = 'Subscribers';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Subscribers';
    protected static ?string $modelLabel = 'Subscriber';
    protected static ?string $pluralModelLabel = 'Subscribers';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'active')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Subscriber Details')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('locale')
                            ->label('Language Preference')
                            ->options([
                                'ar' => 'Arabic (العربية)',
                                'en' => 'English',
                            ])
                            ->default('ar')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Subscription Status')
                            ->options([
                                'active' => 'Active Subscriber',
                                'unsubscribed' => 'Unsubscribed',
                            ])
                            ->default('active')
                            ->required(),

                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->disabled()
                            ->maxLength(45),

                        Forms\Components\DateTimePicker::make('subscribed_at')
                            ->label('Subscribed At')
                            ->default(now()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('export_csv')
                    ->label('Export CSV (تصدير CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->tooltip('Download complete subscribers list as CSV for Excel')
                    ->action(function () {
                        $subscribers = NewsletterSubscriber::orderBy('id', 'desc')->get();
                        $filename = 'subscribers_' . date('Y-m-d_His') . '.csv';

                        return response()->streamDownload(function () use ($subscribers) {
                            $handle = fopen('php://output', 'w');
                            // UTF-8 BOM so Arabic characters display properly in MS Excel
                            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

                            fputcsv($handle, [
                                'ID',
                                'Email',
                                'Language',
                                'Status',
                                'IP Address',
                                'Subscribed At',
                            ]);

                            foreach ($subscribers as $s) {
                                fputcsv($handle, [
                                    $s->id,
                                    $s->email,
                                    strtoupper($s->locale ?? 'AR'),
                                    ucfirst($s->status ?? 'active'),
                                    $s->ip_address ?? '',
                                    $s->subscribed_at
                                        ? $s->subscribed_at->format('Y-m-d H:i:s')
                                        : ($s->created_at ? $s->created_at->format('Y-m-d H:i:s') : ''),
                                ]);
                            }
                            fclose($handle);
                        }, $filename, [
                            'Content-Type' => 'text/csv; charset=UTF-8',
                            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                        ]);
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Subscriber Email')
                    ->icon('heroicon-o-envelope')
                    ->searchable()
                    ->copyable()
                    ->sortable()
                    ->tooltip('Click to copy email'),

                Tables\Columns\TextColumn::make('locale')
                    ->label('Language')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? 'AR'))
                    ->color(fn (?string $state): string => $state === 'ar' ? 'primary' : 'info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'active'))
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'unsubscribed' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): string => match ($state) {
                        'active' => 'heroicon-o-check-circle',
                        'unsubscribed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    }),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('subscribed_at')
                    ->label('Subscribed Date')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('locale')
                    ->label('Language')
                    ->options([
                        'ar' => 'Arabic (العربية)',
                        'en' => 'English',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'unsubscribed' => 'Unsubscribed',
                    ]),
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
            'index' => Pages\ListNewsletterSubscribers::route('/'),
            'create' => Pages\CreateNewsletterSubscriber::route('/create'),
            'edit' => Pages\EditNewsletterSubscriber::route('/{record}/edit'),
        ];
    }
}
