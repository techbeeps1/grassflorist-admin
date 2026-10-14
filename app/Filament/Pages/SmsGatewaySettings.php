<?php

namespace App\Filament\Pages;

use App\Models\GlobalSetting;
use App\Services\Notifications\MsegatSmsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SmsGatewaySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'SMS Gateway';
    protected static ?string $title = 'SMS Gateway Settings (Msegat)';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.settings-page';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || (auth()->user()?->hasPermission('global_settings') ?? false);
    }

    public function mount(): void
    {
        $settings = GlobalSetting::current();

        $this->form->fill([
            'msegat_sms_enabled' => (bool)$settings->msegat_sms_enabled,
            'msegat_username' => $settings->msegat_username,
            'msegat_api_key' => $settings->msegat_api_key,
            'msegat_user_sender' => $settings->msegat_user_sender,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Msegat.com SMS Gateway Integration')
                    ->description('Configure Msegat Saudi SMS gateway credentials to send automated order, delivery, and customer verification SMS.')
                    ->headerActions([
                        FormAction::make('check_balance')
                            ->label('Check Msegat Balance')
                            ->icon('heroicon-o-currency-dollar')
                            ->color('info')
                            ->action(function (MsegatSmsService $smsService) {
                                $res = $smsService->checkCredits();
                                if ($res['success']) {
                                    Notification::make()
                                        ->title('Msegat Balance Active')
                                        ->body($res['message'])
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Msegat Balance Check Failed')
                                        ->body($res['message'])
                                        ->danger()
                                        ->send();
                                }
                            }),
                        FormAction::make('test_sms')
                            ->label('Send Test SMS')
                            ->icon('heroicon-o-paper-airplane')
                            ->color('success')
                            ->form([
                                TextInput::make('test_mobile')
                                    ->label('Recipient Mobile Number')
                                    ->placeholder('0501234567 or 966501234567')
                                    ->required(),
                                TextInput::make('test_msg')
                                    ->label('Message Text')
                                    ->default('تجربة إرسال رسالة نصية من متجر غراس فلوريست عبر بوابة مسجات - Grass Florist Test SMS')
                                    ->required(),
                            ])
                            ->action(function (array $data, MsegatSmsService $smsService) {
                                $res = $smsService->send($data['test_mobile'], $data['test_msg'], 'test_msegat_connection');
                                if ($res['success']) {
                                    Notification::make()
                                        ->title('Test SMS Delivered')
                                        ->body("Message sent successfully to {$data['test_mobile']}")
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Test SMS Failed')
                                        ->body($res['message'])
                                        ->danger()
                                        ->persistent()
                                        ->send();
                                }
                            }),
                    ])
                    ->schema([
                        Toggle::make('msegat_sms_enabled')
                            ->label('Enable Msegat SMS Gateway')
                            ->helperText('Turn on to send live SMS for orders, inquiries, and delivery updates.')
                            ->default(false)
                            ->columnSpanFull(),

                        TextInput::make('msegat_username')
                            ->label('Msegat Account Username')
                            ->placeholder('e.g. grassflorist')
                            ->helperText('Your registered username on msegat.com'),

                        TextInput::make('msegat_api_key')
                            ->label('Msegat API Key')
                            ->password()
                            ->revealable()
                            ->placeholder('e.g. 7f89cxxxxxxxxxxxxxx')
                            ->helperText('Your private API Key generated from Msegat account settings'),

                        TextInput::make('msegat_user_sender')
                            ->label('Approved Sender Name (Sender ID)')
                            ->placeholder('Grass')
                            ->helperText('Registered Sender ID approved in Msegat (English letters/numbers only)')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save SMS Gateway Settings')
                ->submit('save')
                ->icon('heroicon-o-check'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = GlobalSetting::current();
        $settings->update([
            'msegat_sms_enabled' => (bool)($data['msegat_sms_enabled'] ?? false),
            'msegat_username' => $data['msegat_username'] ?? null,
            'msegat_api_key' => $data['msegat_api_key'] ?? null,
            'msegat_user_sender' => $data['msegat_user_sender'] ?? null,
        ]);

        Notification::make()
            ->title('SMS Gateway Settings Saved')
            ->body('Msegat SMS gateway credentials and status updated successfully.')
            ->success()
            ->send();
    }
}
