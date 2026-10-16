<?php

namespace App\Filament\Resources\WebhookLogResource\Pages;

use App\Filament\Resources\WebhookLogResource;
use App\Models\GlobalSetting;
use App\Services\Webhooks\WebhookService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWebhookLogs extends ListRecords
{
    protected static string $resource = WebhookLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_ping')
                ->label('Send Test Ping')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->form([
                    Forms\Components\TextInput::make('test_url')
                        ->label('Target Webhook URL')
                        ->placeholder('https://webhook.site/... or leave blank to use configured URL')
                        ->default(fn () => GlobalSetting::current()->webhook_default_url)
                        ->url()
                        ->helperText('Leave empty to test your Global Settings Default Webhook URL.'),
                ])
                ->action(function (array $data) {
                    $res = WebhookService::sendTestPing($data['test_url'] ?? null);
                    if ($res['success']) {
                        Notification::make()
                            ->title('Webhook Ping Succeeded')
                            ->body($res['message'])
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Webhook Ping Failed')
                            ->body($res['message'])
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
