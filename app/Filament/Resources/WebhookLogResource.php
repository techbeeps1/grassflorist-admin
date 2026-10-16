<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WebhookLogResource\Pages;
use App\Models\WebhookLog;
use App\Services\Webhooks\WebhookService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WebhookLogResource extends Resource
{
    protected static ?string $model = WebhookLog::class;
    protected static ?string $slug = 'webhook-logs';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-top-right-on-square';
    protected static ?string $navigationGroup = 'Logs';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Webhook Logs';
    protected static ?string $modelLabel = 'Webhook Log';
    protected static ?string $pluralModelLabel = 'Webhook Logs';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return true;
        return $user->hasPermission('webhook_logs') || $user->hasPermission('global_settings') || ($user->role ?? null) === 'admin';
    }

    public static function canCreate(): bool
    {
        return false;
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
                    ->label('Delivery')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'success' => 'heroicon-o-check-circle',
                        'failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-clock',
                    }),

                Tables\Columns\TextColumn::make('response_status')
                    ->label('HTTP Code')
                    ->badge()
                    ->formatStateUsing(fn (?int $state) => $state ? "HTTP {$state}" : 'Failed / Timeout')
                    ->color(fn (?int $state): string => match (true) {
                        $state >= 200 && $state < 300 => 'success',
                        $state >= 400 && $state < 500 => 'warning',
                        $state >= 500 => 'danger',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('event')
                    ->label('Trigger Event')
                    ->badge()
                    ->searchable()
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'order.') => 'primary',
                        str_starts_with($state, 'user.') => 'info',
                        str_starts_with($state, 'subscription.') => 'purple',
                        str_starts_with($state, 'newsletter.') => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('reference_id')
                    ->label('Ref / Entity')
                    ->searchable()
                    ->placeholder('-')
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('url')
                    ->label('Destination URL')
                    ->limit(40)
                    ->tooltip(fn (WebhookLog $record) => $record->url)
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Latency')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? "{$state} ms" : '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('attempts')
                    ->label('Attempts')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dispatched At')
                    ->dateTime('d M Y, h:i:s A')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'success' => 'Successful (2xx)',
                        'failed' => 'Failed Delivery',
                    ]),

                Tables\Filters\SelectFilter::make('event')
                    ->label('Event')
                    ->options([
                        'order.created' => 'order.created',
                        'order.status_updated' => 'order.status_updated',
                        'order.paid' => 'order.paid',
                        'user.registered' => 'user.registered',
                        'subscription.created' => 'subscription.created',
                        'newsletter.subscribed' => 'newsletter.subscribed',
                        'user.password_changed' => 'user.password_changed',
                        'test.ping' => 'test.ping',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('Payload & Details')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (WebhookLog $record) => "Webhook Log #{$record->id} — {$record->event}")
                    ->modalWidth('4xl')
                    ->infolist([
                        Infolists\Components\Section::make('Delivery Summary')
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('event')
                                            ->label('Event Type')
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('status')
                                            ->label('Delivery Status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'success' => 'success',
                                                'failed' => 'danger',
                                                default => 'warning',
                                            }),
                                        Infolists\Components\TextEntry::make('response_status')
                                            ->label('HTTP Status Code')
                                            ->badge()
                                            ->formatStateUsing(fn (?int $state) => $state ? "HTTP {$state}" : 'Connection Error / Timeout'),
                                        Infolists\Components\TextEntry::make('reference_id')
                                            ->label('Entity Reference')
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('duration_ms')
                                            ->label('Latency')
                                            ->formatStateUsing(fn (?int $state) => $state !== null ? "{$state} ms" : '-'),
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Triggered At')
                                            ->dateTime('d M Y, h:i:s A'),
                                    ]),
                                Infolists\Components\TextEntry::make('url')
                                    ->label('Webhook Target Endpoint')
                                    ->columnSpanFull()
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('error_message')
                                    ->label('Error Detail')
                                    ->color('danger')
                                    ->visible(fn (WebhookLog $record) => !empty($record->error_message))
                                    ->columnSpanFull(),
                            ]),

                        Infolists\Components\Section::make('Request Payload Sent')
                            ->schema([
                                Infolists\Components\TextEntry::make('formatted_payload')
                                    ->label('')
                                    ->formatStateUsing(fn (string $state) => "<pre class=\"p-3 bg-gray-900 text-green-400 font-mono text-xs rounded-xl overflow-x-auto max-h-96 select-all\">" . e($state) . "</pre>")
                                    ->html()
                                    ->columnSpanFull(),
                            ])
                            ->collapsible(),

                        Infolists\Components\Section::make('3rd Party Response Received')
                            ->schema([
                                Infolists\Components\TextEntry::make('formatted_response')
                                    ->label('')
                                    ->placeholder('No response body returned from endpoint')
                                    ->formatStateUsing(fn (string $state) => "<pre class=\"p-3 bg-gray-900 text-blue-300 font-mono text-xs rounded-xl overflow-x-auto max-h-96 select-all\">" . e($state) . "</pre>")
                                    ->html()
                                    ->columnSpanFull(),
                            ])
                            ->collapsible(),
                    ]),

                Tables\Actions\Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Resend Webhook')
                    ->modalDescription('Are you sure you want to resend this exact payload to the webhook destination URL now?')
                    ->action(function (WebhookLog $record) {
                        $newLog = WebhookService::resend($record);
                        if ($newLog->status === 'success') {
                            Notification::make()
                                ->title('Webhook Resent Successfully')
                                ->body("Endpoint returned HTTP {$newLog->response_status} in {$newLog->duration_ms}ms.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Webhook Resend Failed')
                                ->body($newLog->error_message ?: "Endpoint returned HTTP {$newLog->response_status}.")
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
            'index' => Pages\ListWebhookLogs::route('/'),
        ];
    }
}
