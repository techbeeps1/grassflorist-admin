<?php

namespace App\Filament\Pages;

use App\Models\SubscriptionSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
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
    protected static ?string $title = 'Subscription Settings (إعدادات الاشتراكات)';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;

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
            'duration_mode' => $settings->duration_mode ?? 'customer_choice',
            'fixed_duration' => $settings->fixed_duration ?? 'until_cancelled',
            'allowed_durations' => $settings->allowed_durations ?? ['3_months', '6_months', '12_months', 'until_cancelled'],
            'customer_notes_enabled' => (bool)($settings->customer_notes_enabled ?? true),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Subscription Products (المنتجات المخصصة لعروض الاشتراكات)')
                    ->description('اختر المنتجات من كتالوج المتجر لعرضها كباقات اشتراك في صفحة (/flowers-subscription-deals). يتم استخدام تفاصيل المنتج الأصلية (الاسم، الصور، الوصف، السعر) مع تطبيق نسب الخصم المحددة أدناه.')
                    ->schema([
                        Select::make('subscription_product_ids')
                            ->label('Select Products for Subscriptions (اختيار منتجات باقات الاشتراكات)')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                return \App\Models\Product::query()
                                    ->where('is_visible', 1)
                                    ->orderBy('id', 'desc')
                                    ->limit(300)
                                    ->get()
                                    ->mapWithKeys(function ($p) {
                                        $name = $p->getTranslation('name', 'ar') ?: $p->getTranslation('name', 'en') ?: $p->name;
                                        return [$p->id => "#{$p->id} - {$name} (السعر الأصلي: {$p->price} ر.س)"];
                                    })
                                    ->toArray();
                            })
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\Product::query()
                                    ->where('is_visible', 1)
                                    ->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")
                                          ->orWhere('id', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($p) {
                                        $name = $p->getTranslation('name', 'ar') ?: $p->getTranslation('name', 'en') ?: $p->name;
                                        return [$p->id => "#{$p->id} - {$name} (السعر الأصلي: {$p->price} ر.س)"];
                                    })
                                    ->toArray();
                            })
                            ->helperText('يمكنك البحث واختيار منتجات الزهور التي ستكون متاحة كباقات اشتراك مباشر.')
                            ->required(),
                    ]),

                Section::make('Subscription Discounts by Duration (خصومات الاشتراكات حسب المدة)')
                    ->description('حدد نسبة الخصم المطبقة على السعر الأصلي لكل منتج عند اختيار العميل لمدة الاشتراك.')
                    ->schema([
                        TextInput::make('duration_discounts.1_month')
                            ->label('1 Month Discount (خصم شهر)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(0),

                        TextInput::make('duration_discounts.3_months')
                            ->label('3 Months Discount (خصم 3 أشهر)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(10),

                        TextInput::make('duration_discounts.6_months')
                            ->label('6 Months Discount (خصم 6 أشهر)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(15),

                        TextInput::make('duration_discounts.12_months')
                            ->label('12 Months Discount (خصم 12 شهراً)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(20),
                    ])
                    ->columns(4),

                Section::make('General Subscription Rules (القواعد العامة للاشتراكات)')
                    ->description('Enable or disable recurring subscriptions storewide, and configure the default recurring order discount.')
                    ->schema([
                        Toggle::make('is_enabled')
                            ->label('Enable Recurring Subscriptions (تفعيل نظام الاشتراكات)')
                            ->helperText('When enabled, subscriptions are available to customers for all eligible products.')
                            ->default(true),

                        TextInput::make('default_discount_percentage')
                            ->label('Default Starting Discount (%) (نسبة الخصم الأساسية)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->default(10.00)
                            ->helperText('Applied to recurring orders as base discount.'),
                    ])
                    ->columns(2),

                Section::make('Allowed Delivery Frequencies (التكرارات المتاحة للتوصيل)')
                    ->description('Select which recurring delivery frequencies are offered to customers.')
                    ->schema([
                        CheckboxList::make('allowed_frequencies')
                            ->label('Delivery Frequencies')
                            ->options([
                                'weekly' => 'Every Week / أسبوعياً (every 7 days)',
                                'biweekly' => 'Every 2 Weeks / كل أسبوعين (every 14 days)',
                                'monthly' => 'Every Month / شهرياً (every 30 days)',
                            ])
                            ->required()
                            ->columns(3),
                    ]),

                Section::make('Subscription Duration Configuration (إعدادات مدة خطة الاشتراك)')
                    ->description('Choose whether to fix the duration automatically or allow customers to choose their preferred duration.')
                    ->schema([
                        Radio::make('duration_mode')
                            ->label('Duration Mode (نظام تحديد مدة الاشتراك)')
                            ->options([
                                'customer_choice' => '👥 Let Customer Choose (السماح للعميل باختيار المدة في صفحة الباقة)',
                                'fixed' => '🔒 Fixed Duration (مدة ثابتة محددة مسبقاً)',
                            ])
                            ->default('customer_choice')
                            ->required()
                            ->live(),

                        Select::make('fixed_duration')
                            ->label('Fixed Plan Duration (المدة الثابتة للاشتراك)')
                            ->options([
                                'until_cancelled' => 'Until Cancelled (Auto-renew) / حتى الإلغاء (تجديد تلقائي مرن)',
                                '3_months' => '3 Months / 3 أشهر',
                                '6_months' => '6 Months / 6 أشهر',
                                '12_months' => '12 Months / سنة كاملة',
                            ])
                            ->default('until_cancelled')
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('duration_mode') === 'fixed'),

                        CheckboxList::make('allowed_durations')
                            ->label('Allowed Duration Options for Customers (الخيارات المتاحة لاختيار العميل)')
                            ->options([
                                '1_month' => '1 Month (شهر واحد)',
                                '3_months' => '3 Months (3 أشهر)',
                                '6_months' => '6 Months (6 أشهر)',
                                '12_months' => '12 Months (سنة كاملة)',
                            ])
                            ->columns(4)
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('duration_mode') === 'customer_choice'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Subscription Settings (حفظ إعدادات الاشتراكات)')
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
            'duration_mode' => $data['duration_mode'] ?? 'customer_choice',
            'fixed_duration' => $data['fixed_duration'] ?? 'until_cancelled',
            'allowed_durations' => $data['allowed_durations'] ?? ['1_month', '3_months', '6_months', '12_months'],
            'customer_notes_enabled' => (bool)($data['customer_notes_enabled'] ?? true),
        ]);
        $settings->save();

        Notification::make()
            ->title('Subscription Settings Saved (تم حفظ إعدادات الاشتراكات)')
            ->body('Subscription products and pricing rules updated successfully.')
            ->success()
            ->send();
    }
}
