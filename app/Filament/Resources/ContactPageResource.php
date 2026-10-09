<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactPageResource\Pages;
use App\Models\ContactPage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactPageResource extends Resource
{
    protected static ?string $model = ContactPage::class;
    protected static ?string $navigationGroup = 'Pages';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Contact Page';
    protected static ?string $pluralModelLabel = 'Contact Pages';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('contact_page') ?? false;
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return 'Contact Page Settings';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Update Information')
                    ->schema([
                        Forms\Components\Placeholder::make('last_updated_at')
                            ->label('Last Updated')
                            ->content(function (?ContactPage $record) {
                                return $record?->updated_at
                                    ? $record->updated_at->format('d M Y, h:i A')
                                    : 'Not updated yet';
                            }),

                        Forms\Components\Placeholder::make('updated_by')
                            ->label('Updated By')
                            ->content(function (?ContactPage $record) {
                                return $record?->updatedBy?->name ?? 'Not available';
                            }),
                    ])
                    ->columns(2)
                    ->visible(fn (string $operation) => $operation === 'edit'),

                // 📧 Form Email Notification Settings (User Request: Konse email par bhejna hai aur title set karne ka option)
                Forms\Components\Section::make('Contact Form Email Notification Settings')
                    ->description('Configure where form inquiries should be emailed and custom email subject')
                    ->icon('heroicon-o-envelope')
                    ->schema([
                        Forms\Components\TextInput::make('notification_email')
                            ->label('Recipient Email Address(es)')
                            ->placeholder('e.g. info@grassflorist.com, concierge@grassflorist.com')
                            ->helperText('Form inquiries submitted by customers will be immediately sent to this email address. Separate multiple emails with commas.')
                            ->required(),

                        Forms\Components\TextInput::make('email_subject')
                            ->label('Email Subject / Notification Title')
                            ->placeholder('e.g. New Contact Inquiry from Grass Florist Website')
                            ->helperText('Subject line for incoming emails when a visitor submits the contact form.')
                            ->default('New Contact Inquiry from Grass Florist Website')
                            ->required(),
                    ])
                    ->columns(2),

                // 🌐 Multilingual Content (EN & AR tabs)
                Forms\Components\Tabs::make('Contact Page Content & Headings')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('English (EN)')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('badge.en')
                                            ->label('Top Badge / Tagline (English)')
                                            ->placeholder('e.g. ALWAYS AT YOUR SERVICE'),

                                        Forms\Components\TextInput::make('page_title.en')
                                            ->label('Main Page Title (English)')
                                            ->placeholder('e.g. Connect with Our Concierge')
                                            ->required(),
                                    ]),

                                Forms\Components\Textarea::make('page_subtitle.en')
                                    ->label('Page Subtitle / Description (English)')
                                    ->placeholder('We are at your service for bespoke floral requests, event styling, and delivery inquiries.')
                                    ->rows(2),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('form_title.en')
                                            ->label('Contact Form Box Title (English)')
                                            ->placeholder('e.g. Send an Inquiry'),

                                        Forms\Components\TextInput::make('inquiries_title.en')
                                            ->label('Direct Inquiries Box Title (English)')
                                            ->placeholder('e.g. DIRECT INQUIRIES'),
                                    ]),

                                Forms\Components\TextInput::make('working_hours.en')
                                    ->label('Operating Hours (English)')
                                    ->placeholder('e.g. Daily 9:00 AM - 11:30 PM AST'),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('ateliers_title.en')
                                            ->label('Boutique Box Title (English)')
                                            ->placeholder('e.g. BOUTIQUE ATELIERS'),

                                        Forms\Components\TextInput::make('city.en')
                                            ->label('Boutique City Name (English)')
                                            ->placeholder('e.g. Jeddah'),

                                        Forms\Components\TextInput::make('address.en')
                                            ->label('Boutique Street Address (English)')
                                            ->placeholder('e.g. 4366 Al Kayyal Street, Al-Rawdah District, Jeddah 23434, Saudi Arabia'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Arabic (عربي)')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('badge.ar')
                                            ->label('شارة العنوان / الشعار (عربي)')
                                            ->placeholder('مثال: نحن في خدمتك')
                                            ->extraInputAttributes(['dir' => 'rtl']),

                                        Forms\Components\TextInput::make('page_title.ar')
                                            ->label('عنوان الصفحة الرئيسي (عربي)')
                                            ->placeholder('مثال: تواصل مع خدمة العملاء')
                                            ->extraInputAttributes(['dir' => 'rtl'])
                                            ->required(),
                                    ]),

                                Forms\Components\Textarea::make('page_subtitle.ar')
                                    ->label('الوصف الترحيبي الفرعي (عربي)')
                                    ->placeholder('نحن في خدمتكم لتلبية طلبات الزهور وتنسيق المناسبات والاستفسارات الفاخرة.')
                                    ->rows(2)
                                    ->extraInputAttributes(['dir' => 'rtl']),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('form_title.ar')
                                            ->label('عنوان صندوق نموذج المراسلة (عربي)')
                                            ->placeholder('مثال: أرسل استفسارك')
                                            ->extraInputAttributes(['dir' => 'rtl']),

                                        Forms\Components\TextInput::make('inquiries_title.ar')
                                            ->label('عنوان صندوق التواصل المباشر (عربي)')
                                            ->placeholder('مثال: التواصل المباشر')
                                            ->extraInputAttributes(['dir' => 'rtl']),
                                    ]),

                                Forms\Components\TextInput::make('working_hours.ar')
                                    ->label('أوقات وساعات العمل (عربي)')
                                    ->placeholder('مثال: يومياً من 9:00 صباحاً حتى 11:30 مساءً')
                                    ->extraInputAttributes(['dir' => 'rtl']),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('ateliers_title.ar')
                                            ->label('عنوان صندوق الفروع (عربي)')
                                            ->placeholder('مثال: فروعنا وبوتيكاتنا')
                                            ->extraInputAttributes(['dir' => 'rtl']),

                                        Forms\Components\TextInput::make('city.ar')
                                            ->label('اسم المدينة (عربي)')
                                            ->placeholder('مثال: جدة')
                                            ->extraInputAttributes(['dir' => 'rtl']),

                                        Forms\Components\TextInput::make('address.ar')
                                            ->label('عنوان الفرع والشارع (عربي)')
                                            ->placeholder('مثال: ٤٣٦٦ شارع الكيال، حي الروضة، جدة ٢٣٤٣٤، المملكة العربية السعودية')
                                            ->extraInputAttributes(['dir' => 'rtl']),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),

                // 📞 Direct Channels & Location Links
                Forms\Components\Section::make('Contact Phone, WhatsApp & Map')
                    ->description('Contact channels displayed on the storefront')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Direct Phone Number')
                            ->placeholder('+966 55 513 4211')
                            ->required(),

                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp Number / Concierge Link')
                            ->placeholder('+966 55 513 4211')
                            ->required(),

                        Forms\Components\TextInput::make('email')
                            ->label('Public Display Email')
                            ->placeholder('info@grassflorist.com')
                            ->email()
                            ->required(),

                        Forms\Components\TextInput::make('con_map')
                            ->label('Google Maps Embed URL / Location Link')
                            ->placeholder('https://maps.google.com/...')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                // 🔍 SEO Metadata
                Forms\Components\Section::make('SEO Metadata')
                    ->description('Search engine optimization title, description, and keywords')
                    ->icon('heroicon-o-magnifying-glass')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('meta_tag_title.en')
                                    ->label('Meta Title (English)'),
                                Forms\Components\TextInput::make('meta_tag_title.ar')
                                    ->label('Meta Title (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl']),
                                Forms\Components\TextInput::make('meta_tag_keywords.en')
                                    ->label('Meta Keywords (English)'),
                                Forms\Components\TextInput::make('meta_tag_keywords.ar')
                                    ->label('Meta Keywords (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl']),
                                Forms\Components\Textarea::make('meta_tag_description.en')
                                    ->label('Meta Description (English)')
                                    ->rows(2),
                                Forms\Components\Textarea::make('meta_tag_description.ar')
                                    ->label('Meta Description (Arabic - عربي)')
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->rows(2),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('page_title')
                    ->label('Title')
                    ->formatStateUsing(fn ($record) => format_translatable($record->page_title, 'en') ?: 'Contact Page'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone'),
                Tables\Columns\TextColumn::make('notification_email')
                    ->label('Notification Email')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactPages::route('/'),
            'create' => Pages\CreateContactPage::route('/create'),
            'edit' => Pages\EditContactPage::route('/{record}/edit'),
        ];
    }

    public static function getNavigationUrl(): string
    {
        $recordId = \App\Models\ContactPage::query()->first()?->id;

        return $recordId
            ? static::getUrl('edit', ['record' => $recordId])
            : static::getUrl('index');
    }
}
