<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\GlobalSetting;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\View;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;

class StoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'Store Settings';
    protected static ?string $title = 'Store Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'store-settings';

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
            // Tax & Currency
            'vat_percentage' => $settings->vat_percentage ?? 15.00,
            'vat_registration_number' => $settings->vat_registration_number,
            'is_vat_included' => (bool) $settings->is_vat_included,
            'sar_to_usd_rate' => $settings->sar_to_usd_rate ?? 0.2667,

            // Cart Upsell ("Make it perfect")
            'cart_upsell_enabled' => (bool) ($settings->cart_upsell_enabled ?? true),
            'cart_upsell_title_en' => $settings->cart_upsell_title_en ?? 'Make it perfect',
            'cart_upsell_title_ar' => $settings->cart_upsell_title_ar ?? 'اجعلها مثالية',
            'cart_upsell_category_ids' => $settings->cart_upsell_category_ids ?? [],
            'cart_upsell_limit_per_category' => $settings->cart_upsell_limit_per_category ?? 10,

            // Restricted Categories (Single Buy Restriction)
            'restricted_categories_enabled' => (bool) ($settings->restricted_categories_enabled ?? true),
            'restricted_category_ids' => $settings->restricted_category_ids ?? [],
            'qualifying_category_ids' => $settings->qualifying_category_ids ?? [],
            'restricted_categories_message_en' => $settings->restricted_categories_message_en ?? 'You must add at least one flower product to your cart before adding cake, chocolates, or balloons.',
            'restricted_categories_message_ar' => $settings->restricted_categories_message_ar ?? 'يجب إضافة منتج زهور واحد على الأقل إلى سلتك قبل إضافة الكيك أو الشوكولاتة أو البالونات.',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('StoreSettingsTabs')
                    ->persistTabInQueryString()
                    ->tabs([
                        // =========================================================================
                        // TAB 1: TAX & CURRENCY
                        // =========================================================================
                        Tabs\Tab::make('Tax & Currency')
                            ->icon('heroicon-o-receipt-percent')
                            ->badge('VAT & Currency')
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
                                                                    $rounded = round((float) $rate, 4);
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
                            ]),

                        // =========================================================================
                        // TAB 2: CART UPSELL ("MAKE IT PERFECT")
                        // =========================================================================
                        Tabs\Tab::make('Cart Upsell ("Make it perfect")')
                            ->icon('heroicon-o-sparkles')
                            ->badge('Floward Style')
                            ->schema([
                                Section::make('Make it perfect (Cart & Checkout Upsell)')
                                    ->description('Configure cross-sell add-on products (e.g. Cakes, Chocolates, Balloons, Greeting Sticks) displayed on the Cart page and Checkout popup modal, just like Floward.')
                                    ->schema([
                                        Toggle::make('cart_upsell_enabled')
                                            ->label('Enable "Make it perfect" Upsell Section')
                                            ->helperText('When enabled, customers will see recommended add-on products on Cart & Checkout.')
                                            ->default(true)
                                            ->columnSpanFull(),

                                        TextInput::make('cart_upsell_title_en')
                                            ->label('Section Title (English)')
                                            ->default('Make it perfect')
                                            ->placeholder('Make it perfect')
                                            ->required(),

                                        TextInput::make('cart_upsell_title_ar')
                                            ->label('Section Title (Arabic)')
                                            ->default('اجعلها مثالية')
                                            ->placeholder('اجعلها مثالية')
                                            ->required(),

                                        Select::make('cart_upsell_category_ids')
                                            ->label('Upsell Add-on Categories')
                                            ->helperText('Select the categories whose products will be displayed in tabs (e.g., Cake & Chocolate, Balloons, Suggestions).')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->options(function () {
                                                return Category::query()
                                                    ->orderBy('id', 'asc')
                                                    ->get()
                                                    ->mapWithKeys(function ($cat) {
                                                        $name = $cat->name;
                                                        $en = is_array($name) ? ($name['en'] ?? '') : (is_string($name) ? $name : '');
                                                        $ar = is_array($name) ? ($name['ar'] ?? '') : '';
                                                        $label = ($en ? $en : '') . ($ar ? " ({$ar})" : '') . " [ID: {$cat->id}]";
                                                        return [$cat->id => $label];
                                                    })
                                                    ->toArray();
                                            })
                                            ->columnSpanFull(),

                                        TextInput::make('cart_upsell_limit_per_category')
                                            ->label('Max Products per Category')
                                            ->numeric()
                                            ->default(10)
                                            ->helperText('Maximum number of in-stock items to load under each category tab.')
                                            ->columnSpan(1),
                                    ])
                                    ->columns(2),
                            ]),

                        // =========================================================================
                        // TAB 3: SHIPPING & DELIVERY (HUB)
                        // =========================================================================
                        Tabs\Tab::make('Shipping & Delivery')
                            ->icon('heroicon-o-truck')
                            ->badge('Methods, Slots & Dates')
                            ->schema([
                                View::make('filament.pages.store-settings-shipping-tab'),
                            ]),

                        // =========================================================================
                        // TAB 4: RESTRICTED CATEGORIES (SINGLE BUY RESTRICTION)
                        // =========================================================================
                        Tabs\Tab::make('Restricted Categories')
                            ->icon('heroicon-o-shield-exclamation')
                            ->badge('Single Buy Restriction')
                            ->schema([
                                Section::make('Category Single Purchase Restriction')
                                    ->description('Configure categories that cannot be bought alone without a main/qualifying product in the cart (e.g. Add-on items like Cakes, Chocolates, Balloons).')
                                    ->schema([
                                        Toggle::make('restricted_categories_enabled')
                                            ->label('Enable Single Buy Restriction')
                                            ->helperText('When enabled, customers cannot place an order or checkout if their cart contains ONLY restricted items without any qualifying product.')
                                            ->default(true)
                                            ->columnSpanFull(),

                                        Select::make('restricted_category_ids')
                                            ->label('Restricted Categories (Cannot Be Purchased Alone)')
                                            ->helperText('Products belonging to these categories will require at least one qualifying product in the cart.')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->options(function () {
                                                return Category::query()
                                                    ->orderBy('id', 'asc')
                                                    ->get()
                                                    ->mapWithKeys(function ($cat) {
                                                        $name = $cat->name;
                                                        $en = is_array($name) ? ($name['en'] ?? '') : (is_string($name) ? $name : '');
                                                        $ar = is_array($name) ? ($name['ar'] ?? '') : '';
                                                        $label = ($en ? $en : '') . ($ar ? " ({$ar})" : '') . " [ID: {$cat->id}]";
                                                        return [$cat->id => $label];
                                                    })
                                                    ->toArray();
                                            })
                                            ->columnSpanFull(),

                                        Select::make('qualifying_category_ids')
                                            ->label('Qualifying / Main Categories (Optional)')
                                            ->helperText('Categories that qualify the cart (e.g. Flowers, Bouquets). If left empty, ANY category outside the restricted list will automatically qualify the cart.')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->options(function () {
                                                return Category::query()
                                                    ->orderBy('id', 'asc')
                                                    ->get()
                                                    ->mapWithKeys(function ($cat) {
                                                        $name = $cat->name;
                                                        $en = is_array($name) ? ($name['en'] ?? '') : (is_string($name) ? $name : '');
                                                        $ar = is_array($name) ? ($name['ar'] ?? '') : '';
                                                        $label = ($en ? $en : '') . ($ar ? " ({$ar})" : '') . " [ID: {$cat->id}]";
                                                        return [$cat->id => $label];
                                                    })
                                                    ->toArray();
                                            })
                                            ->columnSpanFull(),

                                        TextInput::make('restricted_categories_message_en')
                                            ->label('Restriction Warning Message (English)')
                                            ->default('You must add at least one flower product to your cart before adding cake, chocolates, or balloons.')
                                            ->placeholder('You must add at least one flower product to your cart before adding cake, chocolates, or balloons.')
                                            ->helperText('Message shown in English when a customer tries to checkout with only restricted products.')
                                            ->columnSpanFull(),

                                        TextInput::make('restricted_categories_message_ar')
                                            ->label('Restriction Warning Message (Arabic)')
                                            ->default('يجب إضافة منتج زهور واحد على الأقل إلى سلتك قبل إضافة الكيك أو الشوكولاتة أو البالونات.')
                                            ->placeholder('يجب إضافة منتج زهور واحد على الأقل إلى سلتك قبل إضافة الكيك أو الشوكولاتة أو البالونات.')
                                            ->helperText('Message shown in Arabic when a customer tries to checkout with only restricted products.')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Store Settings')
                ->submit('save')
                ->icon('heroicon-o-check'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = GlobalSetting::current();
        $settings->update([
            // Tax & Currency
            'vat_percentage' => (float) ($data['vat_percentage'] ?? 15.00),
            'vat_registration_number' => $data['vat_registration_number'] ?? null,
            'is_vat_included' => (bool) ($data['is_vat_included'] ?? false),
            'sar_to_usd_rate' => (float) ($data['sar_to_usd_rate'] ?? 0.2667),

            // Cart Upsell ("Make it perfect")
            'cart_upsell_enabled' => (bool) ($data['cart_upsell_enabled'] ?? false),
            'cart_upsell_title_en' => $data['cart_upsell_title_en'] ?? 'Make it perfect',
            'cart_upsell_title_ar' => $data['cart_upsell_title_ar'] ?? 'اجعلها مثالية',
            'cart_upsell_category_ids' => $data['cart_upsell_category_ids'] ?? [],
            'cart_upsell_limit_per_category' => (int) ($data['cart_upsell_limit_per_category'] ?? 10),

            // Restricted Categories (Single Buy Restriction)
            'restricted_categories_enabled' => (bool) ($data['restricted_categories_enabled'] ?? false),
            'restricted_category_ids' => $data['restricted_category_ids'] ?? [],
            'qualifying_category_ids' => $data['qualifying_category_ids'] ?? [],
            'restricted_categories_message_en' => $data['restricted_categories_message_en'] ?? null,
            'restricted_categories_message_ar' => $data['restricted_categories_message_ar'] ?? null,
        ]);

        Notification::make()
            ->title('Store Settings Saved')
            ->body('Store settings (Tax, currency, cart upsell, and category restrictions) updated successfully.')
            ->success()
            ->send();
    }
}
