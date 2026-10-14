<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\SubscriptionSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SubscriptionSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Subscription Settings';
    protected static ?string $title = 'Subscription Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.subscription-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $settings = SubscriptionSetting::current();

        $this->form->fill([
            'is_enabled' => (bool)$settings->is_enabled,
            'default_discount_percentage' => $settings->default_discount_percentage ?? 10.00,
            'subscription_product_ids' => $settings->subscription_product_ids ?? [],
            'duration_discounts' => $settings->duration_discounts ?? [
                '1_month' => 0,
                '3_months' => 10,
                '6_months' => 15,
                '12_months' => 20,
            ],
            'allowed_frequencies' => $settings->allowed_frequencies ?? ['weekly', 'biweekly', 'monthly'],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Subscription Products')
                    ->description('Select catalog products to feature as subscription packages on (/flowers-subscription-deals).')
                    ->schema([
                        Select::make('subscription_product_ids')
                            ->label('Featured Products for Subscriptions')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelsUsing(function (array $values): array {
                                return Product::whereIn('id', $values)
                                    ->get()
                                    ->mapWithKeys(function (Product $p) {
                                        $name = format_translatable($p->name, 'en') ?: format_translatable($p->name, 'ar') ?: ($p->sku ?: "Product #{$p->id}");
                                        return [$p->id => "#{$p->id} - {$name} (SAR {$p->price})"];
                                    })
                                    ->toArray();
                            })
                            ->getSearchResultsUsing(function (string $search): array {
                                return Product::query()
                                    ->where('is_visible', 1)
                                    ->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")
                                          ->orWhere('sku', 'like', "%{$search}%")
                                          ->orWhere('id', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function (Product $p) {
                                        $name = format_translatable($p->name, 'en') ?: format_translatable($p->name, 'ar') ?: ($p->sku ?: "Product #{$p->id}");
                                        return [$p->id => "#{$p->id} - {$name} (SAR {$p->price})"];
                                    })
                                    ->toArray();
                            })
                            ->options(function (): array {
                                $selectedIds = SubscriptionSetting::current()->subscription_product_ids ?: [];
                                return Product::query()
                                    ->where('is_visible', 1)
                                    ->orWhereIn('id', $selectedIds)
                                    ->orderBy('id', 'desc')
                                    ->limit(100)
                                    ->get()
                                    ->mapWithKeys(function (Product $p) {
                                        $name = format_translatable($p->name, 'en') ?: format_translatable($p->name, 'ar') ?: ($p->sku ?: "Product #{$p->id}");
                                        return [$p->id => "#{$p->id} - {$name} (SAR {$p->price})"];
                                    })
                                    ->toArray();
                            })
                            ->helperText('Search and select products to be available for recurring subscriptions.')
                            ->required(),
                    ]),

                Section::make('Subscription Discounts by Duration')
                    ->description('Set the percentage discount applied to the bouquet base price based on subscription duration.')
                    ->schema([
                        TextInput::make('duration_discounts.1_month')
                            ->label('1 Month Discount')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(0),

                        TextInput::make('duration_discounts.3_months')
                            ->label('3 Months Discount')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(10),

                        TextInput::make('duration_discounts.6_months')
                            ->label('6 Months Discount')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(15),

                        TextInput::make('duration_discounts.12_months')
                            ->label('12 Months Discount')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(20),
                    ])
                    ->columns(4),

                Section::make('General Subscription Rules')
                    ->description('Enable or disable recurring subscriptions storewide, and configure the base starting discount.')
                    ->schema([
                        Toggle::make('is_enabled')
                            ->label('Enable Recurring Subscriptions')
                            ->helperText('When enabled, subscriptions are available to customers for all featured products.')
                            ->default(true),

                        TextInput::make('default_discount_percentage')
                            ->label('Starting Display Discount (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->default(10.00)
                            ->helperText('Base discount shown on deal cards on the landing page.'),
                    ])
                    ->columns(2),

                Section::make('Allowed Delivery Frequencies')
                    ->description('Select which recurring delivery frequencies are offered to customers on subscription deals.')
                    ->schema([
                        CheckboxList::make('allowed_frequencies')
                            ->label('Delivery Frequencies')
                            ->options([
                                'weekly' => 'Every Week (every 7 days)',
                                'biweekly' => 'Every 2 Weeks (every 14 days)',
                                'monthly' => 'Every Month (every 30 days)',
                            ])
                            ->required()
                            ->columns(3),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Subscription Settings')
                ->submit('save')
                ->icon('heroicon-o-check'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = SubscriptionSetting::current();
        $settings->fill([
            'is_enabled' => (bool)($data['is_enabled'] ?? true),
            'default_discount_percentage' => (float)($data['default_discount_percentage'] ?? 10.00),
            'subscription_product_ids' => $data['subscription_product_ids'] ?? [],
            'duration_discounts' => $data['duration_discounts'] ?? [
                '1_month' => 0,
                '3_months' => 10,
                '6_months' => 15,
                '12_months' => 20,
            ],
            'allowed_frequencies' => $data['allowed_frequencies'] ?? ['weekly', 'biweekly', 'monthly'],
            'duration_mode' => 'customer_choice',
            'fixed_duration' => 'until_cancelled',
            'allowed_durations' => ['1_month', '3_months', '6_months', '12_months'],
            'customer_notes_enabled' => true,
        ]);
        $settings->save();

        Notification::make()
            ->title('Subscription Settings Saved')
            ->body('Subscription products and pricing rules updated successfully.')
            ->success()
            ->send();
    }
}
