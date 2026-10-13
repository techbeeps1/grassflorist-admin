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
            'allowed_frequencies' => $settings->allowed_frequencies ?? ['weekly', 'biweekly', 'monthly'],
            'duration_mode' => $settings->duration_mode ?? 'fixed',
            'fixed_duration' => $settings->fixed_duration ?? 'until_cancelled',
            'allowed_durations' => $settings->allowed_durations ?? ['3_months', '6_months', '12_months', 'until_cancelled'],
            'customer_notes_enabled' => (bool)($settings->customer_notes_enabled ?? true),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('General Subscription Rules (القواعد العامة للاشتراكات)')
                    ->description('Enable or disable recurring subscriptions storewide, and configure the default recurring order discount.')
                    ->schema([
                        Toggle::make('is_enabled')
                            ->label('Enable Recurring Subscriptions (تفعيل نظام الاشتراكات)')
                            ->helperText('When enabled, subscriptions are available to customers for all eligible products.')
                            ->default(true),

                        TextInput::make('default_discount_percentage')
                            ->label('Default Subscription Discount (%) (نسبة الخصم الافتراضية)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->default(10.00)
                            ->helperText('Applied to recurring orders unless overridden individually on a specific product.'),
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
                    ->description('Choose whether to fix the duration automatically (so customers are not asked) or allow customers to choose their preferred duration.')
                    ->schema([
                        Radio::make('duration_mode')
                            ->label('Duration Mode (نظام تحديد مدة الاشتراك)')
                            ->options([
                                'fixed' => '🔒 Fixed Duration (مدة ثابتة محددة مسبقاً - لا يتم سؤال العميل في المتجر)',
                                'customer_choice' => '👥 Let Customer Choose (السماح للعميل باختيار المدة في صفحة المنتج)',
                            ])
                            ->default('fixed')
                            ->required()
                            ->live()
                            ->helperText('Select whether duration is pre-determined by store settings or selected by the customer on the product page.'),

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
                            ->visible(fn (Forms\Get $get) => $get('duration_mode') === 'fixed')
                            ->helperText('All subscriptions will automatically run on this duration. The frontend will NOT prompt the customer for duration choice.'),

                        CheckboxList::make('allowed_durations')
                            ->label('Allowed Duration Options for Customers (الخيارات المتاحة لاختيار العميل)')
                            ->options([
                                '3_months' => '3 Months (3 أشهر)',
                                '6_months' => '6 Months (6 أشهر)',
                                '12_months' => '12 Months (سنة كاملة)',
                                'until_cancelled' => 'Until Cancelled / Auto-renew (حتى الإلغاء)',
                            ])
                            ->columns(2)
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('duration_mode') === 'customer_choice')
                            ->helperText('Check the duration options that will be displayed on the product page for the customer to choose.'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Subscription Settings (حفظ الإعدادات)')
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
            'allowed_frequencies' => $data['allowed_frequencies'] ?? ['weekly', 'biweekly', 'monthly'],
            'duration_mode' => $data['duration_mode'] ?? 'fixed',
            'fixed_duration' => $data['fixed_duration'] ?? 'until_cancelled',
            'allowed_durations' => $data['allowed_durations'] ?? ['3_months', '6_months', '12_months', 'until_cancelled'],
        ]);
        $settings->save();

        Notification::make()
            ->title('Subscription Settings Saved')
            ->body('Global subscription rules and duration configuration have been updated successfully.')
            ->success()
            ->send();
    }
}
