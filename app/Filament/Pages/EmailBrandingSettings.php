<?php

namespace App\Filament\Pages;

use App\Models\GlobalSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class EmailBrandingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Email Branding';
    protected static ?string $title = 'Email Templates Branding & Theme';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 5;

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
            'email_logo' => $settings->email_logo,
            'email_custom_logo_url' => $settings->email_custom_logo_url,
            'email_title_en' => $settings->email_title_en ?? 'Grass Florist',
            'email_title_ar' => $settings->email_title_ar ?? 'غراس فلوريست',
            'email_subtitle_en' => $settings->email_subtitle_en ?? 'Luxury Floral Atelier & Curated Gifting',
            'email_subtitle_ar' => $settings->email_subtitle_ar ?? 'متجر الزهور والهدايا الفاخرة',
            'email_header_bg_color' => $settings->email_header_bg_color ?? '#2D3F33',
            'email_body_bg_color' => $settings->email_body_bg_color ?? '#FAF8F5',
            'email_card_bg_color' => $settings->email_card_bg_color ?? '#FAF8F5',
            'email_accent_color' => $settings->email_accent_color ?? '#8fae2a',
            'email_footer_name_en' => $settings->email_footer_name_en ?? 'Grass Florist Atelier — Saudi Arabia',
            'email_footer_name_ar' => $settings->email_footer_name_ar ?? 'مؤسسة غراس السعودية للزهور والهدايا',
            'email_footer_address_en' => $settings->email_footer_address_en ?? 'Jeddah, Kingdom of Saudi Arabia',
            'email_footer_address_ar' => $settings->email_footer_address_ar ?? 'جدة، المملكة العربية السعودية',
            'email_footer_phone' => $settings->email_footer_phone ?? '+966 55 513 4211',
            'email_footer_email' => $settings->email_footer_email ?? 'info@grassflorist.com',
            'email_footer_copyright_en' => $settings->email_footer_copyright_en ?? 'All rights reserved.',
            'email_footer_copyright_ar' => $settings->email_footer_copyright_ar ?? 'جميع الحقوق محفوظة.',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Email Header & Logo')
                    ->description('Customize the visual logo and brand title of all transactional and notification emails.')
                    ->schema([
                        FileUpload::make('email_logo')
                            ->label('Email Header Logo File')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('settings/emails')
                            ->visibility('public')
                            ->helperText('Upload image file (PNG, SVG, WEBP). Used if direct URL is empty.')
                            ->columnSpan(1),

                        TextInput::make('email_custom_logo_url')
                            ->label('Or Enter Logo Direct URL (CDN / External)')
                            ->placeholder('https://example.com/images/logo.png')
                            ->url()
                            ->helperText('Enter logo URL from another website or CDN. If provided, this URL will be used in emails.')
                            ->columnSpan(1),

                        TextInput::make('email_title_en')
                            ->label('Brand Title (English)')
                            ->default('Grass Florist'),

                        TextInput::make('email_title_ar')
                            ->label('Brand Title (Arabic)')
                            ->default('غراس فلوريست'),

                        TextInput::make('email_subtitle_en')
                            ->label('Brand Subtitle (English)')
                            ->default('Luxury Floral Atelier & Curated Gifting'),

                        TextInput::make('email_subtitle_ar')
                            ->label('Brand Subtitle (Arabic)')
                            ->default('متجر الزهور والهدايا الفاخرة'),
                    ])
                    ->columns(2),

                Section::make('Color Palette & Theme')
                    ->description('Select the colors for email header, page background, cards, and action buttons.')
                    ->schema([
                        ColorPicker::make('email_header_bg_color')
                            ->label('Header Background Color')
                            ->default('#2D3F33')
                            ->helperText('Default: #2D3F33 (Forest Green)'),

                        ColorPicker::make('email_body_bg_color')
                            ->label('Email Page Background')
                            ->default('#FAF8F5')
                            ->helperText('Default: #FAF8F5 (Soft Ivory)'),

                        ColorPicker::make('email_card_bg_color')
                            ->label('Card Background Color')
                            ->default('#FAF8F5')
                            ->helperText('Default: #FAF8F5 (Warm Cream)'),

                        ColorPicker::make('email_accent_color')
                            ->label('Button & Accent Color')
                            ->default('#8fae2a')
                            ->helperText('Default: #8fae2a (Olive Leaf Green)'),
                    ])
                    ->columns(2),

                Section::make('Email Footer Details')
                    ->description('Contact info, legal store name, and copyright notice displayed in the email footer.')
                    ->schema([
                        TextInput::make('email_footer_name_en')
                            ->label('Store Legal Name (English)')
                            ->default('Grass Florist Atelier — Saudi Arabia'),

                        TextInput::make('email_footer_name_ar')
                            ->label('Store Legal Name (Arabic)')
                            ->default('مؤسسة غراس السعودية للزهور والهدايا'),

                        TextInput::make('email_footer_address_en')
                            ->label('Footer Address (English)')
                            ->default('Jeddah, Kingdom of Saudi Arabia'),

                        TextInput::make('email_footer_address_ar')
                            ->label('Footer Address (Arabic)')
                            ->default('جدة، المملكة العربية السعودية'),

                        TextInput::make('email_footer_phone')
                            ->label('Contact Phone')
                            ->default('+966 55 513 4211'),

                        TextInput::make('email_footer_email')
                            ->label('Contact Email')
                            ->default('info@grassflorist.com'),

                        TextInput::make('email_footer_copyright_en')
                            ->label('Custom Copyright (English)')
                            ->placeholder('All rights reserved.'),

                        TextInput::make('email_footer_copyright_ar')
                            ->label('Custom Copyright (Arabic)')
                            ->placeholder('جميع الحقوق محفوظة.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Email Branding')
                ->submit('save')
                ->icon('heroicon-o-check'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = GlobalSetting::current();
        $settings->update([
            'email_logo' => $data['email_logo'] ?? null,
            'email_custom_logo_url' => $data['email_custom_logo_url'] ?? null,
            'email_title_en' => $data['email_title_en'] ?? null,
            'email_title_ar' => $data['email_title_ar'] ?? null,
            'email_subtitle_en' => $data['email_subtitle_en'] ?? null,
            'email_subtitle_ar' => $data['email_subtitle_ar'] ?? null,
            'email_header_bg_color' => $data['email_header_bg_color'] ?? '#2D3F33',
            'email_body_bg_color' => $data['email_body_bg_color'] ?? '#FAF8F5',
            'email_card_bg_color' => $data['email_card_bg_color'] ?? '#FAF8F5',
            'email_accent_color' => $data['email_accent_color'] ?? '#8fae2a',
            'email_footer_name_en' => $data['email_footer_name_en'] ?? null,
            'email_footer_name_ar' => $data['email_footer_name_ar'] ?? null,
            'email_footer_address_en' => $data['email_footer_address_en'] ?? null,
            'email_footer_address_ar' => $data['email_footer_address_ar'] ?? null,
            'email_footer_phone' => $data['email_footer_phone'] ?? null,
            'email_footer_email' => $data['email_footer_email'] ?? null,
            'email_footer_copyright_en' => $data['email_footer_copyright_en'] ?? null,
            'email_footer_copyright_ar' => $data['email_footer_copyright_ar'] ?? null,
        ]);

        Notification::make()
            ->title('Email Branding Saved')
            ->body('Email template colors, logo, and footer updated successfully.')
            ->success()
            ->send();
    }
}
