<?php

namespace App\Filament\Pages;

use App\Models\GlobalSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OdooSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationLabel = 'Odoo ERP Settings';
    protected static ?string $title = 'Odoo ERP Integration Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 4;

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
            'odoo_enabled' => (bool)$settings->odoo_enabled,
            'odoo_auto_sync_on_paid' => (bool)$settings->odoo_auto_sync_on_paid,
            'odoo_api_url' => $settings->odoo_api_url ?? 'https://odoo.grassflorist.com/website/api/order/upsert',
            'odoo_api_token' => $settings->odoo_api_token,
            'odoo_bypass_domains' => $settings->odoo_bypass_domains,
            'odoo_bypass_emails' => $settings->odoo_bypass_emails,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Odoo Connection & API Credentials')
                    ->description('Configure Odoo ERP order synchronization endpoint and authentication token.')
                    ->schema([
                        Toggle::make('odoo_enabled')
                            ->label('Enable Odoo ERP Sync')
                            ->helperText('When enabled, paid orders will be synchronized with Odoo ERP.')
                            ->default(true),

                        Toggle::make('odoo_auto_sync_on_paid')
                            ->label('Auto-Sync on Payment Capture')
                            ->helperText('Trigger sync immediately when an order is paid or marked processing.')
                            ->default(true),

                        TextInput::make('odoo_api_url')
                            ->label('Odoo API Endpoint URL')
                            ->default('https://odoo.grassflorist.com/website/api/order/upsert')
                            ->url()
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('odoo_api_token')
                            ->label('Odoo Bearer Token')
                            ->password()
                            ->revealable()
                            ->columnSpanFull()
                            ->helperText('Used in Authorization: Bearer <token> HTTP header.'),
                    ])
                    ->columns(2),

                Section::make('Testing & Developer Bypass Rules')
                    ->description('Define customer accounts, emails, or email domains that should NEVER be synced to Odoo.')
                    ->schema([
                        Textarea::make('odoo_bypass_domains')
                            ->label('Bypass Email Domains')
                            ->placeholder("techbeeps.com\ntechbeeps.co.in")
                            ->helperText('Orders from these email domains will be skipped. Enter one per line or comma-separated.')
                            ->rows(3),

                        Textarea::make('odoo_bypass_emails')
                            ->label('Bypass Specific Emails')
                            ->placeholder("admin@example.com\ntest@example.com")
                            ->helperText('Orders with these exact emails will be skipped. Enter one per line or comma-separated.')
                            ->rows(3),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Odoo Settings')
                ->submit('save')
                ->icon('heroicon-o-check'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = GlobalSetting::current();
        $settings->update([
            'odoo_enabled' => (bool)($data['odoo_enabled'] ?? false),
            'odoo_auto_sync_on_paid' => (bool)($data['odoo_auto_sync_on_paid'] ?? false),
            'odoo_api_url' => $data['odoo_api_url'] ?? null,
            'odoo_api_token' => $data['odoo_api_token'] ?? null,
            'odoo_bypass_domains' => $data['odoo_bypass_domains'] ?? null,
            'odoo_bypass_emails' => $data['odoo_bypass_emails'] ?? null,
        ]);

        Notification::make()
            ->title('Odoo ERP Settings Saved')
            ->body('Odoo API connection and bypass rules updated successfully.')
            ->success()
            ->send();
    }
}
