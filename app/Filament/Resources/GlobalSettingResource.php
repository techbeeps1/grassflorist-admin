<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GlobalSettingResource\Pages;
use App\Models\Category;
use App\Models\GlobalSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GlobalSettingResource extends Resource
{
    protected static ?string $model = GlobalSetting::class;

    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Global Settings';
    protected static ?string $modelLabel = 'Global Setting';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'global-settings';

    /**
     * Strictly restricted to Admins only; Vendors cannot see or access this resource.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('global_settings') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Update Information')
                    ->schema([
                        Forms\Components\Placeholder::make('last_updated_at')
                            ->label('Last Updated')
                            ->content(function (?GlobalSetting $record) {
                                return $record?->updated_at
                                    ? $record->updated_at->format('d M Y, h:i A')
                                    : 'Not updated yet';
                            }),

                        Forms\Components\Placeholder::make('updated_by_name')
                            ->label('Updated By')
                            ->content(function (?GlobalSetting $record) {
                                return $record?->updatedBy?->name ?? 'System';
                            }),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Tabs::make('Global Settings')
                    ->tabs([
                        // =========================================================================
                        // TAB 1: BRANDING & IDENTITY
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('Branding & Assets')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Forms\Components\Section::make('Logos & Favicon')
                                    ->description('Upload header brand logo, dark/footer logo, and browser tab favicon.')
                                    ->schema([
                                        Forms\Components\FileUpload::make('site_logo')
                                            ->label('Header Logo (Primary)')
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('settings')
                                            ->visibility('public')
                                            ->helperText('Recommended: Transparent PNG, WEBP, or SVG (approx. 250×60 px).'),

                                        Forms\Components\FileUpload::make('site_logo_dark')
                                            ->label('Footer / Dark Background Logo')
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('settings')
                                            ->visibility('public')
                                            ->helperText('Displayed on the luxury dark footer background.'),

                                        Forms\Components\FileUpload::make('site_favicon')
                                            ->label('Browser Favicon')
                                            ->acceptedFileTypes([
                                                'image/x-icon',
                                                'image/vnd.microsoft.icon',
                                                'image/png',
                                                'image/svg+xml',
                                                'image/webp',
                                            ])
                                            ->disk('public')
                                            ->directory('settings')
                                            ->visibility('public')
                                            ->helperText('Square 1:1 icon (.ico, .png, or .svg) displayed in browser tabs.'),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('Website Identity & Name')
                                    ->description('Configure brand name and tagline in both English and Arabic.')
                                    ->schema([
                                        Forms\Components\TextInput::make('site_name_en')
                                            ->label('Website Title (English)')
                                            ->placeholder('Grass Florist')
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('site_name_ar')
                                            ->label('Website Title (Arabic)')
                                            ->placeholder('غراس فلوريست')
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('site_tagline_en')
                                            ->label('Brand Tagline (English)')
                                            ->placeholder('Luxury Floral Atelier & Curated Gifting')
                                            ->maxLength(150),

                                        Forms\Components\TextInput::make('site_tagline_ar')
                                            ->label('Brand Tagline (Arabic)')
                                            ->placeholder('متجر الزهور والهدايا الفاخرة')
                                            ->maxLength(150),

                                        Forms\Components\Select::make('timezone')
                                            ->label('Store Operating Timezone')
                                            ->options([
                                                'Asia/Riyadh' => '🇸🇦 Saudi Arabia – Riyadh (AST / GMT+3)',
                                                'Asia/Dubai' => '🇦🇪 United Arab Emirates – Dubai (GST / GMT+4)',
                                                'Asia/Kuwait' => '🇰🇼 Kuwait (GMT+3)',
                                                'Asia/Qatar' => '🇶🇦 Qatar (GMT+3)',
                                                'Asia/Bahrain' => '🇧🇭 Bahrain (GMT+3)',
                                                'UTC' => '🌐 Coordinated Universal Time (UTC)',
                                            ])
                                            ->default('Asia/Riyadh')
                                            ->required()
                                            ->native(false)
                                            ->helperText('Defines exact store time for checkout delivery slots, same-day cutoffs, and holiday blackout calculations in Saudi Arabia.')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),

                        // =========================================================================
                        // TAB 2: TOP ANNOUNCEMENT BAR
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('Top Announcement Bar')
                            ->icon('heroicon-o-megaphone')
                            ->schema([
                                Forms\Components\Section::make('Top Bar Banner')
                                    ->description('Configure the top announcement bar visible across the website.')
                                    ->schema([
                                        Forms\Components\Toggle::make('topbar_enabled')
                                            ->label('Enable Top Announcement Bar')
                                            ->helperText('Turn on to display the top banner across all storefront pages.')
                                            ->default(true)
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make('topbar_text_en')
                                            ->label('Announcement Message (English)')
                                            ->placeholder('Express Same-Day Delivery in 2 Hours | Free Delivery on Orders Over 250 SAR')
                                            ->rows(2),

                                        Forms\Components\Textarea::make('topbar_text_ar')
                                            ->label('Announcement Message (Arabic)')
                                            ->placeholder('توصيل سريع في نفس اليوم خلال ساعتين | شحن مجاني للطلبات فوق 250 ر.س')
                                            ->rows(2),
                                    ])
                                    ->columns(2),
                            ]),

                        // =========================================================================
                        // TAB 3: CONTACT & STORE INFO
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('Contact & Support')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Forms\Components\Section::make('Customer Support Channels')
                                    ->description('Official contact channels shown on header, footer, and contact pages.')
                                    ->schema([
                                        Forms\Components\TextInput::make('contact_phone')
                                            ->label('Customer Helpline / Phone')
                                            ->tel()
                                            ->placeholder('+966 55 513 4211')
                                            ->helperText('Main helpline displayed across Top Bar, Header, Footer & Contact pages.'),

                                        Forms\Components\TextInput::make('contact_whatsapp')
                                            ->label('WhatsApp Support Number')
                                            ->tel()
                                            ->placeholder('+966 55 513 4211')
                                            ->helperText('Customers can directly start a WhatsApp conversation with this number.'),

                                        Forms\Components\TextInput::make('contact_email')
                                            ->label('Official Support Email')
                                            ->email()
                                            ->placeholder('info@grassflorist.com')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Physical Boutique Address')
                                    ->description('Flagship showroom and delivery hub address.')
                                    ->schema([
                                        Forms\Components\Textarea::make('contact_address_en')
                                            ->label('Store Address (English)')
                                            ->rows(2)
                                            ->placeholder('4366 Al Kayyal Street, Al-Rawdah District, Jeddah 23434, Saudi Arabia'),

                                        Forms\Components\Textarea::make('contact_address_ar')
                                            ->label('Store Address (Arabic)')
                                            ->rows(2)
                                            ->placeholder('4366 شارع الكيال، حي الروضة، جدة 23434، المملكة العربية السعودية'),
                                    ])
                                    ->columns(2),
                            ]),

                        // =========================================================================
                        // TAB 4: SOCIAL MEDIA PROFILES
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('Social Links')
                            ->icon('heroicon-o-share')
                            ->schema([
                                Forms\Components\Section::make('Official Social Media Profiles')
                                    ->description('Direct links to your brand social networks displayed in the footer.')
                                    ->schema([
                                        Forms\Components\TextInput::make('social_instagram')
                                            ->label('Instagram Profile URL')
                                            ->url()
                                            ->placeholder('https://instagram.com/grassflorist'),

                                        Forms\Components\TextInput::make('social_snapchat')
                                            ->label('Snapchat Profile URL')
                                            ->url()
                                            ->placeholder('https://snapchat.com/add/grassflorist')
                                            ->helperText('Direct link or add URL for Snapchat.'),

                                        Forms\Components\TextInput::make('social_tiktok')
                                            ->label('TikTok Profile URL')
                                            ->url()
                                            ->placeholder('https://tiktok.com/@grassflorist'),

                                        Forms\Components\TextInput::make('social_twitter')
                                            ->label('Twitter / X Profile URL')
                                            ->url()
                                            ->placeholder('https://x.com/grassflorist'),

                                        Forms\Components\TextInput::make('social_facebook')
                                            ->label('Facebook Page URL')
                                            ->url()
                                            ->placeholder('https://facebook.com/grassflorist'),

                                        Forms\Components\TextInput::make('social_youtube')
                                            ->label('YouTube Channel URL')
                                            ->url()
                                            ->placeholder('https://youtube.com/@grassflorist'),

                                        Forms\Components\TextInput::make('social_linkedin')
                                            ->label('LinkedIn Profile URL')
                                            ->url()
                                            ->placeholder('https://linkedin.com/company/grassflorist'),
                                    ])
                                    ->columns(2),
                            ]),

                        // =========================================================================
                        // TAB 5: FOOTER & REVIEWS
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('Footer & Reviews')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Section::make('Footer Brand Bio')
                                    ->description('Short paragraph describing the brand under the footer logo.')
                                    ->schema([
                                        Forms\Components\Textarea::make('footer_about_en')
                                            ->label('Brand Bio (English)')
                                            ->rows(3)
                                            ->placeholder('Grass Florist is a luxury floral atelier and gifting boutique crafting bespoke bouquets, Belgian chocolates, and living plants with same-day express delivery across Saudi Arabia.'),

                                        Forms\Components\Textarea::make('footer_about_ar')
                                            ->label('Brand Bio (Arabic)')
                                            ->rows(3)
                                            ->placeholder('غراس فلوريست هي بوتيك واستوديو للزهور والهدايا الفاخرة، تقدم باقات منسقة خصيصاً، وشوكولاتة بلجيكية فاخرة، ونباتات داخلية مع توصيل مبرد وسريع في نفس اليوم في المملكة العربية السعودية.'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Footer Copyright Notice')
                                    ->description('Copyright text shown at the bottom of the footer.')
                                    ->schema([
                                        Forms\Components\TextInput::make('footer_copyright_en')
                                            ->label('Copyright Notice (English)')
                                            ->placeholder('Copyright© 2026, GRASS Florist, All Rights Reserved.'),

                                        Forms\Components\TextInput::make('footer_copyright_ar')
                                            ->label('Copyright Notice (Arabic)')
                                            ->placeholder('جميع الحقوق محفوظة © 2026، غراس فلوريست.'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Google Business Profile & Review Link')
                                    ->description('Configure your Google Business link and direct "Write a Review" URL for customer redirects.')
                                    ->schema([
                                        Forms\Components\TextInput::make('google_business_url')
                                            ->label('Google Business Profile URL')
                                            ->placeholder('https://share.google/z7BTvYwJ4iPqWjQTK')
                                            ->helperText('Your official Google Maps / Business Profile page URL.'),

                                        Forms\Components\TextInput::make('google_write_review_url')
                                            ->label('Direct "Write a Review" URL')
                                            ->placeholder('https://search.google.com/local/writereview?placeid=... or https://g.page/r/.../review')
                                            ->helperText('Direct URL that opens Google review popup when customer clicks "Write a Review".'),
                                    ])
                                    ->columns(2),
                            ]),

                        // =========================================================================
                        // TAB 6: WEBHOOKS & 3RD-PARTY INTEGRATIONS (ERP / SAAS / BOTS)
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('Webhooks & Integrations')
                            ->icon('heroicon-o-signal')
                            ->schema([
                                Forms\Components\Section::make('Webhook Dispatcher Settings')
                                    ->description('Send real-time JSON webhooks to 3rd-party ERPs (Odoo, Zoho, SAP), CRMs, WhatsApp notification bots, Zapier, or custom SaaS platforms.')
                                    ->schema([
                                        Forms\Components\Toggle::make('webhook_enabled')
                                            ->label('Enable Outgoing Webhooks')
                                            ->helperText('Toggle to activate or deactivate real-time event webhook notifications.')
                                            ->default(false)
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('webhook_default_url')
                                            ->label('Default Webhook Destination URL')
                                            ->placeholder('https://api.your-erp.com/webhooks/grass-florist or https://webhook.site/...')
                                            ->url()
                                            ->helperText('The primary HTTP POST endpoint where all selected events will be delivered.')
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('webhook_timeout')
                                            ->label('Request Timeout (Seconds)')
                                            ->numeric()
                                            ->minValue(2)
                                            ->maxValue(60)
                                            ->default(10)
                                            ->helperText('Maximum seconds to wait for 3rd-party server response before timing out.'),

                                        Forms\Components\TextInput::make('webhook_secret')
                                            ->label('HMAC Secret Key (Optional Signature)')
                                            ->password()
                                            ->revealable()
                                            ->placeholder('e.g. whsec_9a8b7c6d5e4f3a2b1c')
                                            ->helperText('If provided, outgoing requests include an HMAC-SHA256 signature in the X-Webhook-Signature header for payload verification.')
                                            ->columnSpanFull(),

                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('test_ping')
                                                ->label('Send Test Ping to Webhook URL')
                                                ->icon('heroicon-o-paper-airplane')
                                                ->color('success')
                                                ->action(function ($get) {
                                                    $url = $get('webhook_default_url');
                                                    $res = \App\Services\Webhooks\WebhookService::sendTestPing($url);
                                                    if ($res['success']) {
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Webhook Test Succeeded')
                                                            ->body($res['message'])
                                                            ->success()
                                                            ->send();
                                                    } else {
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Webhook Test Failed')
                                                            ->body($res['message'])
                                                            ->danger()
                                                            ->send();
                                                    }
                                                }),
                                        ])->columnSpanFull(),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('Trigger Events (Select Actions to Dispatch)')
                                    ->description('Choose which business events will trigger an automatic webhook POST request.')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('webhook_events')
                                            ->label('Active Event Triggers')
                                            ->options(\App\Services\Webhooks\WebhookService::getAvailableEvents())
                                            ->columns(2)
                                            ->default([
                                                \App\Services\Webhooks\WebhookService::EVENT_ORDER_CREATED,
                                                \App\Services\Webhooks\WebhookService::EVENT_ORDER_STATUS_UPDATED,
                                                \App\Services\Webhooks\WebhookService::EVENT_ORDER_PAID,
                                                \App\Services\Webhooks\WebhookService::EVENT_USER_REGISTERED,
                                                \App\Services\Webhooks\WebhookService::EVENT_SUBSCRIPTION_CREATED,
                                                \App\Services\Webhooks\WebhookService::EVENT_NEWSLETTER_SUBSCRIBED,
                                                \App\Services\Webhooks\WebhookService::EVENT_USER_PASSWORD_CHANGED,
                                            ])
                                            ->helperText('Only checked events will trigger an outgoing webhook notification.')
                                            ->columnSpanFull(),
                                    ]),

                                Forms\Components\Section::make('Custom Request Headers')
                                    ->description('Add authentication tokens or custom headers required by your 3rd-party ERP or SaaS.')
                                    ->schema([
                                        Forms\Components\Repeater::make('webhook_headers')
                                            ->label('Headers List')
                                            ->schema([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Header Key')
                                                    ->placeholder('Authorization, X-API-Key, etc.')
                                                    ->required(),
                                                Forms\Components\TextInput::make('value')
                                                    ->label('Header Value')
                                                    ->placeholder('Bearer eyJ..., token-xyz')
                                                    ->required(),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(0)
                                            ->addActionLabel('Add Custom Header')
                                            ->collapsible(),
                                    ])
                                    ->collapsed(),

                                Forms\Components\Section::make('Specific Event URL Overrides (Optional)')
                                    ->description('By default, all events are sent to the Default Webhook Destination URL. Enter specific URLs below only if you want certain events routed to dedicated endpoints.')
                                    ->schema([
                                        Forms\Components\TextInput::make('webhook_order_created_url')
                                            ->label('Order Created Override URL')
                                            ->placeholder('Leave empty to use Default Webhook URL')
                                            ->url(),

                                        Forms\Components\TextInput::make('webhook_order_status_url')
                                            ->label('Order Status / Paid Override URL')
                                            ->placeholder('Leave empty to use Default Webhook URL')
                                            ->url(),

                                        Forms\Components\TextInput::make('webhook_user_registered_url')
                                            ->label('Customer Registered Override URL')
                                            ->placeholder('Leave empty to use Default Webhook URL')
                                            ->url(),

                                        Forms\Components\TextInput::make('webhook_subscription_created_url')
                                            ->label('Subscription Created Override URL')
                                            ->placeholder('Leave empty to use Default Webhook URL')
                                            ->url(),

                                        Forms\Components\TextInput::make('webhook_newsletter_url')
                                            ->label('Newsletter Subscribed Override URL')
                                            ->placeholder('Leave empty to use Default Webhook URL')
                                            ->url(),

                                        Forms\Components\TextInput::make('webhook_password_changed_url')
                                            ->label('Password Changed Override URL')
                                            ->placeholder('Leave empty to use Default Webhook URL')
                                            ->url(),
                                    ])
                                    ->columns(2)
                                    ->collapsed(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('site_name_en')
                    ->label('Website (EN)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('site_name_ar')
                    ->label('Website (AR)')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('site_logo')
                    ->label('Logo'),
                Tables\Columns\TextColumn::make('contact_email')
                    ->label('Email'),
                Tables\Columns\TextColumn::make('contact_phone')
                    ->label('Phone'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y, h:i A'),
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
            'index' => Pages\ListGlobalSettings::route('/'),
            'edit' => Pages\EditGlobalSetting::route('/{record}/edit'),
        ];
    }

    /**
     * Resolve record for edit route. Auto-creates record #1 if the table is currently empty.
     */
    public static function resolveRecordRouteBinding(int | string $key): ?\Illuminate\Database\Eloquent\Model
    {
        return GlobalSetting::firstOrCreate(
            ['id' => $key],
            [
                'site_name' => 'Grass Florist',
                'site_name_en' => 'Grass Florist',
                'site_name_ar' => 'غراس فلوريست',
                'site_tagline_en' => 'Luxury Floral Atelier & Curated Gifting',
                'site_tagline_ar' => 'متجر الزهور والهدايا الفاخرة',
                'footer_copyright_en' => 'Copyright© 2026, GRASS Florist, All Rights Reserved.',
                'footer_copyright_ar' => 'جميع الحقوق محفوظة © 2026، غراس فلوريست.',
            ]
        );
    }

    /**
     * Direct Navigation: Clicking 'Global Settings' opens the Edit page directly.
     */
    public static function getNavigationUrl(): string
    {
        $record = GlobalSetting::current();

        return static::getUrl('edit', ['record' => $record->id]);
    }
}
