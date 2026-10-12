<?php

namespace App\Filament\Resources\EmailLogResource\Pages;

use App\Filament\Resources\EmailLogResource;
use App\Models\NotificationLog;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListEmailLogs extends ListRecords
{
    protected static string $resource = EmailLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Emails (كل الرسائل)')
                ->badge(NotificationLog::where('channel', 'email')->count())
                ->icon('heroicon-o-envelope'),

            'sent' => Tab::make('Sent (تم الإرسال)')
                ->badge(NotificationLog::where('channel', 'email')->where('status', 'sent')->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'sent')),

            'failed' => Tab::make('Failed (فشل الإرسال)')
                ->badge(NotificationLog::where('channel', 'email')->where('status', 'failed')->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'failed')),
        ];
    }
}
