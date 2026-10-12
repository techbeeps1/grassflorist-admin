<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmsLogResource\Pages;
use App\Models\NotificationLog;
use App\Services\Notifications\MsegatSmsService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SmsLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;
    protected static ?string $slug = 'sms-logs';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Logs';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'SMS Logs';
    protected static ?string $modelLabel = 'SMS Log';
    protected static ?string $pluralModelLabel = 'SMS Logs';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return true;
        return $user->hasPermission('sms_logs') || $user->hasPermission('notification_logs');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('channel', 'sms');
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
                    ->label('Phone Number')
                    ->searchable()
                    ->copyable()
                    ->tooltip('Click to copy phone number'),

                Tables\Columns\TextColumn::make('subject_or_sender')
                    ->label('Sender ID')
                    ->badge()
                    ->color('gray')
                    ->placeholder('GRASS'),

                Tables\Columns\TextColumn::make('content')
                    ->label('SMS Message Body')
                    ->limit(55)
                    ->tooltip(fn (NotificationLog $record) => $record->content),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Gateway Error / Response')
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
                    ->options(fn () => NotificationLog::where('channel', 'sms')->distinct()->pluck('event_key', 'event_key')->toArray()),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('View SMS')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (NotificationLog $record) => 'SMS Log #' . $record->id . ' — ' . $record->recipient)
                    ->modalWidth('3xl')
                    ->infolist([
                        Infolists\Components\Section::make('SMS Dispatch Summary')
                            ->schema([
                                Infolists\Components\Grid::make(4)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn (string $state): string => $state === 'sent' ? 'success' : 'danger'),

                                        Infolists\Components\TextEntry::make('event_key')
                                            ->label('Trigger Event')
                                            ->badge(),

                                        Infolists\Components\TextEntry::make('subject_or_sender')
                                            ->label('Sender ID'),

                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Timestamp')
                                            ->dateTime('d M Y, h:i:s A'),
                                    ]),

                                Infolists\Components\Grid::make(2)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('recipient')
                                            ->label('Recipient Mobile Number')
                                            ->copyable(),

                                        Infolists\Components\TextEntry::make('ip_address')
                                            ->label('IP Address')
                                            ->placeholder('N/A'),
                                    ]),
                            ]),

                        Infolists\Components\Section::make('Gateway Error Details')
                            ->schema([
                                Infolists\Components\TextEntry::make('error_message')
                                    ->label('Exception / Gateway Error Code')
                                    ->color('danger')
                                    ->extraAttributes(['class' => 'font-mono text-xs text-red-600 bg-red-50 p-3 rounded-lg border border-red-200']),
                            ])
                            ->visible(fn (NotificationLog $record) => !empty($record->error_message)),

                        Infolists\Components\Section::make('Dispatched SMS Content & Gateway Response')
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
                    ->modalHeading('Resend SMS Message')
                    ->modalDescription(fn (NotificationLog $record) => "Are you sure you want to resend this SMS message to {$record->recipient}?")
                    ->action(function (NotificationLog $record, MsegatSmsService $smsService) {
                        try {
                            $result = $smsService->send(
                                $record->recipient,
                                $record->content,
                                'retry_' . $record->event_key
                            );

                            if ($result['success']) {
                                Notification::make()
                                    ->title('SMS Resent Successfully!')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('SMS Resend Failed')
                                    ->body($result['message'])
                                    ->danger()
                                    ->send();
                            }
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
            'index' => Pages\ListSmsLogs::route('/'),
        ];
    }
}
