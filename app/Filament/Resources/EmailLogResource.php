<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailLogResource\Pages;
use App\Models\NotificationLog;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

class EmailLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;
    protected static ?string $slug = 'email-logs';
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Logs';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Email Logs';
    protected static ?string $modelLabel = 'Email Log';
    protected static ?string $pluralModelLabel = 'Email Logs';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return true;
        return $user->hasPermission('email_logs') || $user->hasPermission('notification_logs');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('channel', 'email');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'sent' => 'heroicon-o-check-circle',
                        'failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    }),

                Tables\Columns\TextColumn::make('event_key')
                    ->label('Trigger Event')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                Tables\Columns\TextColumn::make('recipient')
                    ->label('Recipient Email')
                    ->searchable()
                    ->copyable()
                    ->tooltip('Click to copy email address'),

                Tables\Columns\TextColumn::make('subject_or_sender')
                    ->label('Subject')
                    ->searchable()
                    ->limit(45)
                    ->tooltip(fn (NotificationLog $record) => $record->subject_or_sender),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error Detail')
                    ->limit(35)
                    ->color('danger')
                    ->placeholder('None')
                    ->tooltip(fn (NotificationLog $record) => $record->error_message),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dispatched At')
                    ->dateTime('d M Y, h:i:s A')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'sent' => 'Sent Successfully',
                        'failed' => 'Failed Delivery',
                    ]),

                Tables\Filters\SelectFilter::make('event_key')
                    ->label('Trigger Event')
                    ->options(fn () => NotificationLog::where('channel', 'email')->distinct()->pluck('event_key', 'event_key')->toArray()),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('View Email')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (NotificationLog $record) => 'Email Log #' . $record->id . ' — ' . ($record->subject_or_sender ?: 'Details'))
                    ->modalWidth('4xl')
                    ->infolist([
                        Infolists\Components\Section::make('Delivery Information')
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('status')
                                            ->label('Delivery Status')
                                            ->badge()
                                            ->color(fn (string $state): string => $state === 'sent' ? 'success' : 'danger'),

                                        Infolists\Components\TextEntry::make('event_key')
                                            ->label('Event')
                                            ->badge(),

                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Dispatched Timestamp')
                                            ->dateTime('d M Y, h:i:s A'),
                                    ]),

                                Infolists\Components\Grid::make(3)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('recipient')
                                            ->label('Recipient Email')
                                            ->copyable(),

                                        Infolists\Components\TextEntry::make('subject_or_sender')
                                            ->label('Subject Line')
                                            ->columnSpan(2),
                                    ]),
                            ]),

                        Infolists\Components\Section::make('Failure Reason / Error Details')
                            ->schema([
                                Infolists\Components\TextEntry::make('error_message')
                                    ->label('Exception Error')
                                    ->color('danger')
                                    ->extraAttributes(['class' => 'font-mono text-xs text-red-600 bg-red-50 p-3 rounded-lg border border-red-200']),
                            ])
                            ->visible(fn (NotificationLog $record) => !empty($record->error_message)),

                        Infolists\Components\Section::make('Email HTML Preview')
                            ->schema([
                                Infolists\Components\ViewEntry::make('content_preview')
                                    ->label('')
                                    ->view('filament.components.notification-preview'),
                            ]),
                    ]),

                Tables\Actions\Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Resend Email')
                    ->modalDescription(fn (NotificationLog $record) => "Are you sure you want to re-send this email to {$record->recipient}?")
                    ->action(function (NotificationLog $record) {
                        try {
                            Mail::html($record->content, function ($msg) use ($record) {
                                $msg->to($record->recipient)
                                    ->subject($record->subject_or_sender ?: 'Grass Florist Notification');
                            });

                            NotificationLog::create([
                                'channel' => 'email',
                                'event_key' => 'retry_' . $record->event_key,
                                'recipient' => $record->recipient,
                                'subject_or_sender' => $record->subject_or_sender,
                                'content' => $record->content,
                                'status' => 'sent',
                                'error_message' => null,
                            ]);

                            Notification::make()
                                ->title('Email Resent Successfully!')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Resend Exception')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

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
            'index' => Pages\ListEmailLogs::route('/'),
        ];
    }
}
