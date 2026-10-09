<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GlobalSettingResource\Pages;
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
                        // TAB 1: BRANDING & IDENTITY (الهوية البصرية)
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('Branding & Assets')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Forms\Components\Section::make('Logos & Favicon (الشعارات وأيقونة المتصفح)')
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

                                Forms\Components\Section::make('Website Identity & Name (اسم الموقع والشعار اللفظي)')
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
                                            ->label('Store Operating Timezone (المنطقة الزمنية للمتجر)')
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
                        // TAB 2: TOP ANNOUNCEMENT BAR (الشريط العلوي)
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('Top Announcement Bar')
                            ->icon('heroicon-o-megaphone')
                            ->schema([
                                Forms\Components\Section::make('Top Bar Banner (الشريط الترويجي أعلى الهيدر)')
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
                        // TAB 3: FOOTER SETTINGS (إعدادات الفوتر)
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('Footer Settings')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Section::make('Footer Brand Bio (نبذة العلامة التجارية أسفل الشعار)')
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

                                Forms\Components\Section::make('Footer Copyright Notice (حقوق الملكية)')
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
                            ]),

                        // =========================================================================
                        // TAB: TAX & VAT SETTINGS (الضريبة والقيمة المضافة)
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('Tax & VAT')
                            ->icon('heroicon-o-receipt-percent')
                            ->schema([
                                Forms\Components\Section::make('Saudi Value Added Tax (VAT) / ضريبة القيمة المضافة')
                                    ->description('Configure the VAT rate applied across storefront checkout, order calculations, and tax invoices.')
                                    ->schema([
                                        Forms\Components\TextInput::make('vat_percentage')
                                            ->label('VAT Percentage (%) / نسبة ضريبة القيمة المضافة')
                                            ->numeric()
                                            ->suffix('%')
                                            ->default(15.00)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.01)
                                            ->required()
                                            ->helperText('Standard Saudi Arabia VAT is 15%. This rate dynamically updates the storefront checkout label, cart breakdown, and tax invoices.'),

                                        Forms\Components\TextInput::make('vat_registration_number')
                                            ->label('ZATCA Tax Registration Number / الرقم الضريبي الموحد')
                                            ->placeholder('300000000000003')
                                            ->helperText('15-digit Tax Identification Number registered with ZATCA (هيئة الزكاة والضريبة والجمارك) printed on invoices.'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Multi-Currency & USD Exchange Rate (العملات وسعر صرف الدولار)')
                                    ->description('Default store currency is Saudi Riyal (SAR). Customers can switch to US Dollar (USD) on the storefront header.')
                                    ->schema([
                                        Forms\Components\TextInput::make('sar_to_usd_rate')
                                            ->label('SAR to USD Rate (1 SAR = ? USD)')
                                            ->numeric()
                                            ->step(0.0001)
                                            ->default(0.2667)
                                            ->required()
                                            ->helperText('Multiplier to convert SAR to USD (e.g. 0.2667 means 100 SAR = $26.67 USD). Click button to live fetch.')
                                            ->suffixAction(
                                                Forms\Components\Actions\Action::make('fetchLiveRate')
                                                    ->label('Fetch Live Rate')
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->color('success')
                                                    ->tooltip('Fetch live exchange rate from open exchange API')
                                                    ->action(function (Forms\Set $set) {
                                                        try {
                                                            $res = \Illuminate\Support\Facades\Http::timeout(6)->get('https://open.er-api.com/v6/latest/SAR');
                                                            if ($res->successful()) {
                                                                $rate = $res->json()['rates']['USD'] ?? null;
                                                                if ($rate) {
                                                                    $rounded = round((float)$rate, 4);
                                                                    $set('sar_to_usd_rate', $rounded);
                                                                    $set('last_currency_rate_fetch_at', now());
                                                                    \Filament\Notifications\Notification::make()
                                                                        ->title('Live Currency Rate Fetched')
                                                                        ->body("1 SAR = {$rounded} USD (1 USD ≈ " . round(1 / $rounded, 2) . " SAR)")
                                                                        ->success()
                                                                        ->send();
                                                                    return;
                                                                }
                                                            }
                                                            throw new \Exception('API did not return a valid USD rate');
                                                        } catch (\Exception $e) {
                                                            \Filament\Notifications\Notification::make()
                                                                ->title('Failed to fetch rate')
                                                                ->body($e->getMessage())
                                                                ->danger()
                                                                ->send();
                                                        }
                                                    })
                                            ),

                                        Forms\Components\Placeholder::make('last_currency_rate_fetch_at_display')
                                            ->label('Last Live Rate Fetched')
                                            ->content(function (?GlobalSetting $record) {
                                                return $record?->last_currency_rate_fetch_at
                                                    ? \Carbon\Carbon::parse($record->last_currency_rate_fetch_at)->diffForHumans()
                                                    : 'Never fetched automatically (Default 0.2667)';
                                            }),
                                    ])
                                    ->columns(2),
                            ]),

                        // =========================================================================
                        // TAB 4: CONTACT & STORE INFO (بيانات التواصل والعنوان)
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
                                            ->placeholder('info@grassflorist.com'),

                                        Forms\Components\TextInput::make('business_hours')
                                            ->label('Store & Concierge Working Hours')
                                            ->placeholder('Daily 9:00 AM – 11:30 PM AST'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Physical Boutique Address (عنوان المتجر والبوتيك)')
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
                        // TAB 5: SOCIAL MEDIA PROFILES (وسائل التواصل الاجتماعي)
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
                        // TAB 6: SCRIPTS & TRACKING (الأكواد والإحصائيات)
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('Scripts & Tracking')
                            ->icon('heroicon-o-code-bracket')
                            ->schema([
                                Forms\Components\Section::make('Google Tag Manager (GTM)')
                                    ->description('Inject Google Tag Manager code snippets directly into your storefront.')
                                    ->schema([
                                        Forms\Components\Textarea::make('gtm_head_code')
                                            ->label('GTM Head Script (<head>)')
                                            ->placeholder("<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){...})(window,document,'script','dataLayer','GTM-XXXXXX');</script>\n<!-- End Google Tag Manager -->")
                                            ->rows(5)
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make('gtm_body_code')
                                            ->label('GTM Body NoScript (<body>)')
                                            ->placeholder("<!-- Google Tag Manager (noscript) -->\n<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=GTM-XXXXXX\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n<!-- End Google Tag Manager (noscript) -->")
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),

                                Forms\Components\Section::make('Google Analytics & Pixels')
                                    ->description('Configure GA4 Measurement ID and tracking pixels.')
                                    ->schema([
                                        Forms\Components\TextInput::make('ga_measurement_id')
                                            ->label('GA4 Measurement ID')
                                            ->placeholder('G-XXXXXXXXXX'),

                                        Forms\Components\TextInput::make('meta_pixel_id')
                                            ->label('Meta / Facebook Pixel ID')
                                            ->placeholder('123456789012345'),

                                        Forms\Components\TextInput::make('tiktok_pixel_id')
                                            ->label('TikTok Pixel ID')
                                            ->placeholder('CXXXXXXXXXXXXX'),

                                        Forms\Components\TextInput::make('snapchat_pixel_id')
                                            ->label('Snapchat Pixel ID')
                                            ->placeholder('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Custom Scripts (Head & Footer)')
                                    ->description('Custom CSS, meta tags, and third-party widgets.')
                                    ->schema([
                                        Forms\Components\Textarea::make('custom_head_scripts')
                                            ->label('Custom <head> Scripts')
                                            ->rows(4)
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make('custom_footer_scripts')
                                            ->label('Custom </body> Footer Scripts')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // =========================================================================
                        // TAB 7: SEO, SITEMAP & AI DISCOVERY (السيو ومحركات الذكاء الاصطناعي)
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('SEO & AI Discovery')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                // 1. SITEMAP.XML MANAGEMENT
                                Forms\Components\Section::make('Sitemap.xml Management (إدارة وتخصيص خريطة الموقع)')
                                    ->description('Control which resources are indexed in sitemap.xml, active/deactive status, and custom exclusions.')
                                    ->schema([
                                        Forms\Components\Toggle::make('sitemap_enabled')
                                            ->label('Enable Dynamic Sitemap (sitemap.xml)')
                                            ->helperText('Turn on to generate and serve dynamic sitemap.xml to Google and search engines.')
                                            ->default(true)
                                            ->columnSpanFull(),

                                        Forms\Components\Section::make('Included Resources (المحتوى المتضمن في الخريطة)')
                                            ->schema([
                                                Forms\Components\Toggle::make('sitemap_include_products')
                                                    ->label('Include Products')
                                                    ->helperText('Index all active products')
                                                    ->default(true),

                                                Forms\Components\Toggle::make('sitemap_include_categories')
                                                    ->label('Include Categories')
                                                    ->helperText('Index categories in AR & EN')
                                                    ->default(true),

                                                Forms\Components\Toggle::make('sitemap_include_static_pages')
                                                    ->label('Include Static Pages')
                                                    ->helperText('Index Home, About, Contact, Events')
                                                    ->default(true),

                                                Forms\Components\Toggle::make('sitemap_include_blog')
                                                    ->label('Include Blog Posts')
                                                    ->helperText('Index floral & gift articles')
                                                    ->default(true),
                                            ])
                                            ->columns(4)
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make('sitemap_excluded_paths')
                                            ->label('Excluded Paths & Routes (المسارات المستثناة)')
                                            ->placeholder("/cart\n/checkout\n/en/cart\n/en/checkout\n/account\n/api/*\n/admin/*")
                                            ->rows(4)
                                            ->helperText('Enter one URL path per line to exclude from sitemap indexing.')
                                            ->extraInputAttributes(['class' => 'font-mono text-xs'])
                                            ->columnSpanFull(),

                                        Forms\Components\Placeholder::make('sitemap_live_link')
                                            ->label('Live Sitemap Preview')
                                            ->content(function () {
                                                $localUrl = 'http://localhost:3000/sitemap.xml';
                                                return new \Illuminate\Support\HtmlString("
                                                    <a href='{$localUrl}' target='_blank' class='inline-flex items-center text-primary-600 dark:text-primary-400 hover:underline font-mono text-xs bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700'>
                                                        <span>Open Live {$localUrl}</span>
                                                        <svg class='w-3.5 h-3.5 ml-1.5 inline' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14'/></svg>
                                                    </a>
                                                ");
                                            })
                                            ->columnSpanFull(),
                                    ]),

                                // 2. ROBOTS.TXT EDITING
                                Forms\Components\Section::make('Robots.txt Editor (محرر ملف الروبوتات robots.txt)')
                                    ->description('Directly edit crawlers rules, allow/disallow paths, and sitemap directives.')
                                    ->schema([
                                        Forms\Components\Textarea::make('robots_custom_content')
                                            ->label('Custom robots.txt Rules & Directives')
                                            ->placeholder("User-agent: *\nAllow: /\nDisallow: /cart\nDisallow: /checkout\n\nSitemap: https://grassflorist.com/sitemap.xml")
                                            ->rows(8)
                                            ->helperText('Define crawler rules for Googlebot, Bingbot, and AI agents. Customize freely.')
                                            ->extraInputAttributes(['class' => 'font-mono text-xs'])
                                            ->columnSpanFull(),

                                        Forms\Components\Placeholder::make('robots_live_link')
                                            ->label('Live robots.txt Preview')
                                            ->content(function () {
                                                $localUrl = 'http://localhost:3000/robots.txt';
                                                return new \Illuminate\Support\HtmlString("
                                                    <a href='{$localUrl}' target='_blank' class='inline-flex items-center text-primary-600 dark:text-primary-400 hover:underline font-mono text-xs bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700'>
                                                        <span>Open Live {$localUrl}</span>
                                                        <svg class='w-3.5 h-3.5 ml-1.5 inline' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14'/></svg>
                                                    </a>
                                                ");
                                            })
                                            ->columnSpanFull(),
                                    ]),

                                // 3. LLMS.TXT & AI DISCOVERY EDITING (FULL CONTENT EDITOR)
                                Forms\Components\Section::make('AI Discovery & LLMs.txt Full Editor (محرر ملف نماذج الذكاء الاصطناعي llms.txt الكامل)')
                                    ->description('Edit the full Markdown content served to AI answer engines (ChatGPT, Claude, Perplexity) in English and Arabic.')
                                    ->schema([
                                        Forms\Components\Toggle::make('llms_enabled')
                                            ->label('Enable LLMs.txt AI Knowledge Feed (/llms.txt)')
                                            ->helperText('Turn on to serve optimized Markdown context to LLM crawlers like GPTBot and ClaudeBot.')
                                            ->default(true)
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make('llms_custom_content')
                                            ->label('Full llms.txt Content (محتوى ملف llms.txt بالكامل)')
                                            ->placeholder("# Grass Florist (غراس فلوريست) — Knowledge Specification for LLMs\n\n## 1. Brand Overview...\n## 2. Arabic Overview...")
                                            ->rows(16)
                                            ->helperText('Complete Markdown content for /llms.txt. Edit English, Arabic, services, and directives in one place.')
                                            ->extraInputAttributes(['class' => 'font-mono text-xs leading-relaxed'])
                                            ->columnSpanFull(),

                                        Forms\Components\Placeholder::make('llms_live_link')
                                            ->label('Live llms.txt Preview')
                                            ->content(function () {
                                                $localUrl = 'http://localhost:3000/llms.txt';
                                                return new \Illuminate\Support\HtmlString("
                                                    <a href='{$localUrl}' target='_blank' class='inline-flex items-center text-primary-600 dark:text-primary-400 hover:underline font-mono text-xs bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700'>
                                                        <span>Open Live {$localUrl}</span>
                                                        <svg class='w-3.5 h-3.5 ml-1.5 inline' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14'/></svg>
                                                    </a>
                                                ");
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // =========================================================================
                        // TAB 8: GOOGLE BUSINESS & REVIEWS (تقييمات وخرائط جوجل)
                        // =========================================================================
                        Forms\Components\Tabs\Tab::make('Google Reviews')
                            ->icon('heroicon-o-star')
                            ->schema([
                                Forms\Components\Section::make('Google Business Profile & Direct Review Link')
                                    ->description('Configure your Google Business link and direct "Write a Review" URL for customer redirects.')
                                    ->schema([
                                        Forms\Components\TextInput::make('google_business_url')
                                            ->label('Google Business Profile URL (رابط الملف التجاري)')
                                            ->placeholder('https://share.google/z7BTvYwJ4iPqWjQTK')
                                            ->helperText('Your official Google Maps / Business Profile page URL. Default: https://share.google/z7BTvYwJ4iPqWjQTK')
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('google_write_review_url')
                                            ->label('Direct "Write a Review" URL (رابط كتابة التقييم المباشر)')
                                            ->placeholder('https://search.google.com/local/writereview?placeid=... or https://g.page/r/.../review')
                                            ->helperText('Direct URL that opens Google review popup when customer clicks "Write a Review". If blank, it uses Business Profile URL.')
                                            ->columnSpanFull(),
                                    ]),
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
