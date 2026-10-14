<?php

namespace App\Filament\Pages;

use App\Models\GlobalSetting;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;

class TaxCurrencySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Tax & Currency';
    protected static ?string $title = 'Tax & Currency Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;

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
            'vat_percentage' => $settings->vat_percentage ?? 15.00,
            'vat_registration_number' => $settings->vat_registration_number,
            'is_vat_included' => (bool)$settings->is_vat_included,
            'sar_to_usd_rate' => $settings->sar_to_usd_rate ?? 0.2667,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Saudi Value Added Tax (VAT) / ZATCA')
                    ->description('Configure the VAT rate applied across storefront checkout, order calculations, and tax invoices.')
                    ->schema([
                        TextInput::make('vat_percentage')
                            ->label('VAT Percentage (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(15.00)
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required()
                            ->helperText('Standard Saudi Arabia VAT is 15%. Dynamically updates checkout calculations and tax invoices.'),

                        TextInput::make('vat_registration_number')
                            ->label('ZATCA Tax Registration Number (TIN)')
                            ->placeholder('300000000000003')
                            ->helperText('15-digit Tax Identification Number registered with ZATCA (هيئة الزكاة والضريبة والجمارك).'),

                        Toggle::make('is_vat_included')
                            ->label('Product Prices Include VAT (Tax-Inclusive)')
                            ->helperText('OFF: 15% VAT is added on top of the subtotal at checkout. ON: Product catalog prices already include 15% VAT.')
                            ->default(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Multi-Currency & USD Exchange Rate')
                    ->description('Default store currency is Saudi Riyal (SAR). Configure the conversion rate for customers viewing USD.')
                    ->schema([
                        TextInput::make('sar_to_usd_rate')
                            ->label('SAR to USD Multiplier (1 SAR = ? USD)')
                            ->numeric()
                            ->step(0.0001)
                            ->default(0.2667)
                            ->required()
                            ->helperText('Multiplier to convert SAR to USD (e.g. 0.2667 means 100 SAR = $26.67 USD). Click button to live fetch.')
                            ->suffixAction(
                                FormAction::make('fetchLiveRate')
                                    ->label('Fetch Live Rate')
                                    ->icon('heroicon-m-arrow-path')
                                    ->color('success')
                                    ->tooltip('Fetch live exchange rate from open exchange API')
                                    ->action(function (Set $set) {
                                        try {
                                            $res = Http::timeout(6)->get('https://open.er-api.com/v6/latest/SAR');
                                            if ($res->successful()) {
                                                $rate = $res->json()['rates']['USD'] ?? null;
                                                if ($rate) {
                                                    $rounded = round((float)$rate, 4);
                                                    $set('sar_to_usd_rate', $rounded);
                                                    $record = GlobalSetting::current();
                                                    $record->update([
                                                        'sar_to_usd_rate' => $rounded,
                                                        'last_currency_rate_fetch_at' => now(),
                                                    ]);
                                                    Notification::make()
                                                        ->title('Live Currency Rate Fetched')
                                                        ->body("1 SAR = {$rounded} USD (1 USD ≈ " . round(1 / $rounded, 2) . " SAR)")
                                                        ->success()
                                                        ->send();
                                                    return;
                                                }
                                            }
                                            throw new \Exception('API did not return a valid USD rate');
                                        } catch (\Exception $e) {
                                            Notification::make()
                                                ->title('Failed to fetch rate')
                                                ->body($e->getMessage())
                                                ->danger()
                                                ->send();
                                        }
                                    })
                            ),

                        Placeholder::make('last_currency_rate_fetch_at_display')
                            ->label('Last Live Rate Fetched')
                            ->content(function () {
                                $record = GlobalSetting::current();
                                return $record?->last_currency_rate_fetch_at
                                    ? Carbon::parse($record->last_currency_rate_fetch_at)->diffForHumans()
                                    : 'Never fetched automatically (Default 0.2667)';
                            }),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Tax & Currency Settings')
                ->submit('save')
                ->icon('heroicon-o-check'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = GlobalSetting::current();
        $settings->update([
            'vat_percentage' => (float)($data['vat_percentage'] ?? 15.00),
            'vat_registration_number' => $data['vat_registration_number'] ?? null,
            'is_vat_included' => (bool)($data['is_vat_included'] ?? false),
            'sar_to_usd_rate' => (float)($data['sar_to_usd_rate'] ?? 0.2667),
        ]);

        Notification::make()
            ->title('Tax & Currency Settings Saved')
            ->body('VAT rate and currency exchange configuration updated successfully.')
            ->success()
            ->send();
    }
}
