<?php

namespace App\Filament\Resources\NotificationLogResource\Pages;

use App\Filament\Resources\NotificationLogResource;
use App\Models\NotificationLog;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListNotificationLogs extends ListRecords
{
    protected static string $resource = NotificationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Notifications (الكل)')
                ->badge(NotificationLog::count())
                ->icon('heroicon-o-list-bullet'),

            'email' => Tab::make('Email Logs (البريد الإلكتروني)')
                ->badge(NotificationLog::where('channel', 'email')->count())
                ->badgeColor('info')
                ->icon('heroicon-o-envelope')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('channel', 'email')),

            'sms' => Tab::make('SMS Logs (الرسائل القصيرة)')
                ->badge(NotificationLog::where('channel', 'sms')->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('channel', 'sms')),
        ];
    }
}
