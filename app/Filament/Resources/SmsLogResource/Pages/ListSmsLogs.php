<?php

namespace App\Filament\Resources\SmsLogResource\Pages;

use App\Filament\Resources\SmsLogResource;
use App\Models\NotificationLog;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSmsLogs extends ListRecords
{
    protected static string $resource = SmsLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All SMS (كل الرسائل)')
                ->badge(NotificationLog::where('channel', 'sms')->count())
                ->icon('heroicon-o-chat-bubble-left-right'),

            'sent' => Tab::make('Sent (تم الإرسال)')
                ->badge(NotificationLog::where('channel', 'sms')->where('status', 'sent')->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'sent')),

            'failed' => Tab::make('Failed (فشل الإرسال)')
                ->badge(NotificationLog::where('channel', 'sms')->where('status', 'failed')->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'failed')),
        ];
    }
}
