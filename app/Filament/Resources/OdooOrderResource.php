<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OdooOrderResource\Pages;
use App\Models\OdooSyncLog;
use App\Models\Order;
use App\Services\Odoo\OdooService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OdooOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Odoo ERP Sync';

    protected static ?string $modelLabel = 'Odoo Sync Order';

    protected static ?string $pluralModelLabel = 'Odoo ERP Orders';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'odoo-orders';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        if ($user->isAdmin()) return true;
        return $user->hasPermission('odoo_orders') || $user->hasPermission('orders');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('payment_status', ['paid', 'completed']);
    }

    public static function getNavigationBadge(): ?string
    {
        $pendingCount = static::getModel()::whereIn('payment_status', ['paid', 'completed'])
            ->where(function ($q) {
                $q->whereNull('odoo_sync_status')
                  ->orWhere('odoo_sync_status', 'pending')
                  ->orWhere('odoo_sync_status', 'failed');
            })
            ->count();

        return $pendingCount > 0 ? (string)$pendingCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Order $record) => trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) . ' • ' . $record->email)
                    ->url(fn (Order $record) => route('filament.admin.resources.orders.edit', ['record' => $record->id])),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Order Date')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->getStateUsing(function (Order $record) {
                        $isPaypal = strtolower((string)($record->payment_method ?? '')) === 'paypal';
                        
                        if ($isPaypal) {
                            $rate = (float)($record->exchange_rate ?: 0.2667);
                            $sarTotal = (float)($record->sar_amount ?: $record->total_amount);
                            $usdVal = ($record->currency_amount && (float)$record->currency_amount < $sarTotal)
                                ? (float)$record->currency_amount
                                : round($sarTotal * $rate, 2);
                                
                            return '$' . number_format($usdVal, 2) . ' USD';
                        }

                        $sarVal = (float)($record->sar_amount ?: $record->total_amount);
                        return number_format($sarVal, 2) . ' SAR';
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->colors([
                        'success' => fn ($state) => in_array(strtolower((string)$state), ['paid', 'completed']),
                        'warning' => fn ($state) => in_array(strtolower((string)$state), ['pending', 'pending_payment']),
                        'danger' => fn ($state) => in_array(strtolower((string)$state), ['failed', 'cancelled']),
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst((string)($state ?: 'Unpaid')))
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Fulfillment')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', (string)($state ?: 'Pending'))))
                    ->sortable(),

                Tables\Columns\TextColumn::make('odoo_sync_status')
                    ->label('Odoo Status')
                    ->badge()
                    ->colors([
                        'success' => 'synced',
                        'warning' => 'pending',
                        'danger' => 'failed',
                        'gray' => 'bypassed',
                    ])
                    ->icons([
                        'heroicon-m-check-circle' => 'synced',
                        'heroicon-m-clock' => 'pending',
                        'heroicon-m-x-circle' => 'failed',
                        'heroicon-m-no-symbol' => 'bypassed',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'synced' => 'Synced (أودو جاهز)',
                        'failed' => 'Failed (فشل)',
                        'bypassed' => 'Bypassed (مستثنى)',
                        default => 'Pending Sync (بانتظار)',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('odoo_synced_at')
                    ->label('Synced At')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice')
                    ->label('Invoice')
                    ->badge()
                    ->state(fn (Order $record) => !empty($record->odoo_invoice_pdf_url) ? 'Odoo PDF' : 'Tax Invoice')
                    ->color(fn (Order $record) => !empty($record->odoo_invoice_pdf_url) ? 'success' : 'info')
                    ->icon(fn (Order $record) => !empty($record->odoo_invoice_pdf_url) ? 'heroicon-o-arrow-down-tray' : 'heroicon-o-document-text')
                    ->tooltip(fn (Order $record) => !empty($record->odoo_invoice_pdf_url) ? 'Click to open Odoo ERP PDF Invoice' : 'Click to view Saudi ZATCA Tax Invoice')
                    ->url(function (Order $record) {
                        if (empty($record->order_key)) {
                            $record->order_key = 'wc_order_' . \Illuminate\Support\Str::random(20);
                            $record->saveQuietly();
                        }
                        return $record->odoo_invoice_pdf_url ?: route('orders.tax_invoice', ['id' => $record->id, 'key' => $record->order_key]);
                    }, shouldOpenInNewTab: true),

                Tables\Columns\TextColumn::make('odoo_error')
                    ->label('Notes / Error')
                    ->limit(35)
                    ->tooltip(fn (Order $record) => $record->odoo_error)
                    ->placeholder('None')
                    ->color(fn (Order $record) => $record->odoo_sync_status === 'failed' ? 'danger' : 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('odoo_sync_status')
                    ->label('Odoo Status')
                    ->options([
                        'synced' => 'Synced Successfully',
                        'pending' => 'Pending Sync',
                        'failed' => 'Failed Sync',
                        'bypassed' => 'Bypassed (Test Accounts)',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Fulfillment Status')
                    ->options([
                        'processing' => 'Processing (قيد التجهيز)',
                        'completed' => 'Completed (مكتمل)',
                        'delivered' => 'Delivered (تم التوصيل)',
                        'shipped' => 'Shipped (تم الشحن)',
                    ]),
            ])
            ->actions([
                // 1. Trigger Manual Sync / Retry
                Tables\Actions\Action::make('sync_to_odoo')
                    ->label(fn (Order $record) => $record->odoo_sync_status === 'failed' ? 'Retry Sync' : 'Sync to Odoo')
                    ->icon('heroicon-o-arrow-path')
                    ->color(fn (Order $record) => $record->odoo_sync_status === 'failed' ? 'danger' : 'primary')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Order $record) => "Sync Order #{$record->order_number} to Odoo ERP")
                    ->modalDescription(fn (Order $record) => "This will send the order payload and line items to Odoo ERP, and download the official Invoice PDF.")
                    ->action(function (Order $record) {
                        if (!$record->isPaid()) {
                            Notification::make()
                                ->title('Cannot Sync Unpaid Order')
                                ->body("Order #{$record->order_number} is not paid. Only paid orders can be synced to Odoo.")
                                ->danger()
                                ->send();
                            return;
                        }

                        $odooService = app(OdooService::class);
                        $adminName = auth()->user()?->name ?: 'Admin';
                        $result = $odooService->syncOrder($record, "admin:{$adminName}");

                        if (!empty($result['success']) && !empty($result['bypassed'])) {
                            Notification::make()
                                ->title('Order Bypassed')
                                ->body($result['message'])
                                ->warning()
                                ->send();
                        } elseif (!empty($result['success'])) {
                            Notification::make()
                                ->title('Odoo Sync Succeeded')
                                ->body($result['message'])
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Odoo Sync Failed')
                                ->body($result['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                // 2. View Official Tax Invoice (Always available!)
                Tables\Actions\Action::make('view_tax_invoice')
                    ->label('Tax Invoice')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->tooltip('View official Saudi ZATCA Tax Invoice with QR Code')
                    ->url(function (Order $record) {
                        if (empty($record->order_key)) {
                            $record->order_key = 'wc_order_' . \Illuminate\Support\Str::random(20);
                            $record->saveQuietly();
                        }
                        return route('orders.tax_invoice', ['id' => $record->id, 'key' => $record->order_key]);
                    })
                    ->openUrlInNewTab(),

                // 3. Download Odoo PDF Invoice (When generated by Odoo)
                Tables\Actions\Action::make('download_odoo_invoice')
                    ->label('Odoo PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->tooltip('Open / Download official Odoo ERP PDF Invoice')
                    ->url(fn (Order $record) => $record->odoo_invoice_pdf_url)
                    ->openUrlInNewTab()
                    ->visible(fn (Order $record) => !empty($record->odoo_invoice_pdf_url)),

                // 3. View Sync Logs Modal (Full History)
                Tables\Actions\Action::make('view_logs')
                    ->label('Logs')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->badge(fn (Order $record) => $record->odooSyncLogs()->count() ?: null)
                    ->tooltip(fn (Order $record) => "View all sync attempts (" . $record->odooSyncLogs()->count() . ")")
                    ->modalHeading(fn (Order $record) => "Odoo Sync History for Order #{$record->order_number}")
                    ->modalWidth('4xl')
                    ->infolist(function (Order $record) {
                        $logs = $record->odooSyncLogs()->orderBy('id', 'desc')->get();

                        if ($logs->isEmpty()) {
                            return Infolist::make()
                                ->schema([
                                    Infolists\Components\Section::make('No Synchronization History')
                                        ->icon('heroicon-o-information-circle')
                                        ->schema([
                                            Infolists\Components\TextEntry::make('no_history')
                                                ->label('')
                                                ->state(fn () => 'No synchronization attempts have been recorded for this order yet.')
                                                ->color('gray'),
                                        ]),
                                ]);
                        }

                        $total = $logs->count();
                        $latestLog = $logs->first();

                        $schema = [
                            Infolists\Components\Section::make("Sync Overview — Order #{$record->order_number}")
                                ->icon('heroicon-o-arrow-path')
                                ->schema([
                                    Infolists\Components\Grid::make(4)
                                        ->schema([
                                            Infolists\Components\TextEntry::make('total_attempts')
                                                ->label('Total Sync Attempts')
                                                ->state(fn () => "{$total} attempt(s)")
                                                ->badge()
                                                ->color('info'),

                                            Infolists\Components\TextEntry::make('current_status')
                                                ->label('Current Status')
                                                ->state(fn () => match ($record->odoo_sync_status) {
                                                    'synced' => 'Synced (متزامن)',
                                                    'failed' => 'Failed (فشل)',
                                                    'bypassed' => 'Bypassed (مستثنى)',
                                                    default => 'Pending (بالانتظار)',
                                                })
                                                ->badge()
                                                ->color(fn () => match ($record->odoo_sync_status) {
                                                    'synced' => 'success',
                                                    'failed' => 'danger',
                                                    'bypassed' => 'gray',
                                                    default => 'warning',
                                                }),

                                            Infolists\Components\TextEntry::make('last_synced_at')
                                                ->label('Last Attempt At')
                                                ->state(fn () => $latestLog?->created_at ? $latestLog->created_at->format('d M Y, h:i:s A') : '—'),

                                            Infolists\Components\TextEntry::make('invoice_download')
                                                ->label('Invoice')
                                                ->badge()
                                                ->state(fn () => $record->odoo_invoice_pdf_url ? 'Open Odoo PDF' : 'Open Tax Invoice')
                                                ->color(fn () => $record->odoo_invoice_pdf_url ? 'success' : 'info')
                                                ->url(function () use ($record) {
                                                    if (empty($record->order_key)) {
                                                        $record->order_key = 'wc_order_' . \Illuminate\Support\Str::random(20);
                                                        $record->saveQuietly();
                                                    }
                                                    return $record->odoo_invoice_pdf_url ?: route('orders.tax_invoice', ['id' => $record->id, 'key' => $record->order_key]);
                                                }, shouldOpenInNewTab: true),
                                        ]),
                                ]),
                        ];

                        foreach ($logs as $index => $log) {
                            $attemptNum = $total - $index;
                            $isLatest = $index === 0;
                            $statusTitle = match ($log->status) {
                                'success' => 'Synced Successfully',
                                'failed' => 'Sync Failed',
                                'bypassed' => 'Bypassed',
                                default => ucfirst($log->status),
                            };
                            $badgeColor = match ($log->status) {
                                'success' => 'success',
                                'failed' => 'danger',
                                'bypassed' => 'gray',
                                default => 'warning',
                            };
                            $timeStr = $log->created_at ? $log->created_at->format('d M Y, h:i:s A') : '—';
                            $timeDiff = $log->created_at ? "({$log->created_at->diffForHumans()})" : '';
                            $heading = "Attempt #{$attemptNum}" . ($isLatest ? ' (Latest)' : '') . " — {$statusTitle} — {$timeStr} {$timeDiff}";

                            $schema[] = Infolists\Components\Section::make($heading)
                                ->icon(match ($log->status) {
                                    'success' => 'heroicon-o-check-circle',
                                    'failed' => 'heroicon-o-x-circle',
                                    'bypassed' => 'heroicon-o-no-symbol',
                                    default => 'heroicon-o-clock',
                                })
                                ->collapsible()
                                ->collapsed(!$isLatest)
                                ->schema([
                                    Infolists\Components\Grid::make(3)
                                        ->schema([
                                            Infolists\Components\TextEntry::make("log_{$log->id}_status")
                                                ->label('Result')
                                                ->state(function () use ($log) { return ucfirst($log->status); })
                                                ->badge()
                                                ->color($badgeColor),

                                            Infolists\Components\TextEntry::make("log_{$log->id}_synced_by")
                                                ->label('Initiated By')
                                                ->state(function () use ($log) { return ucfirst(str_replace('_', ' ', $log->synced_by ?: 'System')); }),

                                            Infolists\Components\TextEntry::make("log_{$log->id}_created_at")
                                                ->label('Timestamp')
                                                ->state(function () use ($log) { return $log->created_at ? $log->created_at->format('d M Y, h:i:s A') : '—'; }),
                                        ]),

                                    Infolists\Components\TextEntry::make("log_{$log->id}_error")
                                        ->label('Failure Details / Reason')
                                        ->state(function () use ($log) { return $log->error_message ?: 'None'; })
                                        ->color('danger')
                                        ->visible(!empty($log->error_message)),

                                    Infolists\Components\TextEntry::make("log_{$log->id}_invoice")
                                        ->label('Stored Invoice PDF')
                                        ->state(function () use ($log) { return $log->invoice_pdf_url ?: 'None'; })
                                        ->url(function () use ($log) { return $log->invoice_pdf_url; }, shouldOpenInNewTab: true)
                                        ->color('primary')
                                        ->visible(!empty($log->invoice_pdf_url)),

                                    Infolists\Components\Section::make("Request Sent to Odoo (Payload) - Attempt #{$attemptNum}")
                                        ->schema([
                                            Infolists\Components\TextEntry::make("log_{$log->id}_req")
                                                ->label('')
                                                ->state(function () use ($log) {
                                                    return $log->request_payload 
                                                        ? json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
                                                        : 'No request payload recorded';
                                                })
                                                ->extraAttributes(['class' => 'font-mono text-xs bg-gray-900 text-emerald-400 p-3 rounded-lg overflow-x-auto max-h-60']),
                                        ])
                                        ->collapsible()
                                        ->collapsed(),

                                    Infolists\Components\Section::make("Response from Odoo - Attempt #{$attemptNum}")
                                        ->schema([
                                            Infolists\Components\TextEntry::make("log_{$log->id}_res")
                                                ->label('')
                                                ->state(function () use ($log) {
                                                    return $log->response_payload 
                                                        ? json_encode($log->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
                                                        : 'No response payload recorded';
                                                })
                                                ->extraAttributes(['class' => 'font-mono text-xs bg-gray-900 text-sky-400 p-3 rounded-lg overflow-x-auto max-h-60']),
                                        ])
                                        ->collapsible()
                                        ->collapsed(),
                                ]);
                        }

                        return Infolist::make()->schema($schema);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_sync_to_odoo')
                        ->label('Sync Selected Orders to Odoo')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Batch Sync Orders to Odoo ERP')
                        ->modalDescription('Only orders with confirmed payment will be synchronized. Test and bypassed accounts will be marked accordingly.')
                        ->action(function (Collection $records) {
                            $odooService = app(OdooService::class);
                            $adminName = auth()->user()?->name ?: 'Admin';
                            $syncedCount = 0;
                            $failedCount = 0;
                            $skippedCount = 0;

                            foreach ($records as $order) {
                                if (!$order->isPaid()) {
                                    $skippedCount++;
                                    continue;
                                }

                                $result = $odooService->syncOrder($order, "bulk:{$adminName}");
                                if (!empty($result['success']) && !empty($result['bypassed'])) {
                                    $skippedCount++;
                                } elseif (!empty($result['success'])) {
                                    $syncedCount++;
                                } else {
                                    $failedCount++;
                                }
                            }

                            Notification::make()
                                ->title('Batch Sync Completed')
                                ->body("{$syncedCount} orders successfully synced, {$failedCount} failed, {$skippedCount} skipped/unpaid/bypassed.")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOdooOrders::route('/'),
        ];
    }
}
